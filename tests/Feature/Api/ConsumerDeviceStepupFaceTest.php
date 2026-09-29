<?php

namespace Tests\Feature\Api;

use App\Models\ConsumerDeviceStepupSession;
use App\Models\ConsumerTrustedDevice;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\WalletFaceCheckService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConsumerDeviceStepupFaceTest extends TestCase
{
    private const PHONE = '2348012345678';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureSchema();
        config([
            'consumer_wallet.device_trust_enabled' => true,
            'consumer_wallet.device_stepup_required_on_login' => true,
            'consumer_wallet.device_first_trust_email_otp' => true,
            'checkout.quarantine.enabled' => false,
        ]);
    }

    public function test_pin_mismatch_includes_face_available(): void
    {
        [$wallet] = $this->seedTrustedWallet();
        $wallet->forceFill(['face_enrolled_at' => now()])->save();

        $this->mock(WalletFaceCheckService::class, function ($face) {
            $face->shouldReceive('isAvailableForStepUp')->andReturn(true);
        });

        $this->postJson('/api/v1/consumer/auth/pin/verify', [
            'phone' => self::PHONE,
            'pin' => '1234',
            'device_id' => 'cn_other_install',
        ], [
            'X-Device-Id' => 'cn_other_install',
        ])->assertStatus(403)
            ->assertJsonPath('data.stepup_mode', 'device_mismatch')
            ->assertJsonPath('data.face_available', true)
            ->assertJsonPath('data.face_challenge', 'liveness');
    }

    public function test_first_device_email_forces_face_available_false(): void
    {
        $this->seedWalletWithoutTrustedDevice();

        $this->postJson('/api/v1/consumer/auth/pin/verify', [
            'phone' => self::PHONE,
            'pin' => '1234',
            'device_id' => 'cn_first',
        ], [
            'X-Device-Id' => 'cn_first',
        ])->assertStatus(403)
            ->assertJsonPath('data.stepup_mode', 'first_device_email')
            ->assertJsonPath('data.face_available', false);
    }

    public function test_stepup_face_photo_requires_liveness(): void
    {
        [$wallet, $account] = $this->seedTrustedWallet();
        $wallet->forceFill(['face_enrolled_at' => now()])->save();

        $session = ConsumerDeviceStepupSession::query()->create([
            'session_token' => 'sess_face_test_1',
            'consumer_wallet_api_account_id' => $account->id,
            'phone_e164' => self::PHONE,
            'whatsapp_wallet_id' => $wallet->id,
            'pending_device_id' => 'cn_new_phone',
            'stepup_mode' => 'device_mismatch',
            'auth_verified_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->post('/api/v1/consumer/auth/device/stepup/face/verify', [
            'stepup_session' => $session->session_token,
            'photo' => UploadedFile::fake()->image('selfie.jpg', 400, 400),
        ], [
            'Accept' => 'application/json',
            'X-Device-Id' => 'cn_new_phone',
        ])->assertStatus(422)
            ->assertJsonPath('data.error_code', 'face_liveness_required')
            ->assertJsonPath('data.face_challenge', 'liveness')
            ->assertJsonPath('data.next_step', 'liveness_session');
    }

    public function test_stepup_face_liveness_video_mints_bind_token(): void
    {
        [$wallet, $account] = $this->seedTrustedWallet();
        $wallet->forceFill(['face_enrolled_at' => now()])->save();

        $session = ConsumerDeviceStepupSession::query()->create([
            'session_token' => 'sess_face_live_1',
            'consumer_wallet_api_account_id' => $account->id,
            'phone_e164' => self::PHONE,
            'whatsapp_wallet_id' => $wallet->id,
            'pending_device_id' => 'cn_new_phone',
            'stepup_mode' => 'device_mismatch',
            'auth_verified_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->mock(WalletFaceCheckService::class, function ($face) {
            $face->shouldReceive('isAvailableForStepUp')->andReturn(true);
            $face->shouldReceive('startLiveness')->once()->andReturn([
                'ok' => true,
                'message' => 'Liveness session created.',
                'data' => [
                    'session_id' => 'lv_test_abc',
                    'challenges' => ['center', 'turn_left', 'turn_right', 'smile'],
                    'expires_in' => 180,
                    'capture' => 'video',
                    'seconds_per_challenge' => 2.4,
                ],
            ]);
            $face->shouldReceive('completeLivenessForStepUp')->once()->andReturn([
                'ok' => true,
                'message' => 'Liveness passed.',
                'data' => [
                    'score' => 94.0,
                    'liveness_score' => 88.0,
                    'liveness_passed' => true,
                    'matched_via' => 'latest',
                ],
            ]);
        });

        $start = $this->postJson('/api/v1/consumer/auth/device/stepup/face/liveness/session', [
            'stepup_session' => $session->session_token,
        ], [
            'X-Device-Id' => 'cn_new_phone',
        ])->assertOk()
            ->assertJsonPath('data.session_id', 'lv_test_abc')
            ->assertJsonPath('data.face_challenge', 'liveness')
            ->assertJsonPath('data.instructions', 'Follow the on-screen prompts')
            ->assertJsonPath('data.challenges.0.id', 'center')
            ->assertJsonPath('data.challenges.1.id', 'left')
            ->assertJsonPath('data.challenges.1.prompt', 'Look left');

        $this->assertNotEmpty($start->json('data.expires_at'));
        $this->assertIsInt($start->json('data.challenges.0.duration_ms'));

        $clip = UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4');

        $this->post('/api/v1/consumer/auth/device/stepup/face/liveness/video', [
            'stepup_session' => $session->session_token,
            'session_id' => 'lv_test_abc',
            'clip' => $clip,
        ], [
            'Accept' => 'application/json',
            'X-Device-Id' => 'cn_new_phone',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.next_step', 'bind')
            ->assertJsonPath('data.liveness_passed', true)
            ->assertJsonStructure(['data' => ['stepup_token']]);

        $session->refresh();
        $this->assertNotNull($session->stepup_token);
        $this->assertNotNull($session->otp_verified_at);
        $this->assertNotNull($session->bvn_verified_at);
    }

    public function test_stepup_face_liveness_rejects_when_not_enrolled(): void
    {
        [$wallet, $account] = $this->seedTrustedWallet();

        $session = ConsumerDeviceStepupSession::query()->create([
            'session_token' => 'sess_face_test_2',
            'consumer_wallet_api_account_id' => $account->id,
            'phone_e164' => self::PHONE,
            'whatsapp_wallet_id' => $wallet->id,
            'pending_device_id' => 'cn_new_phone',
            'stepup_mode' => 'device_mismatch',
            'auth_verified_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->mock(WalletFaceCheckService::class, function ($face) {
            $face->shouldReceive('isAvailableForStepUp')->andReturn(false);
        });

        $this->postJson('/api/v1/consumer/auth/device/stepup/face/liveness/session', [
            'stepup_session' => $session->session_token,
        ], [
            'X-Device-Id' => 'cn_new_phone',
        ])->assertStatus(422)
            ->assertJsonPath('data.error_code', 'face_not_available');
    }

    /**
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount}
     */
    private function seedTrustedWallet(): array
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => self::PHONE,
            'pin_hash' => Hash::make('1234'),
            'pin_set_at' => now(),
            'tier' => 2,
            'balance' => 0,
            'status' => 'active',
            'kyc_fname' => 'Test',
            'kyc_lname' => 'User',
            'sender_name' => 'Test User',
        ]);
        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => self::PHONE,
        ]);
        ConsumerTrustedDevice::query()->create([
            'consumer_wallet_api_account_id' => $account->id,
            'device_id' => 'cn_trusted_install',
            'label' => 'iPhone 17',
            'platform' => 'ios',
            'kyc_confirmed_at' => now(),
            'last_active_at' => now(),
        ]);

        return [$wallet, $account];
    }

    private function seedWalletWithoutTrustedDevice(): void
    {
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => self::PHONE,
            'pin_hash' => Hash::make('1234'),
            'pin_set_at' => now(),
            'tier' => 2,
            'balance' => 0,
            'status' => 'active',
            'kyc_email' => 'face.stepup@example.com',
            'kyc_fname' => 'Test',
            'kyc_lname' => 'User',
            'sender_name' => 'Test User',
        ]);
        ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => self::PHONE,
        ]);
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
                $table->string('kyc_email', 255)->nullable();
                $table->string('kyc_fname', 128)->nullable();
                $table->string('kyc_lname', 128)->nullable();
                $table->string('sender_name', 128)->nullable();
                $table->unsignedTinyInteger('tier')->default(1);
                $table->decimal('balance', 14, 2)->default(0);
                $table->string('status', 32)->default('active');
                $table->decimal('savings_balance', 14, 2)->default(0);
                $table->boolean('transfer_email_otp_enabled')->default(false);
                $table->timestamp('face_enrolled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_wallet_api_accounts')) {
            $schema->create('consumer_wallet_api_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('phone_e164', 32)->unique();
                $table->boolean('pin_reset_required')->default(false);
                $table->timestamp('transfer_lock_until')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_trusted_devices')) {
            $schema->create('consumer_trusted_devices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('consumer_wallet_api_account_id');
                $table->string('device_id', 128)->nullable();
                $table->string('label', 120)->nullable();
                $table->string('platform', 32)->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->timestamp('kyc_confirmed_at')->nullable();
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
                $table->string('pending_device_id', 128)->nullable();
                $table->string('pending_platform', 32)->nullable();
                $table->string('pending_device_label', 120)->nullable();
                $table->string('stepup_mode', 32)->nullable();
                $table->timestamp('auth_verified_at')->nullable();
                $table->timestamp('bvn_verified_at')->nullable();
                $table->timestamp('otp_verified_at')->nullable();
                $table->boolean('pin_set_at_stepup')->default(false);
                $table->string('stepup_token', 64)->nullable()->unique();
                $table->timestamp('stepup_token_expires_at')->nullable();
                $table->timestamp('expires_at');
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
                $table->string('login_method', 32)->nullable();
                $table->string('platform', 32)->nullable();
                $table->string('device_id', 128)->nullable();
                $table->string('device_label', 120)->nullable();
                $table->string('app_version', 64)->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }
    }
}
