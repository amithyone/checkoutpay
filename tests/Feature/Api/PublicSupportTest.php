<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Models\WhatsappWallet;
use App\Services\Support\SupportConversationService;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PublicSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSupportSchema();

        $mock = Mockery::mock(EvolutionWhatsAppClient::class);
        $mock->shouldReceive('sendText')->andReturn(true);
        $this->app->instance(EvolutionWhatsAppClient::class, $mock);
    }

    private function ensureSupportSchema(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');

        if (! $schema->hasTable('payments')) {
            $schema->create('payments', function (Blueprint $table) {
                $table->id();
                $table->string('transaction_id')->unique();
                $table->decimal('amount', 14, 2);
                $table->string('status', 32)->default('pending');
                $table->unsignedBigInteger('business_id')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('matched_at')->nullable();
                $table->decimal('received_amount', 14, 2)->nullable();
                $table->string('payer_name')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable('whatsapp_wallets')) {
            $schema->create('whatsapp_wallets', function (Blueprint $table) {
                $table->id();
                $table->string('phone_e164', 32)->unique();
                $table->string('pay_code', 32)->nullable();
                $table->unsignedBigInteger('renter_id')->nullable();
                $table->unsignedTinyInteger('tier')->default(1);
                $table->decimal('balance', 14, 2)->default(0);
                $table->string('status', 32)->default('active');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('admins')) {
            $schema->create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('role')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable('support_tickets')) {
            $schema->create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 32)->default('business_dashboard');
                $table->string('issue_type', 64)->nullable();
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->string('payment_transaction_id', 64)->nullable();
                $table->decimal('payment_amount_reported', 14, 2)->nullable();
                $table->unsignedBigInteger('business_id')->nullable();
                $table->unsignedBigInteger('whatsapp_wallet_id')->nullable();
                $table->boolean('wallet_linked')->default(false);
                $table->char('visitor_country', 2)->nullable();
                $table->string('ticket_number')->unique();
                $table->string('subject');
                $table->text('message');
                $table->string('visitor_name')->nullable();
                $table->string('visitor_email')->nullable();
                $table->string('visitor_phone', 20)->nullable();
                $table->uuid('public_token')->nullable()->unique();
                $table->timestamp('wallet_onboarding_sent_at')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->unsignedInteger('admin_unread_count')->default(0);
                $table->unsignedInteger('visitor_unread_count')->default(0);
                $table->string('last_visitor_ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('priority')->default('medium');
                $table->string('status')->default('open');
                $table->unsignedBigInteger('assigned_to')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable('settings')) {
            $schema->create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string');
                $table->text('description')->nullable();
                $table->string('group')->default('general');
                $table->timestamps();
            });
        }

        config([
            'whatsapp.evolution.instance' => 'test',
            'whatsapp.evolution.base_url' => 'http://localhost',
            'whatsapp.evolution.api_key' => 'test-key',
        ]);

        if (! $schema->hasTable('support_ticket_replies')) {
            $schema->create('support_ticket_replies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_type');
                $table->text('message');
                $table->json('attachments')->nullable();
                $table->boolean('is_internal_note')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /** @test */
    public function options_returns_countries_and_suggested_country_from_header(): void
    {
        $this->withHeader('CF-IPCountry', 'GH')
            ->getJson('/api/v1/public/support/options')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.suggested_country', 'GH')
            ->assertJsonPath('data.payment_session_label', 'Bank session ID')
            ->assertJsonStructure([
                'data' => [
                    'countries' => [['iso', 'label', 'dial']],
                    'suggested_country',
                    'default_country',
                    'payment_session_hint',
                    'issue_types' => [['key', 'label', 'requires_payment']],
                ],
            ]);
    }

    /** @test */
    public function payment_lookup_returns_summary_for_pending_payment(): void
    {
        Payment::create([
            'transaction_id' => 'TXN-TEST-PENDING-1',
            'amount' => 7500,
            'status' => Payment::STATUS_PENDING,
            'expires_at' => now()->addHour(),
        ]);

        $this->getJson('/api/v1/public/support/payment-lookup?transaction_id=TXN-TEST-PENDING-1')
            ->assertOk()
            ->assertJsonPath('data.transaction_id', 'TXN-TEST-PENDING-1')
            ->assertJsonPath('data.is_pending', true);
    }

    /** @test */
    public function quick_payment_pending_start_with_bank_ref_stores_id_without_payment_link(): void
    {
        $this->postJson('/api/v1/public/support/conversations', [
            'link_whatsapp_wallet' => false,
            'issue_type' => 'payment_pending_transfer',
            'payment_transaction_id' => 'BANK-REF-12345',
            'payment_amount_reported' => 5000,
            'consent_accepted' => true,
            'channel' => 'checkout_web',
        ])->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'issue_type' => 'payment_pending_transfer',
            'payment_id' => null,
            'payment_transaction_id' => 'BANK-REF-12345',
            'payment_amount_reported' => 5000,
            'priority' => SupportTicket::PRIORITY_HIGH,
        ]);

        $ticket = SupportTicket::query()->where('payment_transaction_id', 'BANK-REF-12345')->first();
        $this->assertNotNull($ticket);
        $this->assertStringContainsString('Bank session ID: BANK-REF-12345', $ticket->message);
        $this->assertStringContainsString('not found in system', strtolower($ticket->message));
    }

    /** @test */
    public function quick_payment_pending_start_links_payment_when_reference_matches_txn(): void
    {
        $payment = Payment::create([
            'transaction_id' => 'TXN-QUICK-99',
            'amount' => 5000,
            'status' => Payment::STATUS_PENDING,
            'expires_at' => now()->addHour(),
        ]);

        $this->postJson('/api/v1/public/support/conversations', [
            'link_whatsapp_wallet' => false,
            'issue_type' => 'payment_pending_transfer',
            'payment_transaction_id' => 'TXN-QUICK-99',
            'payment_amount_reported' => 5000,
            'consent_accepted' => true,
            'channel' => 'checkout_web',
        ])->assertOk();

        $this->assertDatabaseHas('support_tickets', [
            'issue_type' => 'payment_pending_transfer',
            'payment_id' => $payment->id,
            'payment_transaction_id' => 'TXN-QUICK-99',
        ]);

        $ticket = SupportTicket::query()->where('payment_transaction_id', 'TXN-QUICK-99')->first();
        $this->assertNotNull($ticket);
        $this->assertStringContainsString('Bank session ID: TXN-QUICK-99', $ticket->message);
        $this->assertStringContainsString('pending', strtolower($ticket->message));
    }

    /** @test */
    public function anonymous_start_without_phone(): void
    {
        $this->postJson('/api/v1/public/support/conversations', [
            'link_whatsapp_wallet' => false,
            'consent_accepted' => true,
            'channel' => 'checkout_web',
            'name' => 'Guest User',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['public_token', 'ticket_id']]);

        $this->assertDatabaseHas('support_tickets', [
            'channel' => 'checkout_web',
            'wallet_linked' => 0,
            'visitor_name' => 'Guest User',
            'whatsapp_wallet_id' => null,
            'visitor_phone' => null,
        ]);

        $this->assertDatabaseCount('whatsapp_wallets', 0);
    }

    /** @test */
    public function wallet_start_with_gh_country_creates_wallet(): void
    {
        $this->postJson('/api/v1/public/support/conversations', [
            'link_whatsapp_wallet' => true,
            'country_iso' => 'GH',
            'phone' => '0241234567',
            'consent_accepted' => true,
            'wallet_consent_accepted' => true,
            'channel' => 'checkout_web',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['public_token', 'wallet_id', 'ticket_id']]);

        $this->assertDatabaseHas('whatsapp_wallets', [
            'phone_e164' => '233241234567',
        ]);

        $this->assertDatabaseHas('support_tickets', [
            'channel' => 'checkout_web',
            'wallet_linked' => 1,
            'visitor_country' => 'GH',
            'visitor_phone' => '233241234567',
        ]);
    }

    /** @test */
    public function wallet_start_requires_consent_phone_and_country(): void
    {
        $this->postJson('/api/v1/public/support/conversations', [
            'link_whatsapp_wallet' => true,
            'phone' => '08012345678',
            'channel' => 'checkout_web',
        ])->assertStatus(422);

        $this->postJson('/api/v1/public/support/conversations', [
            'link_whatsapp_wallet' => true,
            'phone' => '08012345678',
            'country_iso' => 'NG',
            'consent_accepted' => true,
            'wallet_consent_accepted' => true,
            'channel' => 'checkout_web',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['public_token', 'wallet_id', 'ticket_id']]);

        $this->assertDatabaseHas('whatsapp_wallets', [
            'phone_e164' => '2348012345678',
        ]);

        $this->assertDatabaseHas('support_tickets', [
            'channel' => 'checkout_web',
            'visitor_phone' => '2348012345678',
            'wallet_linked' => 1,
        ]);
    }

    /** @test */
    public function visitor_can_send_and_admin_can_poll(): void
    {
        $start = $this->postJson('/api/v1/public/support/conversations', [
            'link_whatsapp_wallet' => true,
            'phone' => '08098765432',
            'country_iso' => 'NG',
            'consent_accepted' => true,
            'wallet_consent_accepted' => true,
            'channel' => 'checkout_web',
            'first_message' => 'Hello support',
        ])->assertOk();

        $token = (string) $start->json('data.public_token');

        $this->postJson('/api/v1/public/support/conversations/'.$token.'/messages', [
            'message' => 'Follow up question',
        ])->assertOk();

        $ticket = SupportTicket::query()->where('public_token', $token)->first();
        $this->assertNotNull($ticket);
        $this->assertGreaterThanOrEqual(1, $ticket->admin_unread_count);

        $service = app(SupportConversationService::class);
        $adminMessages = $service->listMessagesForAdmin($ticket, null, true);
        $this->assertGreaterThanOrEqual(2, count($adminMessages));

        $ticket->refresh();
        $this->assertSame(0, $ticket->admin_unread_count);

        $admin = Admin::create([
            'name' => 'Support Admin',
            'email' => 'admin-support-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->actingAs($admin, 'admin');
        $result = $service->addAdminReply($ticket, 'We are looking into it', false);
        $this->assertTrue($result['ok']);

        $poll = $this->getJson('/api/v1/public/support/conversations/'.$token.'/messages?after_id=0')
            ->assertOk();

        $messages = $poll->json('data.messages');
        $this->assertIsArray($messages);
        $this->assertGreaterThanOrEqual(2, count($messages));
    }

    /** @test */
    public function resume_conversation_by_token_without_reentering_phone(): void
    {
        $start = $this->postJson('/api/v1/public/support/conversations', [
            'link_whatsapp_wallet' => true,
            'phone' => '08011112222',
            'country_iso' => 'NG',
            'consent_accepted' => true,
            'wallet_consent_accepted' => true,
            'channel' => 'checkout_web',
        ])->assertOk();

        $token = (string) $start->json('data.public_token');

        $this->getJson('/api/v1/public/support/conversations/'.$token.'/messages')
            ->assertOk()
            ->assertJsonPath('success', true);

        $wallet = WhatsappWallet::query()->where('phone_e164', '2348011112222')->first();
        $this->assertNotNull($wallet);
    }
}
