<?php

namespace Tests\Feature\Api;

use App\Models\AccountNumber;
use App\Models\Admin;
use App\Models\Payment;
use App\Models\SupportIntakeSession;
use App\Models\SupportTicket;
use App\Models\WhatsappWallet;
use App\Services\Support\SupportConversationService;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PublicSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSupportSchema();

        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (['support-start', 'support-write', 'support-poll', 'support-options', 'api'] as $name) {
            RateLimiter::clear($name);
        }

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
                $table->string('account_number', 32)->nullable();
                $table->string('payment_source', 64)->nullable();
                $table->string('external_reference', 128)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! $schema->hasTable('account_numbers')) {
            $schema->create('account_numbers', function (Blueprint $table) {
                $table->id();
                $table->string('account_number', 32)->unique();
                $table->string('account_name')->nullable();
                $table->string('bank_name')->nullable();
                $table->unsignedBigInteger('business_id')->nullable();
                $table->boolean('is_pool')->default(false);
                $table->boolean('is_external')->default(false);
                $table->boolean('is_active')->default(true);
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
                $table->timestamp('support_whatsapp_welcome_sent_at')->nullable();
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
                $table->string('intake_status', 32)->nullable();
                $table->string('reported_destination_account', 32)->nullable();
                $table->string('reported_destination_bank', 120)->nullable();
                $table->timestamp('whatsapp_eligible_at')->nullable();
                $table->string('payment_receipt_path')->nullable();
                $table->boolean('account_on_session')->default(false);
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

        if (! $schema->hasTable('whatsapp_wallet_pending_topups')) {
            $schema->create('whatsapp_wallet_pending_topups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->string('account_number', 32);
                $table->string('account_name')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('bank_code', 16)->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('fulfilled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('support_intake_sessions')) {
            $schema->create('support_intake_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('intake_token')->unique();
                $table->string('channel', 32);
                $table->string('intake_status', 32);
                $table->string('current_step', 64);
                $table->string('issue_type', 64)->nullable();
                $table->boolean('is_payment_issue')->nullable();
                $table->string('reported_destination_account', 32)->nullable();
                $table->string('reported_destination_bank', 120)->nullable();
                $table->string('payment_session_id', 64)->nullable();
                $table->decimal('payment_amount_reported', 14, 2)->nullable();
                $table->string('visitor_name', 120)->nullable();
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->boolean('account_on_session')->default(false);
                $table->boolean('account_in_platform')->default(false);
                $table->timestamp('whatsapp_eligible_at')->nullable();
                $table->string('payment_receipt_path')->nullable();
                $table->boolean('link_whatsapp_wallet')->default(false);
                $table->string('visitor_phone', 20)->nullable();
                $table->char('visitor_country', 2)->nullable();
                $table->unsignedBigInteger('whatsapp_wallet_id')->nullable();
                $table->unsignedBigInteger('consumer_wallet_api_account_id')->nullable();
                $table->unsignedBigInteger('support_ticket_id')->nullable();
                $table->uuid('public_token')->nullable();
                $table->json('bot_messages')->nullable();
                $table->timestamps();
            });
        }

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

    /** @test */
    public function intake_start_does_not_send_whatsapp(): void
    {
        $mock = Mockery::mock(EvolutionWhatsAppClient::class);
        $mock->shouldReceive('sendText')->never();
        $this->app->instance(EvolutionWhatsAppClient::class, $mock);

        $this->postJson('/api/v1/public/support/intake/start', [
            'channel' => 'checkout_web',
        ])->assertOk()
            ->assertJsonPath('data.current_step', 'payment_issue');
    }

    /** @test */
    public function intake_rejects_non_payment_issue_without_ticket(): void
    {
        $start = $this->postJson('/api/v1/public/support/intake/start', [
            'channel' => 'checkout_web',
        ])->assertOk();

        $token = (string) $start->json('data.intake_token');

        $this->postJson('/api/v1/public/support/intake/'.$token.'/advance', [
            'step' => 'payment_issue',
            'value' => false,
        ])->assertOk()
            ->assertJsonPath('data.intake_status', SupportIntakeSession::STATUS_REJECTED_NON_PAYMENT)
            ->assertJsonPath('data.is_terminal', true);

        $this->assertDatabaseCount('support_tickets', 0);
    }

    /** @test */
    public function intake_whatsapp_welcome_only_after_session_and_account_match(): void
    {
        $account = '0123456789';
        AccountNumber::create([
            'account_number' => $account,
            'account_name' => 'CHECKOUT NOW LTD',
            'bank_name' => 'Test Bank',
            'is_pool' => true,
            'is_active' => true,
        ]);

        $payment = Payment::create([
            'transaction_id' => 'TXN-WA-GATE-1',
            'amount' => 7500,
            'status' => Payment::STATUS_PENDING,
            'account_number' => $account,
            'expires_at' => now()->addHour(),
        ]);

        $mock = Mockery::mock(EvolutionWhatsAppClient::class);
        $mock->shouldReceive('sendText')->once()->andReturn(true);
        $this->app->instance(EvolutionWhatsAppClient::class, $mock);

        $intakeToken = (string) $this->postJson('/api/v1/public/support/intake/start', [
            'channel' => 'checkout_web',
        ])->json('data.intake_token');

        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'payment_issue',
            'value' => true,
        ])->assertOk();

        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'destination_account',
            'value' => $account,
        ])->assertOk();

        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'session_id',
            'value' => 'TXN-WA-GATE-1',
        ])->assertOk()
            ->assertJsonPath('data.whatsapp_eligible', true);

        $steps = [
            ['step' => 'name', 'value' => 'Test User'],
            ['step' => 'amount', 'value' => 7500],
            ['step' => 'bank_from', 'value' => 'GTBank'],
            ['step' => 'receipt', 'value' => 'skip'],
            ['step' => 'contact_mode', 'value' => 'whatsapp'],
            ['step' => 'phone', 'value' => ['phone' => '08033334444', 'country_iso' => 'NG']],
        ];

        foreach ($steps as $row) {
            $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', $row)->assertOk();
        }

        $ticket = SupportTicket::query()->where('payment_id', $payment->id)->first();
        $this->assertNotNull($ticket);
        $this->assertNotNull($ticket->whatsapp_eligible_at);
        $this->assertTrue($ticket->account_on_session);

        $wallet = WhatsappWallet::query()->where('phone_e164', '2348033334444')->first();
        $this->assertNotNull($wallet);
        $this->assertNotNull($wallet->support_whatsapp_welcome_sent_at);
    }

    /** @test */
    public function intake_session_account_mismatch_returns_error(): void
    {
        $account = '0123456789';
        $other = '0999888777';
        AccountNumber::create([
            'account_number' => $account,
            'account_name' => 'CHECKOUT NOW LTD',
            'is_pool' => true,
            'is_active' => true,
        ]);
        AccountNumber::create([
            'account_number' => $other,
            'account_name' => 'CHECKOUT NOW LTD',
            'is_pool' => true,
            'is_active' => true,
        ]);

        Payment::create([
            'transaction_id' => 'TXN-MISMATCH-1',
            'amount' => 5000,
            'status' => Payment::STATUS_PENDING,
            'account_number' => $account,
            'expires_at' => now()->addHour(),
        ]);

        $intakeToken = (string) $this->postJson('/api/v1/public/support/intake/start')->json('data.intake_token');

        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'payment_issue',
            'value' => true,
        ]);
        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'destination_account',
            'value' => $other,
        ]);

        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'session_id',
            'value' => 'TXN-MISMATCH-1',
        ])->assertStatus(422);
    }

    /** @test */
    public function intake_unknown_account_is_rejected(): void
    {
        $start = $this->postJson('/api/v1/public/support/intake/start', [
            'channel' => 'checkout_web',
        ])->assertOk();

        $intakeToken = (string) $start->json('data.intake_token');

        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'payment_issue',
            'value' => true,
        ])->assertOk();

        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'destination_account',
            'value' => '0000000000',
        ])->assertOk()
            ->assertJsonPath('data.intake_status', SupportIntakeSession::STATUS_REJECTED_NOT_OUR_ACCOUNT);
    }

    /** @test */
    public function whatsapp_welcome_is_sent_only_once_per_wallet_across_qualified_intakes(): void
    {
        $account = '0111222333';
        AccountNumber::create([
            'account_number' => $account,
            'account_name' => 'CHECKOUT NOW LTD',
            'is_pool' => true,
            'is_active' => true,
        ]);

        Payment::create([
            'transaction_id' => 'TXN-WA-ONCE',
            'amount' => 1000,
            'status' => Payment::STATUS_PENDING,
            'account_number' => $account,
            'expires_at' => now()->addHour(),
        ]);

        $mock = Mockery::mock(EvolutionWhatsAppClient::class);
        $mock->shouldReceive('sendText')->once()->andReturn(true);
        $this->app->instance(EvolutionWhatsAppClient::class, $mock);

        foreach ([1, 2] as $run) {
            $intakeToken = (string) $this->postJson('/api/v1/public/support/intake/start')->json('data.intake_token');
            $this->runQualifiedIntakeToWhatsapp($intakeToken, $account, 'TXN-WA-ONCE', '08044445555');
        }

        $this->assertSame(2, SupportTicket::query()->count());
        $wallet = WhatsappWallet::query()->where('phone_e164', '2348044445555')->first();
        $this->assertNotNull($wallet->support_whatsapp_welcome_sent_at);
    }

    /**
     * @return array{public_token: string}
     */
    private function runQualifiedIntakeToWhatsapp(string $intakeToken, string $account, string $sessionId, string $phone): array
    {
        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'payment_issue',
            'value' => true,
        ]);
        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'destination_account',
            'value' => $account,
        ]);
        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'session_id',
            'value' => $sessionId,
        ]);
        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'name',
            'value' => 'User',
        ]);
        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'amount',
            'value' => 1000,
        ]);
        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'bank_from',
            'value' => 'UBA',
        ]);
        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'receipt',
            'value' => 'skip',
        ]);
        $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'contact_mode',
            'value' => 'whatsapp',
        ]);
        $done = $this->postJson('/api/v1/public/support/intake/'.$intakeToken.'/advance', [
            'step' => 'phone',
            'value' => ['phone' => $phone, 'country_iso' => 'NG'],
        ])->assertOk();

        return ['public_token' => (string) $done->json('data.public_token')];
    }

    /** @test */
    public function visitor_can_poll_messages_without_hitting_rate_limit(): void
    {
        $start = $this->postJson('/api/v1/public/support/conversations', [
            'link_whatsapp_wallet' => false,
            'consent_accepted' => true,
            'channel' => 'checkout_web',
        ])->assertOk();

        $token = (string) $start->json('data.public_token');

        for ($i = 0; $i < 25; $i++) {
            $this->getJson('/api/v1/public/support/conversations/'.$token.'/messages?after_id=0')
                ->assertOk();
        }
    }
}
