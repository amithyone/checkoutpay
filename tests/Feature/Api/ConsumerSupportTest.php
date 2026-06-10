<?php

namespace Tests\Feature\Api;

use App\Models\ConsumerWalletApiAccount;
use App\Models\SupportTicket;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ConsumerSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSchema();

        $mock = Mockery::mock(EvolutionWhatsAppClient::class);
        $mock->shouldReceive('sendText')->never();
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
                $table->string('pay_code', 32)->nullable();
                $table->string('pin_hash')->nullable();
                $table->unsignedBigInteger('renter_id')->nullable();
                $table->unsignedTinyInteger('tier')->default(1);
                $table->decimal('balance', 14, 2)->default(0);
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
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
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

        config([
            'whatsapp.evolution.instance' => 'test',
            'whatsapp.evolution.base_url' => 'http://localhost',
            'whatsapp.evolution.api_key' => 'test-key',
        ]);
    }

    /** @test */
    public function logged_in_consumer_starts_support_without_phone_fields(): void
    {
        $wallet = WhatsappWallet::create([
            'phone_e164' => '2348012345678',
            'status' => 'active',
        ]);

        $account = ConsumerWalletApiAccount::create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => '2348012345678',
        ]);

        Sanctum::actingAs($account);

        $this->postJson('/api/v1/consumer/support/conversations', [
            'link_whatsapp_wallet' => true,
            'issue_type' => 'general',
            'consent_accepted' => true,
        ])
            ->dump()
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wallet_linked', true)
            ->assertJsonPath('data.wallet_id', $wallet->id);

        $this->assertDatabaseHas('support_tickets', [
            'channel' => SupportTicket::CHANNEL_CHECKOUTNOW_APP,
            'whatsapp_wallet_id' => $wallet->id,
            'wallet_linked' => 1,
            'visitor_phone' => '2348012345678',
            'visitor_name' => null,
        ]);
    }
}
