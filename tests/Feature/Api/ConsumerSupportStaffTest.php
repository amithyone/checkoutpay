<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\ConsumerWalletApiAccount;
use App\Models\SupportTicket;
use App\Models\WhatsappWallet;
use App\Http\Middleware\TouchConsumerAppSession;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ConsumerSupportStaffTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(TouchConsumerAppSession::class);
        $this->ensureSchema();

        $mock = Mockery::mock(EvolutionWhatsAppClient::class);
        $mock->shouldReceive('sendText')->andReturn(null);
        $this->app->instance(EvolutionWhatsAppClient::class, $mock);
    }

    private function ensureSchema(): void
    {
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');

        if (! $schema->hasTable('whatsapp_wallets')) {
            $schema->create('whatsapp_wallets', function (Blueprint $table) {
                $table->id();
                $table->string('phone_e164', 32)->unique();
                $table->string('sender_name')->nullable();
                $table->string('status', 32)->default('active');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_wallet_api_accounts')) {
            $schema->create('consumer_wallet_api_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('phone_e164', 32)->unique();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('personal_access_tokens')) {
            $schema->create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('admins')) {
            $schema->create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('role')->default('admin');
                $table->boolean('is_active')->default(true);
                $table->string('whatsapp_e164', 32)->nullable();
                $table->boolean('notify_wallet_signup')->default(false);
                $table->boolean('handles_wallet_support_in_app')->default(true);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('support_tickets')) {
            $schema->create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 32)->default('business_dashboard');
                $table->string('issue_type', 64)->nullable();
                $table->string('support_queue', 20)->nullable();
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
                $table->string('intake_status')->nullable();
                $table->timestamp('whatsapp_eligible_at')->nullable();
                $table->boolean('account_on_session')->default(false);
                $table->string('reported_destination_account')->nullable();
                $table->string('reported_destination_bank')->nullable();
                $table->string('payment_receipt_path')->nullable();
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

        if (! $schema->hasTable('support_ticket_replies')) {
            $schema->create('support_ticket_replies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_type');
                $table->text('message');
                $table->boolean('is_internal_note')->default(false);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('settings')) {
            $schema->create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type')->default('string');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_app_sessions')) {
            $schema->create('consumer_app_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('session_uuid')->unique();
                $table->unsignedBigInteger('consumer_wallet_api_account_id')->nullable();
                $table->unsignedBigInteger('whatsapp_wallet_id')->nullable();
                $table->string('phone_e164', 20)->nullable();
                $table->string('login_method', 32);
                $table->string('platform', 16)->nullable();
                $table->string('app_version', 64)->nullable();
                $table->string('device_label', 160)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->unsignedBigInteger('personal_access_token_id')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_app_session_events')) {
            $schema->create('consumer_app_session_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consumer_app_session_id')->nullable();
                $table->unsignedBigInteger('consumer_wallet_api_account_id')->nullable();
                $table->unsignedBigInteger('whatsapp_wallet_id')->nullable();
                $table->string('phone_e164', 20)->nullable();
                $table->string('event_type', 32);
                $table->string('summary', 255)->nullable();
                $table->json('meta')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();
            });
        }

        config([
            'whatsapp.evolution.instance' => 'test',
            'consumer_wallet.credit_push_enabled' => false,
        ]);
    }

    public function test_customer_can_start_wallet_support_without_payment_fields(): void
    {
        [$account] = $this->customerAccount('2348012345678');

        Sanctum::actingAs($account);

        $this->postJson('/api/v1/consumer/support/conversations', [
            'link_whatsapp_wallet' => true,
            'issue_type' => 'wallet_transfer',
            'first_message' => 'My transfer failed',
            'consent_accepted' => true,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('support_tickets', [
            'channel' => SupportTicket::CHANNEL_CHECKOUTNOW_APP,
            'issue_type' => 'wallet_transfer',
            'support_queue' => SupportTicket::QUEUE_WALLET,
            'visitor_phone' => '2348012345678',
        ]);
    }

    public function test_staff_phone_cannot_start_customer_ticket(): void
    {
        [$account] = $this->staffAccount('2348099988776');

        Sanctum::actingAs($account);

        $this->postJson('/api/v1/consumer/support/conversations', [
            'link_whatsapp_wallet' => true,
            'issue_type' => 'wallet_transfer',
            'consent_accepted' => true,
        ])
            ->assertStatus(403)
            ->assertJsonPath('mode', 'staff');
    }

    public function test_staff_sees_inbox_and_can_reply(): void
    {
        [$customerAccount, $customerWallet] = $this->customerAccount('2348010101010');
        [$staffAccount, , $staffAdmin] = $this->staffAccount('2348020202020');

        Sanctum::actingAs($customerAccount);
        $start = $this->postJson('/api/v1/consumer/support/conversations', [
            'link_whatsapp_wallet' => true,
            'issue_type' => 'wallet_balance',
            'first_message' => 'Balance wrong',
            'consent_accepted' => true,
        ])->assertOk()->json('data');

        Sanctum::actingAs($staffAccount);

        $this->getJson('/api/v1/consumer/support/context')
            ->assertOk()
            ->assertJsonPath('data.mode', 'staff');

        $inbox = $this->getJson('/api/v1/consumer/support/staff/inbox')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.tickets');

        $this->assertNotEmpty($inbox);

        $ticketId = (int) $inbox[0]['id'];

        $this->postJson("/api/v1/consumer/support/staff/tickets/{$ticketId}/reply", [
            'message' => 'We are checking your balance now.',
        ])->assertOk();

        Sanctum::actingAs($customerAccount);

        $messages = $this->getJson('/api/v1/consumer/support/conversations/'.$start['public_token'].'/messages')
            ->assertOk()
            ->json('data.messages');

        $adminMessages = array_filter($messages, fn (array $row) => ($row['user_type'] ?? '') === 'admin');
        $this->assertNotEmpty($adminMessages);
    }

    /**
     * @return array{0: ConsumerWalletApiAccount, 1: WhatsappWallet}
     */
    private function customerAccount(string $phone): array
    {
        $wallet = WhatsappWallet::create([
            'phone_e164' => $phone,
            'sender_name' => 'Customer',
            'status' => 'active',
        ]);

        $account = ConsumerWalletApiAccount::create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $phone,
        ]);

        return [$account, $wallet];
    }

    /**
     * @return array{0: ConsumerWalletApiAccount, 1: WhatsappWallet, 2: Admin}
     */
    private function staffAccount(string $phone): array
    {
        [$account, $wallet] = $this->customerAccount($phone);

        $admin = Admin::create([
            'name' => 'Staff',
            'email' => $phone.'@example.com',
            'password' => Hash::make('secret'),
            'role' => Admin::ROLE_WALLET_SUPPORT,
            'is_active' => true,
            'whatsapp_e164' => $phone,
            'handles_wallet_support_in_app' => true,
        ]);

        return [$account, $wallet, $admin];
    }
}
