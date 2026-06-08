<?php

namespace Tests\Unit\Whatsapp;

use App\Models\WhatsappWallet;
use App\Services\Whatsapp\WhatsappWalletCasualSendParser;
use App\Services\WhatsappWalletBankPayoutService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WhatsappWalletCasualSendParserTest extends TestCase
{
    private function wallet(): WhatsappWallet
    {
        return new WhatsappWallet([
            'phone_e164' => '2348011111111',
            'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
            'balance' => 50000,
            'status' => WhatsappWallet::STATUS_ACTIVE,
        ]);
    }

    private function bankPayout(): WhatsappWalletBankPayoutService
    {
        return app(WhatsappWalletBankPayoutService::class);
    }

    public function test_send_mon_account_amount_any_order(): void
    {
        $wallet = $this->wallet();
        $recent = [];

        foreach (['send mon 8148790554 1000', 'send 1000 mon 8148790554', '1000 send mon 8148790554'] as $text) {
            $parsed = WhatsappWalletCasualSendParser::tryParse($text, $wallet, $this->bankPayout(), $recent);
            $this->assertNotNull($parsed, "Failed for: {$text}");
            $this->assertSame('bank_direct', $parsed['flow'], "Flow for: {$text}");
            $this->assertSame(1000.0, $parsed['amount']);
            $this->assertSame('8148790554', $parsed['ctx']['dest_acct']);
            $this->assertSame('090405', $parsed['ctx']['dest_bank_code']);
        }
    }

    public function test_ambiguous_bank_prefix_returns_disambiguate_flow(): void
    {
        Config::set('whatsapp_wallet_quick_banks', [
            ['code' => '090405', 'label' => 'Moniepoint MFB', 'aliases' => ['moniepoint'], 'prefixes' => ['mon']],
            ['code' => '999999', 'label' => 'Monarch Microfinance', 'aliases' => ['monarch'], 'prefixes' => ['mon']],
        ]);

        $parsed = WhatsappWalletCasualSendParser::tryParse(
            'send mon 8148790554 1000',
            $this->wallet(),
            $this->bankPayout(),
            []
        );

        $this->assertNotNull($parsed);
        $this->assertSame('bank_prefix_disambiguate', $parsed['flow']);
        $this->assertSame('8148790554', $parsed['acct']);
        $this->assertGreaterThanOrEqual(2, count($parsed['candidates']));
    }

    public function test_classic_mobile_without_bank_stays_p2p(): void
    {
        $parsed = WhatsappWalletCasualSendParser::tryParse(
            'send 5k to 08031234567',
            $this->wallet(),
            $this->bankPayout(),
            []
        );

        $this->assertNotNull($parsed);
        $this->assertSame('p2p', $parsed['flow']);
        $this->assertStringContainsString('8031234567', $parsed['recipient_e164']);
    }

    public function test_two_ten_digit_accounts_does_not_use_unordered_bank(): void
    {
        $parsed = WhatsappWalletCasualSendParser::tryParse(
            'send mon 8148790554 8012345678 1000',
            $this->wallet(),
            $this->bankPayout(),
            []
        );

        $this->assertNull($parsed);
    }

    public function test_legacy_direct_account_bank_tail_still_works(): void
    {
        $parsed = WhatsappWalletCasualSendParser::tryParse(
            'send 20000 to 0210085995 gtbank',
            $this->wallet(),
            $this->bankPayout(),
            []
        );

        $this->assertNotNull($parsed);
        $this->assertSame('bank_direct', $parsed['flow']);
        $this->assertSame('0210085995', $parsed['ctx']['dest_acct']);
    }

    public function test_leading_zero_account_not_picked_as_amount(): void
    {
        $text = 'send 2k to 0098767877 sterling';

        $this->assertSame(2000.0, WhatsappWalletCasualSendParser::largestNairaAmount($text));

        $parsed = WhatsappWalletCasualSendParser::tryParse(
            $text,
            $this->wallet(),
            $this->bankPayout(),
            []
        );

        $this->assertNotNull($parsed);
        $this->assertSame('bank_direct', $parsed['flow']);
        $this->assertSame(2000.0, $parsed['amount']);
        $this->assertSame('0098767877', $parsed['ctx']['dest_acct']);
        $this->assertSame('000001', $parsed['ctx']['dest_bank_code']);
    }
}
