<?php

namespace Tests\Feature\Api;

use App\Models\ConsumerDeviceLoginApproval;
use App\Models\ConsumerDeviceStepupSession;
use App\Models\ConsumerPasskeyCredential;
use App\Models\ConsumerTrustedDevice;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\PushNotificationService;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ConsumerDeviceStepupPushTest extends TestCase
{
    private const PHONE = '+2348012345678';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSchema();

        config([
            'consumer_wallet.device_trust_enabled' => true,
            'consumer_wallet.device_stepup_push_enabled' => true,
            'services.firebase.checkoutnow.project_id' => 'test-project',
            'services.firebase.checkoutnow.service_account_json' => '{"client_email":"x@y.z","private_key":"x"}',
        ]);
    }

    public function test_stepup_start_includes_push_approval_when_fcm_token_present(): void
    {
        [$wallet, $account] = $this->seedWalletWithPasskeyDevice();
        $account->fcm_token = 'trusted-device-fcm-token';
        $account->fcm_platform = 'ios';
        $account->save();

        $response = $this->postJson('/api/v1/consumer/auth/device/stepup/start', [
            'phone' => self::PHONE,
            'pin' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.push_approval_available', true)
            ->assertJsonPath('data.stepup_required', true);
    }

    public function test_push_request_and_approve_issues_stepup_token(): void
    {
        [$wallet, $account, $session] = $this->seedStepupSession();

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('isConfigured')->andReturn(true);
        $push->shouldReceive('sendToTokens')->once()->andReturn([]);
        $this->app->instance(PushNotificationService::class, $push);

        $request = $this->postJson('/api/v1/consumer/auth/device/stepup/push/request', [
            'stepup_session' => $session->session_token,
        ]);
        $request->assertOk()
            ->assertJsonPath('data.sent', true);

        $approvalId = (string) $request->json('data.approval_id');
        $this->assertNotEmpty($approvalId);

        Sanctum::actingAs($account, ['consumer']);

        $this->postJson('/api/v1/consumer/auth/device/stepup/push/approve', [
            'approval_id' => $approvalId,
            'pin' => '1234',
        ])->assertOk()
            ->assertJsonPath('data.ok', true);

        $status = $this->getJson('/api/v1/consumer/auth/device/stepup/push/status?stepup_session='.$session->session_token);
        $status->assertOk()
            ->assertJsonPath('data.status', ConsumerDeviceLoginApproval::STATUS_APPROVED);

        $this->assertNotEmpty($status->json('data.stepup_token'));
    }

    public function test_push_deny_returns_denied_status(): void
    {
        [$wallet, $account, $session] = $this->seedStepupSession();

        $push = Mockery::mock(PushNotificationService::class);
        $push->shouldReceive('isConfigured')->andReturn(true);
        $push->shouldReceive('sendToTokens')->once()->andReturn([]);
        $this->app->instance(PushNotificationService::class, $push);

        $approvalId = (string) $this->postJson('/api/v1/consumer/auth/device/stepup/push/request', [
            'stepup_session' => $session->session_token,
        ])->json('data.approval_id');

        Sanctum::actingAs($account, ['consumer']);

        $this->postJson('/api/v1/consumer/auth/device/stepup/push/deny', [
            'approval_id' => $approvalId,
        ])->assertOk();

        $this->getJson('/api/v1/consumer/auth/device/stepup/push/status?stepup_session='.$session->session_token)
            ->assertOk()
            ->assertJsonPath('data.status', ConsumerDeviceLoginApproval::STATUS_DENIED);
    }

    /**
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount, 2: ConsumerDeviceStepupSession}
     */
    private function seedStepupSession(): array
    {
        [$wallet, $account] = $this->seedWalletWithPasskeyDevice();
        $account->fcm_token = 'trusted-device-fcm-token';
        $account->fcm_platform = 'android';
        $account->save();

        $session = ConsumerDeviceStepupSession::query()->create([
            'session_token' => 'sess_test_push_approval',
            'consumer_wallet_api_account_id' => $account->id,
            'phone_e164' => PhoneNormalizer::canonicalNgE164Digits(self::PHONE),
            'whatsapp_wallet_id' => $wallet->id,
            'auth_verified_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        return [$wallet, $account, $session];
    }

    /**
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount}
     */
    private function seedWalletWithPasskeyDevice(): array
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => PhoneNormalizer::canonicalNgE164Digits(self::PHONE),
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'balance' => 10000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'pin_hash' => Hash::make('1234'),
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => PhoneNormalizer::canonicalNgE164Digits(self::PHONE),
        ]);

        $device = ConsumerTrustedDevice::query()->create([
            'consumer_wallet_api_account_id' => $account->id,
            'label' => 'Existing iPhone',
            'platform' => 'ios',
            'last_active_at' => now(),
        ]);

        ConsumerPasskeyCredential::query()->create([
            'consumer_trusted_device_id' => $device->id,
            'credential_id' => 'cred-existing-device',
            'credential_record' => ['id' => 'cred-existing-device'],
            'counter' => 0,
        ]);

        return [$wallet, $account];
    }

    private function ensureSchema(): void
    {
        $schema = Schema::connection(config('database.default'));

        if (! $schema->hasTable('whatsapp_wallets')) {
            $schema->create('whatsapp_wallets', function (Blueprint $table) {
                $table->id();
                $table->string('phone_e164', 20)->unique();
                $table->string('tier', 32)->default('whatsapp_only');
                $table->decimal('balance', 14, 2)->default(0);
                $table->string('status', 32)->default('active');
                $table->string('pin_hash')->nullable();
                $table->unsignedTinyInteger('pin_failed_attempts')->default(0);
                $table->timestamp('pin_locked_until')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_wallet_api_accounts')) {
            $schema->create('consumer_wallet_api_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('phone_e164', 20)->unique();
                $table->string('fcm_token')->nullable();
                $table->string('fcm_platform', 32)->nullable();
                $table->timestamp('fcm_token_updated_at')->nullable();
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

        if (! $schema->hasTable('consumer_device_login_approvals')) {
            $schema->create('consumer_device_login_approvals', function (Blueprint $table) {
                $table->id();
                $table->string('approval_id', 64)->unique();
                $table->unsignedBigInteger('consumer_device_stepup_session_id');
                $table->unsignedBigInteger('consumer_wallet_api_account_id');
                $table->string('status', 16)->default('pending');
                $table->timestamp('expires_at');
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }
    }
}
