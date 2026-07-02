<?php

namespace Tests\Unit\Whatsapp;

use App\Models\Setting;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\WhatsappWalletSelfBankTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappWalletSelfBankTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    private WhatsappWalletSelfBankTransferService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WhatsappWalletSelfBankTransferService::class);
    }

    /** @test */
    public function fee_percent_reads_admin_setting(): void
    {
        Setting::set('whatsapp_self_bank_transfer_fee_percent', 2.25, 'float', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fee_enabled', true, 'boolean', 'whatsapp', 'test');

        $this->assertSame(2.25, $this->service->feePercent());
        $this->assertTrue($this->service->isEnabled());
    }

    /** @test */
    public function quote_applies_fee_from_amount_for_self_transfer(): void
    {
        config(['whatsapp.self_bank_transfer_fee_percent' => 1.5]);
        Setting::set('whatsapp_self_bank_transfer_fee_enabled', true, 'boolean', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fee_percent', 2.0, 'float', 'whatsapp', 'test');

        $quoted = $this->service->quote(10000, true);

        $this->assertTrue($quoted['ok']);
        $this->assertTrue($quoted['is_self_transfer']);
        $this->assertSame(200.0, $quoted['fee']);
        $this->assertSame(9800.0, $quoted['payout_amount']);
    }

    /** @test */
    public function quote_is_free_when_not_self(): void
    {
        $quoted = $this->service->quote(5000, false);

        $this->assertTrue($quoted['ok']);
        $this->assertFalse($quoted['is_self_transfer']);
        $this->assertSame(0.0, $quoted['fee']);
        $this->assertSame(5000.0, $quoted['payout_amount']);
    }

    /** @test */
    public function quote_is_free_when_feature_disabled(): void
    {
        Setting::set('whatsapp_self_bank_transfer_fee_enabled', false, 'boolean', 'whatsapp', 'test');

        $quoted = $this->service->quote(5000, true);

        $this->assertTrue($quoted['ok']);
        $this->assertFalse($quoted['is_self_transfer']);
        $this->assertSame(0.0, $quoted['fee']);
        $this->assertSame(5000.0, $quoted['payout_amount']);
    }

    /** @test */
    public function detects_self_via_fintech_account_matching_wallet_phone(): void
    {
        $wallet = new WhatsappWallet([
            'phone_e164' => '2348012345678',
            'sender_name' => 'Someone Else',
        ]);

        $this->assertTrue($this->service->isSelfTransfer(
            $wallet,
            '8012345678',
            '100004',
            'Random Name',
            false,
        ));
    }

    /** @test */
    public function detects_self_via_name_when_verified_by_enquiry(): void
    {
        $wallet = new WhatsappWallet([
            'phone_e164' => '2348099999999',
            'sender_name' => 'John Doe',
        ]);

        $this->assertTrue($this->service->isSelfTransfer(
            $wallet,
            '1234567890',
            '000014',
            'JOHN DOE',
            true,
        ));
    }

    /** @test */
    public function does_not_apply_name_match_without_enquiry(): void
    {
        $wallet = new WhatsappWallet([
            'phone_e164' => '2348099999999',
            'sender_name' => 'John Doe',
        ]);

        $this->assertFalse($this->service->isSelfTransfer(
            $wallet,
            '1234567890',
            '000014',
            'JOHN DOE',
            false,
        ));
    }

    /** @test */
    public function rejects_amount_too_small_after_fee(): void
    {
        config(['whatsapp.self_bank_transfer_fee_percent' => 1.5]);
        Setting::set('whatsapp_self_bank_transfer_fee_enabled', true, 'boolean', 'whatsapp', 'test');

        $quoted = $this->service->quote(1, true);

        $this->assertFalse($quoted['ok']);
    }

    /** @test */
    public function fixed_fee_reads_admin_setting(): void
    {
        Setting::set('whatsapp_self_bank_transfer_fixed_fee', 75, 'float', 'whatsapp', 'test');

        $this->assertSame(75.0, $this->service->fixedFee());
    }

    /** @test */
    public function quote_applies_fixed_fee_alone_when_percent_is_zero(): void
    {
        Setting::set('whatsapp_self_bank_transfer_fee_enabled', true, 'boolean', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fee_percent', 0, 'float', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fixed_fee', 50, 'float', 'whatsapp', 'test');

        $quoted = $this->service->quote(10000, true);

        $this->assertTrue($quoted['ok']);
        $this->assertTrue($quoted['is_self_transfer']);
        $this->assertSame(50.0, $quoted['fee']);
        $this->assertSame(9950.0, $quoted['payout_amount']);
    }

    /** @test */
    public function quote_adds_percent_and_fixed_before_max_cap(): void
    {
        Setting::set('whatsapp_self_bank_transfer_fee_enabled', true, 'boolean', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fee_percent', 2.0, 'float', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fixed_fee', 50, 'float', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_max_fee', 500, 'float', 'whatsapp', 'test');

        $quoted = $this->service->quote(10000, true);

        $this->assertTrue($quoted['ok']);
        $this->assertSame(250.0, $quoted['fee']);
        $this->assertSame(9750.0, $quoted['payout_amount']);
    }

    /** @test */
    public function max_fee_reads_admin_setting(): void
    {
        Setting::set('whatsapp_self_bank_transfer_max_fee', 750, 'float', 'whatsapp', 'test');

        $this->assertSame(750.0, $this->service->maxFee());
    }

    /** @test */
    public function quote_caps_self_transfer_fee_at_max(): void
    {
        Setting::set('whatsapp_self_bank_transfer_max_fee', 500, 'float', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fee_enabled', true, 'boolean', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fee_percent', 2.0, 'float', 'whatsapp', 'test');

        $quoted = $this->service->quote(50000, true);

        $this->assertTrue($quoted['ok']);
        $this->assertTrue($quoted['is_self_transfer']);
        $this->assertSame(500.0, $quoted['fee']);
        $this->assertSame(49500.0, $quoted['payout_amount']);
    }

    /** @test */
    public function quote_uses_calculated_fee_when_below_max(): void
    {
        Setting::set('whatsapp_self_bank_transfer_max_fee', 500, 'float', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fee_enabled', true, 'boolean', 'whatsapp', 'test');
        Setting::set('whatsapp_self_bank_transfer_fee_percent', 2.0, 'float', 'whatsapp', 'test');

        $quoted = $this->service->quote(10000, true);

        $this->assertTrue($quoted['ok']);
        $this->assertSame(200.0, $quoted['fee']);
        $this->assertSame(9800.0, $quoted['payout_amount']);
    }

    /** @test */
    public function quote_does_not_cap_fee_for_non_self_transfer(): void
    {
        Setting::set('whatsapp_self_bank_transfer_max_fee', 500, 'float', 'whatsapp', 'test');

        $quoted = $this->service->quote(50000, false);

        $this->assertTrue($quoted['ok']);
        $this->assertSame(0.0, $quoted['fee']);
        $this->assertSame(50000.0, $quoted['payout_amount']);
    }
}
