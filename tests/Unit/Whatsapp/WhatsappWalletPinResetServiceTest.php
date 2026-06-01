<?php

namespace Tests\Unit\Whatsapp;

use App\Models\WhatsappWallet;
use App\Services\Whatsapp\WhatsappWalletPinResetService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WhatsappWalletPinResetServiceTest extends TestCase
{
    public function test_bvn_verification_requires_eleven_digits(): void
    {
        $wallet = new WhatsappWallet;
        $wallet->forceFill(['kyc_bvn' => '12345678901']);

        $svc = app(WhatsappWalletPinResetService::class);

        $this->assertTrue($svc->verifyBvn($wallet, '12345678901'));
        $this->assertFalse($svc->verifyBvn($wallet, '1234567890'));
    }

    public function test_rate_limit_blocks_after_max_failures(): void
    {
        $wallet = new WhatsappWallet;
        $wallet->forceFill(['id' => 42]);
        Cache::put('wa_wallet_pin_reset_fail:42', 5, now()->addMinutes(15));

        $svc = app(WhatsappWalletPinResetService::class);

        $this->assertTrue($svc->isRateLimited($wallet));

        Cache::forget('wa_wallet_pin_reset_fail:42');
    }
}
