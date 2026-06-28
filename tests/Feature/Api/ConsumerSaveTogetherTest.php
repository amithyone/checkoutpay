<?php

namespace Tests\Feature\Api;

use App\Models\ConsumerWalletApiAccount;
use App\Models\Setting;
use App\Models\WalletSaveTogetherMember;
use App\Models\WalletSaveTogetherPot;
use App\Models\WhatsappWallet;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerSaveTogetherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
        config([
            'consumer_wallet.save_together_enabled' => true,
            'consumer_wallet.credit_push_enabled' => false,
            'whatsapp.evolution.instance' => 'test-instance',
            'whatsapp.evolution.wallet_instance' => 'test-instance',
        ]);
        Setting::set('savings_enabled', false, 'boolean', 'savings');
        $this->mock(\App\Services\Whatsapp\EvolutionWhatsAppClient::class, function ($mock) {
            $mock->shouldReceive('sendText')->andReturn(true);
        });
    }

    public function test_create_pot_and_partial_contributions_until_share_complete(): void
    {
        [$creatorWallet, $creatorAccount] = $this->seedWallet('2348011111111', 5000, '1111', 'Creator');
        [$memberWallet, $memberAccount] = $this->seedWallet('2348022222222', 5000, '2222');

        Sanctum::actingAs($creatorAccount, ['consumer']);
        $create = $this->postJson('/api/v1/consumer/save-together', [
            'title' => 'Trip fund',
            'target_amount' => 1000,
            'member_phones' => ['+2348022222222'],
            'completion_mode' => 'full_contribution',
        ])->assertOk();

        $potId = (string) $create->json('data.id');
        $share = (float) $create->json('data.per_member_share');
        $this->assertSame(500.0, $share);

        Sanctum::actingAs($creatorAccount, ['consumer']);
        $this->postJson("/api/v1/consumer/save-together/{$potId}/contribute", [
            'amount' => 200,
            'pin' => '1111',
        ])->assertOk();

        $this->postJson("/api/v1/consumer/save-together/{$potId}/contribute", [
            'amount' => 300,
            'pin' => '1111',
        ])->assertOk()
            ->assertJsonPath('data.my_status', WalletSaveTogetherMember::STATUS_COMPLETED_SHARE);

        Sanctum::actingAs($memberAccount, ['consumer']);
        $this->postJson("/api/v1/consumer/save-together/{$potId}/contribute", [
            'amount' => 500,
            'pin' => '2222',
        ])->assertOk();

        $pot = WalletSaveTogetherPot::query()->where('public_id', $potId)->first();
        $this->assertSame(WalletSaveTogetherPot::STATUS_UNLOCKED, $pot->status);
    }

    public function test_reject_overpay_contribution(): void
    {
        [, $creatorAccount] = $this->seedWallet('2348011111111', 5000, '1111');
        $this->seedWallet('2348022222222', 5000, '2222');

        Sanctum::actingAs($creatorAccount, ['consumer']);
        $potId = (string) $this->postJson('/api/v1/consumer/save-together', [
            'title' => 'Test',
            'target_amount' => 1000,
            'member_phones' => ['+2348022222222'],
            'completion_mode' => 'full_contribution',
        ])->json('data.id');

        $this->postJson("/api/v1/consumer/save-together/{$potId}/contribute", [
            'amount' => 600,
            'pin' => '1111',
        ])->assertStatus(422);
    }

    public function test_withdraw_after_unlock(): void
    {
        [$creatorWallet, $creatorAccount] = $this->seedWallet('2348011111111', 5000, '1111');
        [, $memberAccount] = $this->seedWallet('2348022222222', 5000, '2222');

        Sanctum::actingAs($creatorAccount, ['consumer']);
        $potId = (string) $this->postJson('/api/v1/consumer/save-together', [
            'title' => 'Test',
            'target_amount' => 1000,
            'member_phones' => ['+2348022222222'],
            'completion_mode' => 'full_contribution',
        ])->json('data.id');

        $share = 500.0;
        $this->postJson("/api/v1/consumer/save-together/{$potId}/contribute", ['amount' => $share, 'pin' => '1111'])->assertOk();
        Sanctum::actingAs($memberAccount, ['consumer']);
        $this->postJson("/api/v1/consumer/save-together/{$potId}/contribute", ['amount' => $share, 'pin' => '2222'])->assertOk();

        Sanctum::actingAs($creatorAccount, ['consumer']);
        $before = (float) $creatorWallet->fresh()->balance;
        $this->postJson("/api/v1/consumer/save-together/{$potId}/withdraw", ['pin' => '1111'])
            ->assertOk();

        $this->assertSame($before + $share, (float) $creatorWallet->fresh()->balance);
    }

    public function test_decline_before_contributing(): void
    {
        [, $creatorAccount] = $this->seedWallet('2348011111111', 5000, '1111');
        [, $memberAccount] = $this->seedWallet('2348022222222', 5000, '2222');

        Sanctum::actingAs($creatorAccount, ['consumer']);
        $potId = (string) $this->postJson('/api/v1/consumer/save-together', [
            'title' => 'Test',
            'target_amount' => 1000,
            'member_phones' => ['+2348022222222'],
            'completion_mode' => 'full_contribution',
        ])->json('data.id');

        Sanctum::actingAs($memberAccount, ['consumer']);
        $this->postJson("/api/v1/consumer/save-together/{$potId}/decline")
            ->assertOk()
            ->assertJsonPath('data.members.1.status', WalletSaveTogetherMember::STATUS_DECLINED);
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

        if (! $schema->hasTable('wallet_save_together_pots')) {
            $schema->create('wallet_save_together_pots', function (Blueprint $table) {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->unsignedBigInteger('creator_wallet_id');
                $table->string('title', 120);
                $table->decimal('target_amount', 14, 2);
                $table->decimal('per_member_share', 14, 2);
                $table->decimal('total_contributed', 14, 2)->default(0);
                $table->string('completion_mode', 32);
                $table->timestamp('deadline_at')->nullable();
                $table->string('status', 24)->default('collecting');
                $table->timestamp('unlocked_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->string('currency', 8)->default('NGN');
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('wallet_save_together_members')) {
            $schema->create('wallet_save_together_members', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pot_id');
                $table->unsignedBigInteger('wallet_id')->nullable();
                $table->string('phone_e164', 32);
                $table->string('display_name', 128)->nullable();
                $table->string('role', 16)->default('member');
                $table->decimal('share_target', 14, 2);
                $table->decimal('contributed_amount', 14, 2)->default(0);
                $table->decimal('withdrawn_amount', 14, 2)->default(0);
                $table->string('status', 24)->default('invited');
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('first_contributed_at')->nullable();
                $table->timestamp('share_completed_at')->nullable();
                $table->timestamp('withdrawn_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('wallet_save_together_contributions')) {
            $schema->create('wallet_save_together_contributions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pot_id');
                $table->unsignedBigInteger('member_id');
                $table->decimal('amount', 14, 2);
                $table->string('kind', 16);
                $table->unsignedBigInteger('whatsapp_wallet_transaction_id')->nullable();
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
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount}
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
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $e164,
        ]);

        return [$wallet, $account];
    }
}
