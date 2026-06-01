<?php

namespace Tests\Unit\Whatsapp;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\WhatsappWalletPinSetupWebService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WhatsappWalletPinResetWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_token_overwrites_existing_pin(): void
    {
        $session = WhatsappSession::query()->create([
            'phone_e164' => '2348012345678',
            'remote_jid' => '2348012345678@s.whatsapp.net',
            'evolution_instance' => 'test-instance',
            'state' => WhatsappSession::STATE_WELCOME,
        ]);

        $wallet = WhatsappWallet::query()->create([
            'phone_e164' => '2348012345678',
            'tier' => WhatsappWallet::TIER_RUBIES_VA,
            'balance' => 100,
            'status' => WhatsappWallet::STATUS_ACTIVE,
            'pin_hash' => Hash::make('1234'),
            'kyc_bvn' => '12345678901',
            'mevon_account_name' => 'Test User',
        ]);

        $svc = app(WhatsappWalletPinSetupWebService::class);
        $created = $svc->createResetToken($session, 'test-instance', '2348012345678', $wallet);
        $this->assertTrue($created['ok'] ?? false);
        $token = (string) ($created['token'] ?? '');
        $this->assertNotSame('', $token);

        $result = $svc->completeReset($token, '5678', '5678');
        $this->assertTrue($result['ok'] ?? false);

        $wallet->refresh();
        $this->assertTrue($wallet->hasPin());
        $this->assertTrue(Hash::check('5678', (string) $wallet->pin_hash));
        $this->assertSame(0, (int) $wallet->pin_failed_attempts);
        $this->assertNull($wallet->pin_locked_until);
    }

    public function test_expired_reset_token_rejected(): void
    {
        $svc = app(WhatsappWalletPinSetupWebService::class);
        $result = $svc->completeReset(str_repeat('a', 64), '5678', '5678');
        $this->assertFalse($result['ok'] ?? false);
    }
}
