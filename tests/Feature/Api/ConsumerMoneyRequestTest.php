<?php

namespace Tests\Feature\Api;

use App\Models\ConsumerWalletApiAccount;
use App\Models\Setting;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletMoneyRequest;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerMoneyRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
        config([
            'consumer_wallet.money_request_enabled' => true,
            'whatsapp.evolution.instance' => 'test-instance',
            'whatsapp.evolution.wallet_instance' => 'test-instance',
        ]);
        Setting::set('savings_enabled', false, 'boolean', 'savings');
        $this->mock(\App\Services\Whatsapp\EvolutionWhatsAppClient::class, function ($mock) {
            $mock->shouldReceive('sendText')->andReturn(true);
        });
    }

    public function test_create_money_request_returns_sufficiency_hint_when_payer_balance_low(): void
    {
        [$requesterWallet, $requesterAccount] = $this->seedWallet('2348011111111', 1000, '1111');
        $this->seedWallet('2348022222222', 100, '2222', senderName: 'Ada Okoro');

        Sanctum::actingAs($requesterAccount, ['consumer']);

        $response = $this->postJson('/api/v1/consumer/money-requests', [
            'to_phone' => '+2348022222222',
            'amount' => 5000,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['message', 'data' => ['id', 'status', 'amount']]);

        $this->assertStringContainsString('Ada Okoro', (string) $response->json('message'));
        $this->assertStringContainsString('sent your request', (string) $response->json('message'));
    }

    public function test_create_respects_payer_privacy_opt_out(): void
    {
        [$requesterWallet, $requesterAccount] = $this->seedWallet('2348011111111', 1000, '1111');
        [, , $payerWallet] = $this->seedWallet('2348022222222', 100, '2222', senderName: 'Ada Okoro');
        $payerWallet->money_request_balance_hint_enabled = false;
        $payerWallet->save();

        Sanctum::actingAs($requesterAccount, ['consumer']);

        $response = $this->postJson('/api/v1/consumer/money-requests', [
            'to_phone' => '+2348022222222',
            'amount' => 5000,
        ]);

        $response->assertOk();
        $message = (string) $response->json('message');
        $this->assertStringContainsString('Request sent', $message);
        $this->assertStringNotContainsString("doesn't have", $message);
    }

    public function test_payer_can_accept_with_pin(): void
    {
        [$requesterWallet, $requesterAccount] = $this->seedWallet('2348011111111', 1000, '1111');
        [$payerWallet, $payerAccount] = $this->seedWallet('2348022222222', 8000, '2222');

        Sanctum::actingAs($requesterAccount, ['consumer']);
        $create = $this->postJson('/api/v1/consumer/money-requests', [
            'to_phone' => '+2348022222222',
            'amount' => 500,
        ])->assertOk();

        $id = (string) $create->json('data.id');

        Sanctum::actingAs($payerAccount, ['consumer']);
        $this->postJson('/api/v1/consumer/money-requests/'.$id.'/accept', [
            'pin' => '2222',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $requesterWallet->refresh();
        $payerWallet->refresh();
        $this->assertSame(1500.0, (float) $requesterWallet->balance);
        $this->assertSame(7500.0, (float) $payerWallet->balance);

        $this->assertSame(
            WhatsappWalletMoneyRequest::STATUS_ACCEPTED,
            WhatsappWalletMoneyRequest::query()->where('public_id', $id)->value('status'),
        );
    }

    public function test_requester_can_cancel_pending_request(): void
    {
        [, $requesterAccount] = $this->seedWallet('2348011111111', 1000, '1111');
        $this->seedWallet('2348022222222', 8000, '2222');

        Sanctum::actingAs($requesterAccount, ['consumer']);
        $id = (string) $this->postJson('/api/v1/consumer/money-requests', [
            'to_phone' => '+2348022222222',
            'amount' => 500,
        ])->json('data.id');

        $this->deleteJson('/api/v1/consumer/money-requests/'.$id)
            ->assertOk()
            ->assertJsonPath('data.status', WhatsappWalletMoneyRequest::STATUS_CANCELLED);
    }

    public function test_create_rejected_when_payer_paused_requests(): void
    {
        [, $requesterAccount] = $this->seedWallet('2348011111111', 1000, '1111');
        [, , $payerWallet] = $this->seedWallet('2348022222222', 5000, '2222');
        $payerWallet->money_request_paused_until = now()->addDay();
        $payerWallet->save();

        Sanctum::actingAs($requesterAccount, ['consumer']);

        $this->postJson('/api/v1/consumer/money-requests', [
            'to_phone' => '+2348022222222',
            'amount' => 500,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_create_rejected_when_payer_blocked_requester(): void
    {
        [, $requesterAccount] = $this->seedWallet('2348011111111', 1000, '1111');
        [, $payerAccount] = $this->seedWallet('2348022222222', 5000, '2222');

        Sanctum::actingAs($payerAccount, ['consumer']);
        $this->postJson('/api/v1/consumer/wallet/money-request-blocks', [
            'phone' => '+2348011111111',
        ])->assertOk();

        Sanctum::actingAs($requesterAccount, ['consumer']);
        $this->postJson('/api/v1/consumer/money-requests', [
            'to_phone' => '+2348022222222',
            'amount' => 200,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_payer_can_pause_and_resume_requests(): void
    {
        [, $payerAccount, $payerWallet] = $this->seedWallet('2348022222222', 5000, '2222');

        Sanctum::actingAs($payerAccount, ['consumer']);
        $this->patchJson('/api/v1/consumer/wallet/money-request-settings', [
            'money_request_pause_hours' => 24,
        ])
            ->assertOk()
            ->assertJsonPath('data.money_request_paused', true);

        $payerWallet->refresh();
        $this->assertTrue($payerWallet->isMoneyRequestPaused());

        $this->patchJson('/api/v1/consumer/wallet/money-request-settings', [
            'money_request_pause_hours' => 0,
        ])
            ->assertOk()
            ->assertJsonPath('data.money_request_paused', false);
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
                $table->string('sender_name', 128)->nullable();
                $table->string('pin_hash')->nullable();
                $table->unsignedTinyInteger('tier')->default(1);
                $table->decimal('balance', 14, 2)->default(0);
                $table->decimal('daily_transfer_total', 14, 2)->default(0);
                $table->date('daily_transfer_for_date')->nullable();
                $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
                $table->timestamp('pin_locked_until')->nullable();
                $table->boolean('transfer_email_otp_enabled')->default(false);
                $table->boolean('money_request_balance_hint_enabled')->default(true);
                $table->timestamp('money_request_paused_until')->nullable();
                $table->string('status', 32)->default('active');
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('whatsapp_wallet_money_request_blocks')) {
            $schema->create('whatsapp_wallet_money_request_blocks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('blocked_phone_e164', 32);
                $table->unsignedBigInteger('blocked_wallet_id')->nullable();
                $table->string('blocked_display_name', 128)->nullable();
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

        if (! $schema->hasTable('whatsapp_wallet_money_requests')) {
            $schema->create('whatsapp_wallet_money_requests', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('requester_wallet_id');
                $table->string('requester_phone_e164', 32);
                $table->string('payer_phone_e164', 32);
                $table->unsignedBigInteger('payer_wallet_id')->nullable();
                $table->decimal('amount', 14, 2);
                $table->string('currency', 8)->default('NGN');
                $table->string('note', 140)->nullable();
                $table->string('status', 24)->default('pending');
                $table->string('channel', 24)->default('consumer_api');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->unsignedBigInteger('p2p_debit_transaction_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('whatsapp_wallet_transactions')) {
            $schema->create('whatsapp_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('sender_name', 128)->nullable();
                $table->string('type', 32);
                $table->string('ledger_scope', 16)->default('personal');
                $table->decimal('amount', 14, 2);
                $table->decimal('balance_after', 14, 2)->nullable();
                $table->string('counterparty_phone_e164', 32)->nullable();
                $table->string('counterparty_account_name', 128)->nullable();
                $table->json('meta')->nullable();
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

        if (! $schema->hasTable('consumer_app_sessions')) {
            $schema->create('consumer_app_sessions', function (Blueprint $table) {
                $table->id();
                $table->uuid('session_uuid')->unique();
                $table->unsignedBigInteger('consumer_wallet_api_account_id')->nullable();
                $table->unsignedBigInteger('whatsapp_wallet_id')->nullable();
                $table->string('phone_e164', 20)->nullable();
                $table->string('login_method', 32);
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('settings')) {
            $schema->create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('type', 32)->default('string');
                $table->string('group', 64)->default('general');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount, 2?: WhatsappWallet}
     */
    private function seedWallet(string $e164, float $balance, string $pin, ?string $senderName = null): array
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => $e164,
            'sender_name' => $senderName,
            'pin_hash' => Hash::make($pin),
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => $balance,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'money_request_balance_hint_enabled' => true,
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $e164,
        ]);

        return [$wallet, $account, $wallet];
    }
}
