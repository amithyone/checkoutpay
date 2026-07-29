<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\TouchConsumerAppSession;
use App\Models\ConsumerPasskeyCredential;
use App\Models\ConsumerTrustedDevice;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerPaymentAuthService;
use App\Services\Consumer\ConsumerWalletTransferService;
use App\Services\Consumer\ConsumerWebAuthnService;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsumerPasskeyWebAuthnTest extends TestCase
{
    private const PHONE = '+2348012345678';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(TouchConsumerAppSession::class);

        $this->ensureSchema();

        config([
            'consumer_wallet.device_trust_enabled' => true,
            'consumer_wallet.webauthn_rp_id' => 'check-outpay.com',
            'consumer_wallet.webauthn_rp_name' => 'CheckoutNow',
            'consumer_wallet.webauthn_allowed_origins' => ['https://check-outpay.com'],
        ]);
    }

    public function test_passkey_login_options_returns_422_when_no_passkey(): void
    {
        $this->seedAccountWithoutPasskey();

        $this->postJson('/api/v1/consumer/auth/passkey/login/options', [
            'phone' => self::PHONE,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No passkey registered for this number.');
    }

    public function test_passkey_login_options_returns_webauthn_json_when_passkey_exists(): void
    {
        if (! ConsumerWebAuthnService::isAvailable()) {
            $this->markTestSkipped('web-auth/webauthn-lib not installed.');
        }

        $this->seedAccountWithPasskey();

        $response = $this->postJson('/api/v1/consumer/auth/passkey/login/options', [
            'phone' => self::PHONE,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data['challenge'] ?? null);
        $this->assertSame('check-outpay.com', $data['rpId'] ?? null);
    }

    public function test_passkey_register_options_returns_webauthn_json_for_authenticated_user(): void
    {
        if (! ConsumerWebAuthnService::isAvailable()) {
            $this->markTestSkipped('web-auth/webauthn-lib not installed.');
        }

        [, $account] = $this->seedAccountWithoutPasskey();
        Sanctum::actingAs($account, ['consumer']);

        $response = $this->postJson('/api/v1/consumer/auth/passkey/register/options', [
            'device_name' => 'Test iPhone',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data['challenge'] ?? null);
        $this->assertSame('check-outpay.com', $data['rp']['id'] ?? null);
    }

    public function test_passkey_register_options_returns_503_when_packages_missing(): void
    {
        $original = ConsumerWebAuthnService::class;
        $this->app->bind($original, function () {
            return new class extends ConsumerWebAuthnService
            {
                public static function isAvailable(): bool
                {
                    return false;
                }
            };
        });

        [, $account] = $this->seedAccountWithoutPasskey();
        Sanctum::actingAs($account, ['consumer']);

        $this->postJson('/api/v1/consumer/auth/passkey/register/options', [
            'device_name' => 'Test Phone',
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Passkeys are not configured on this server. Install web-auth/webauthn-lib and web-auth/cose-lib, then run composer install.']);
    }

    public function test_transaction_options_requires_authentication(): void
    {
        $this->postJson('/api/v1/consumer/auth/passkey/transaction/options', [
            'intent' => ['action' => 'transfer_p2p'],
        ])->assertUnauthorized();
    }

    public function test_transaction_options_returns_422_without_passkey(): void
    {
        if (! ConsumerWebAuthnService::isAvailable()) {
            $this->markTestSkipped('web-auth/webauthn-lib not installed.');
        }

        [, $account] = $this->seedAccountWithoutPasskey();
        Sanctum::actingAs($account, ['consumer']);

        $this->postJson('/api/v1/consumer/auth/passkey/transaction/options', [
            'intent' => ['action' => 'transfer_p2p'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'No passkey registered on this account.');
    }

    public function test_transaction_options_returns_challenge_and_token_with_passkey(): void
    {
        if (! ConsumerWebAuthnService::isAvailable()) {
            $this->markTestSkipped('web-auth/webauthn-lib not installed.');
        }

        [, $account] = $this->seedAccountWithPasskeyReturningAccount();
        Sanctum::actingAs($account, ['consumer']);

        $response = $this->postJson('/api/v1/consumer/auth/passkey/transaction/options', [
            'intent' => ['action' => 'transfer_p2p'],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data['challenge'] ?? null);
        $this->assertNotEmpty($data['challenge_token'] ?? null);
    }

    public function test_transaction_options_returns_503_when_packages_missing(): void
    {
        $this->app->bind(ConsumerWebAuthnService::class, function () {
            return new class extends ConsumerWebAuthnService
            {
                public static function isAvailable(): bool
                {
                    return false;
                }
            };
        });

        [, $account] = $this->seedAccountWithPasskeyReturningAccount();
        Sanctum::actingAs($account, ['consumer']);

        $this->postJson('/api/v1/consumer/auth/passkey/transaction/options', [
            'intent' => ['action' => 'transfer_p2p'],
        ])
            ->assertStatus(503)
            ->assertJsonPath('success', false);
    }

    public function test_transfer_p2p_accepts_payment_token_and_rejects_reuse(): void
    {
        [$wallet, $account] = $this->seedAccountWithoutPasskey();

        $issued = app(ConsumerPaymentAuthService::class)->issuePaymentToken(
            (int) $account->id,
            (int) $wallet->id,
            ['action' => 'transfer_p2p'],
        );

        $this->mock(ConsumerWalletTransferService::class, function ($mock): void {
            $mock->shouldReceive('p2p')->once()->andReturn([
                'ok' => true,
                'message' => 'Sent.',
                'data' => ['reference' => 'REF-TEST'],
            ]);
        });

        Sanctum::actingAs($account, ['consumer']);

        $this->postJson('/api/v1/consumer/transfers/p2p', [
            'to_phone' => '08087654321',
            'amount' => 100,
            'payment_token' => $issued['payment_token'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/consumer/transfers/p2p', [
            'to_phone' => '08087654321',
            'amount' => 100,
            'payment_token' => $issued['payment_token'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid or expired payment authorization.');
    }

    public function test_transaction_verify_returns_payment_token_when_webauthn_succeeds(): void
    {
        [, $account] = $this->seedAccountWithPasskeyReturningAccount();
        Sanctum::actingAs($account, ['consumer']);

        $this->mock(ConsumerWebAuthnService::class, function ($mock): void {
            $mock->shouldReceive('transactionVerify')->once()->andReturn([
                'ok' => true,
                'payment_token' => 'ptok_mock_token',
                'expires_at' => now()->addMinutes(5)->toIso8601String(),
            ]);
        });

        $this->postJson('/api/v1/consumer/auth/passkey/transaction/verify', [
            'credential' => ['id' => 'test'],
            'challenge_token' => (string) \Illuminate\Support\Str::uuid(),
            'intent' => ['action' => 'transfer_p2p'],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_token', 'ptok_mock_token');
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
                $table->unsignedTinyInteger('tier')->default(1);
                $table->decimal('balance', 14, 2)->default(0);
                $table->string('status', 32)->default('active');
                $table->string('kyc_fname', 128)->nullable();
                $table->string('kyc_lname', 128)->nullable();
                $table->string('sender_name', 128)->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('consumer_wallet_api_accounts')) {
            $schema->create('consumer_wallet_api_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('whatsapp_wallet_id');
                $table->string('phone_e164', 32)->unique();
                $table->timestamp('last_app_active_at')->nullable();
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
    private function seedAccountWithoutPasskey(): array
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits(self::PHONE);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => $e164,
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 1000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);

        $account = ConsumerWalletApiAccount::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'phone_e164' => $e164,
        ]);

        return [$wallet, $account];
    }

    private function seedAccountWithPasskey(): void
    {
        $this->seedAccountWithPasskeyReturningAccount();
    }

    /**
     * @return array{0: WhatsappWallet, 1: ConsumerWalletApiAccount}
     */
    private function seedAccountWithPasskeyReturningAccount(): array
    {
        [$wallet, $account] = $this->seedAccountWithoutPasskey();

        $device = ConsumerTrustedDevice::query()->create([
            'consumer_wallet_api_account_id' => $account->id,
            'label' => 'iPhone',
            'platform' => 'ios',
            'last_active_at' => now(),
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
