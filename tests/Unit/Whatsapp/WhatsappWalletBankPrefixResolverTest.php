<?php

namespace Tests\Unit\Whatsapp;

use App\Services\WhatsappWalletBankPayoutService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WhatsappWalletBankPrefixResolverTest extends TestCase
{
    public function test_mon_prefix_matches_moniepoint(): void
    {
        $svc = app(WhatsappWalletBankPayoutService::class);
        $matches = $svc->resolveBanksByPrefix('mon');

        $this->assertNotEmpty($matches);
        $codes = array_column($matches, 'code');
        $this->assertContains('090405', $codes);
    }

    public function test_prefix_under_three_chars_returns_empty(): void
    {
        $svc = app(WhatsappWalletBankPayoutService::class);
        $this->assertSame([], $svc->resolveBanksByPrefix('mo'));
    }

    public function test_ambiguous_prefix_returns_multiple_banks(): void
    {
        Config::set('whatsapp_wallet_quick_banks', [
            ['code' => '090405', 'label' => 'Moniepoint MFB', 'aliases' => ['moniepoint'], 'prefixes' => ['mon']],
            ['code' => '999999', 'label' => 'Monarch Microfinance', 'aliases' => ['monarch'], 'prefixes' => ['mon']],
        ]);

        $svc = app(WhatsappWalletBankPayoutService::class);
        $matches = $svc->resolveBanksByPrefix('mon');

        $this->assertGreaterThanOrEqual(2, count($matches));
    }
}
