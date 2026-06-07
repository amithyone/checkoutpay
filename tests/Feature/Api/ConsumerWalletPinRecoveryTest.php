<?php

namespace Tests\Feature\Api;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletOtpService;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class ConsumerWalletPinRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+2348012345678';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.evolution.instance' => 'test-instance',
            'consumer_wallet.otp_max_unused_sends' => 3,
        ]);

        $mock = Mockery::mock(EvolutionWhatsAppClient::class);
        $mock->shouldReceive('sendText')->andReturn(true);
        $this->app->instance(EvolutionWhatsAppClient::class, $mock);
    }

    public function test_three_unused_otp_sends_block_fourth_request(): void
    {
        $service = $this->app->make(ConsumerWalletOtpService::class);

        $this->assertTrue($service->requestOtp(self::PHONE)['ok']);
        $this->assertTrue($service->requestOtp(self::PHONE)['ok']);
        $this->assertTrue($service->requestOtp(self::PHONE)['ok']);

        $blocked = $service->requestOtp(self::PHONE);
        $this->assertFalse($blocked['ok']);
        $this->assertTrue($blocked['otp_blocked'] ?? false);
        $this->assertStringContainsString('wallet PIN', $blocked['message']);
    }

    public function test_successful_otp_verify_clears_unused_send_counter(): void
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits(self::PHONE);
        $service = $this->app->make(ConsumerWalletOtpService::class);

        $service->requestOtp(self::PHONE);
        $service->requestOtp(self::PHONE);
        $service->requestOtp(self::PHONE);

        $code = '445566';
        Cache::put('consumer_wallet_otp:'.hash('sha256', (string) $e164), [
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ], 600);

        $this->assertTrue($service->verifyOtp(self::PHONE, $code)['ok']);
        $this->assertTrue($service->requestOtp(self::PHONE)['ok']);
    }

    public function test_p2p_quiz_recovery_resets_pin(): void
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits(self::PHONE);
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => $e164,
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 5000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'pin_hash' => Hash::make('1234'),
            'pin_set_at' => now(),
        ]);

        WhatsappWalletTransaction::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'type' => WhatsappWalletTransaction::TYPE_P2P_CREDIT,
            'amount' => 2500,
            'counterparty_phone_e164' => '2348098765432',
            'counterparty_account_name' => 'Ada Okafor',
            'sender_name' => 'Ada Okafor',
        ]);

        $options = $this->postJson('/api/v1/consumer/auth/recovery/options', ['phone' => self::PHONE]);
        $options->assertOk()
            ->assertJsonPath('data.methods.0', 'p2p_quiz');

        $verify = $this->postJson('/api/v1/consumer/auth/recovery/verify-p2p', [
            'phone' => self::PHONE,
            'sender_hint' => 'Ada',
            'amount' => '2500',
        ]);
        $verify->assertOk();
        $token = (string) $verify->json('data.recovery_token');
        $this->assertNotSame('', $token);

        $reset = $this->postJson('/api/v1/consumer/auth/recovery/reset-pin', [
            'recovery_token' => $token,
            'pin' => '5678',
            'pin_confirmation' => '5678',
        ]);
        $reset->assertOk();

        $wallet->refresh();
        $this->assertTrue(Hash::check('5678', (string) $wallet->pin_hash));
    }

    public function test_tier2_bvn_recovery(): void
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits(self::PHONE);
        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => $e164,
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'balance' => 0,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'pin_hash' => Hash::make('1234'),
            'pin_set_at' => now(),
            'kyc_bvn' => '22222222222',
            'mevon_account_name' => 'John Doe',
        ]);

        $verify = $this->postJson('/api/v1/consumer/auth/recovery/verify-bvn', [
            'phone' => self::PHONE,
            'bvn' => '22222222222',
        ]);
        $verify->assertOk();

        $token = (string) $verify->json('data.recovery_token');
        $reset = $this->postJson('/api/v1/consumer/auth/recovery/reset-pin', [
            'recovery_token' => $token,
            'pin' => '9999',
            'pin_confirmation' => '9999',
        ]);
        $reset->assertOk();

        $wallet->refresh();
        $this->assertTrue(Hash::check('9999', (string) $wallet->pin_hash));
    }
}
