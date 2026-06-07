<?php

namespace Tests\Feature\Api;

use App\Services\Consumer\ConsumerWalletOtpService;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ConsumerWalletOtpTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+2348012345678';

    private function otpCacheKey(): string
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits(self::PHONE);

        return 'consumer_wallet_otp:'.hash('sha256', (string) $e164);
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.evolution.instance' => 'test-instance',
            'consumer_wallet.otp_max_attempts' => 3,
        ]);

        $mock = Mockery::mock(EvolutionWhatsAppClient::class);
        $mock->shouldReceive('sendText')->andReturn(true);
        $this->app->instance(EvolutionWhatsAppClient::class, $mock);
    }

    public function test_requesting_new_otp_clears_verify_attempt_lockout(): void
    {
        $service = $this->app->make(ConsumerWalletOtpService::class);

        $this->assertTrue($service->requestOtp(self::PHONE)['ok']);

        $service->verifyOtp(self::PHONE, '000000');
        $service->verifyOtp(self::PHONE, '000000');
        $service->verifyOtp(self::PHONE, '000000');

        $locked = $service->verifyOtp(self::PHONE, '000000');
        $this->assertFalse($locked['ok']);
        $this->assertStringContainsString('Too many wrong codes', $locked['message']);

        $this->assertTrue($service->requestOtp(self::PHONE)['ok']);

        $payload = Cache::get($this->otpCacheKey());
        $this->assertIsArray($payload);

        $code = '424242';
        Cache::put($this->otpCacheKey(), [
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ], 600);

        $verified = $service->verifyOtp(self::PHONE, $code);
        $this->assertTrue($verified['ok'], $verified['message'] ?? '');
    }

    public function test_otp_request_and_verify_via_api(): void
    {
        $request = $this->postJson('/api/v1/consumer/auth/otp/request', [
            'phone' => self::PHONE,
            'channel' => 'whatsapp',
        ]);
        $request->assertOk()->assertJsonPath('success', true);

        $code = '112233';
        Cache::put($this->otpCacheKey(), [
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10)->timestamp,
        ], 600);

        $verify = $this->postJson('/api/v1/consumer/auth/otp/verify', [
            'phone' => self::PHONE,
            'code' => $code,
        ]);
        $verify->assertOk()->assertJsonPath('success', true);
    }
}
