<?php

namespace Tests\Unit\Support;

use App\Support\WhatsappWalletKycInputGuard;
use Tests\TestCase;

class WhatsappWalletKycInputGuardTest extends TestCase
{
    public function test_allows_popular_email_domains(): void
    {
        $this->assertNull(WhatsappWalletKycInputGuard::emailError('user@gmail.com'));
        $this->assertNull(WhatsappWalletKycInputGuard::emailError('user@yahoo.com'));
        $this->assertNull(WhatsappWalletKycInputGuard::emailError('user@hotmail.com'));
        $this->assertNull(WhatsappWalletKycInputGuard::emailError('user@outlook.com'));
        $this->assertNull(WhatsappWalletKycInputGuard::emailError('user@icloud.com'));
    }

    public function test_rejects_obscure_and_disposable_email_domains(): void
    {
        $err = WhatsappWalletKycInputGuard::emailError('cybstrike_mv01@emalupe.com');
        $this->assertNotNull($err);
        $this->assertStringContainsString('popular email', strtolower($err));

        $this->assertNotNull(WhatsappWalletKycInputGuard::emailError('x@tempmail.com'));
        $this->assertNotNull(WhatsappWalletKycInputGuard::emailError('x@check-outnow.com'));
    }

    public function test_rejects_generic_bvn_and_cac(): void
    {
        $this->assertNotNull(WhatsappWalletKycInputGuard::bvnOrNinError('12345678901', 'BVN'));
        $this->assertNotNull(WhatsappWalletKycInputGuard::bvnOrNinError('11111111111', 'BVN'));
        $this->assertNotNull(WhatsappWalletKycInputGuard::cacError('1234567'));
        $this->assertNotNull(WhatsappWalletKycInputGuard::cacError('RC1234567')); // sequential digits after prefix
        $this->assertNull(WhatsappWalletKycInputGuard::cacError('RC8444692'));
        $this->assertNull(WhatsappWalletKycInputGuard::bvnOrNinError('22334455667', 'BVN'));
    }

    public function test_rejects_placeholder_phone(): void
    {
        $this->assertNotNull(WhatsappWalletKycInputGuard::phoneError('2348012345678'));
        $this->assertNull(WhatsappWalletKycInputGuard::phoneError('2348169223605'));
    }
}
