<?php

namespace Tests\Feature\Api;

use App\Models\ConsumerPasskeyCredential;
use App\Models\ConsumerTrustedDevice;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerDeviceTrustTest extends TestCase
{
    private const PHONE = '+2348012345678';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSchema();

        config([
            'consumer_wallet.device_trust_enabled' => true,
            'consumer_wallet.high_value_single_transfer_cap' => 10000,
            'consumer_wallet.transfer_lock_hours' => 24,
        ]);
    }

    public function test_pin_verify_returns_stepup_when_passkey_device_exists(): void
    {
        [$wallet, $account] = $this->seedWalletWithPasskeyDevice();

        $response = $this->postJson('/api/v1/consumer/auth/pin/verify', [
            'phone' => self::PHONE,
            'pin' => '1234',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.stepup_required', true)
            ->assertJsonPath('data.other_device_label', 'Existing iPhone')
            ->assertJsonPath('message', 'Verify this device to continue');

        $this->assertNotEmpty($response->json('data.stepup_session'));
        $this->assertContains('whatsapp', $response->json('data.channels'));
    }

    public function test_p2p_transfer_blocked_above_cap_during_lock(): void
    {
        [$wallet, $account] = $this->seedWalletWithPasskeyDevice();
        $account->transfer_lock_until = now()->addHours(12);
        $account->save();

        Sanctum::actingAs($account, ['consumer']);

        $response = $this->postJson('/api/v1/consumer/transfers/p2p', [
            'pin' => '1234',
            'to_phone' => '+2348098765432',
            'amount' => 10001,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.high_value_single_transfer_cap', 10000);
    }

    public function test_p2p_transfer_allowed_at_cap_during_lock(): void
    {
        [$wallet, $account] = $this->seedWalletWithPasskeyDevice();
        $account->transfer_lock_until = now()->addHours(12);
        $account->save();

        Sanctum::actingAs($account, ['consumer']);

        $response = $this->postJson('/api/v1/consumer/transfers/p2p', [
            'pin' => '1234',
            'to_phone' => '+2348098765432',
            'amount' => 10000,
        ]);

        $this->assertNotEquals(403, $response->status());
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
                $table->timestamp('pin_set_at')->nullable();
                $table->unsignedInteger('pin_failed_attempts')->default(0);
                $table->timestamp('pin_locked_until')->nullable();
                $table->string('kyc_bvn', 16)->nullable();
                $table->string('kyc_fname', 128)->nullable();
                $table->string('kyc_lname', 128)->nullable();
                $table->string('sender_name', 128)->nullable();
                $table->unsignedTinyInteger('tier')->default(1);
                $table->decimal('balance', 14, 2)->default(0);
                $table->string('status', 32)->default('active');
                $table->decimal('savings_balance', 14, 2)->default(0);
                $table->boolean('transfer_email_otp_enabled')->default(false);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_wallet_api_accounts')) {
            $schema->create('consumer_wallet_api_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('phone_e164', 32)->unique();
                $table->string('fcm_token')->nullable();
                $table->string('fcm_platform', 32)->nullable();
                $table->timestamp('fcm_token_updated_at')->nullable();
                $table->timestamp('last_app_active_at')->nullable();
                $table->timestamp('transfer_lock_until')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_trusted_devices')) {
            $schema->create('consumer_trusted_devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consumer_wallet_api_account_id');
                $table->string('label', 120)->nullable();
                $table->string('platform', 32)->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_passkey_credentials')) {
            $schema->create('consumer_passkey_credentials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consumer_trusted_device_id');
                $table->string('credential_id', 512)->unique();
                $table->json('credential_record');
                $table->unsignedBigInteger('counter')->default(0);
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_device_stepup_sessions')) {
            $schema->create('consumer_device_stepup_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('session_token', 64)->unique();
                $table->unsignedBigInteger('consumer_wallet_api_account_id');
                $table->string('phone_e164', 20);
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->timestamp('auth_verified_at')->nullable();
                $table->timestamp('bvn_verified_at')->nullable();
                $table->timestamp('otp_verified_at')->nullable();
                $table->string('stepup_token', 64)->nullable()->unique();
                $table->timestamp('stepup_token_expires_at')->nullable();
                $table->timestamp('expires_at');
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
    }

    /**
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount}
     */
    private function seedWalletWithPasskeyDevice(): array
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits(self::PHONE);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => $e164,
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 50000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'pin_hash' => Hash::make('1234'),
            'pin_set_at' => now(),
            'kyc_bvn' => '22222222222',
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $e164,
        ]);

        $device = ConsumerTrustedDevice::query()->create([
            'consumer_wallet_api_account_id' => $account->id,
            'label' => 'Existing iPhone',
            'platform' => 'ios',
            'last_active_at' => now()->subDay(),
        ]);

        ConsumerPasskeyCredential::query()->create([
            'consumer_trusted_device_id' => $device->id,
            'credential_id' => base64_encode('test-credential-id'),
            'credential_record' => [
                'publicKeyCredentialId' => base64_encode('test-credential-id'),
                'type' => 'public-key',
                'transports' => [],
                'attestationType' => 'none',
                'trustPath' => ['type' => 'Webauthn\\TrustPath\\EmptyTrustPath'],
                'aaguid' => '00000000-0000-0000-0000-000000000000',
                'credentialPublicKey' => base64_encode('fake-key'),
                'userHandle' => base64_encode('user'),
                'counter' => 0,
            ],
            'counter' => 0,
        ]);

        return [$wallet, $account];
    }
}
