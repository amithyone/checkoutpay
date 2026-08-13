<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\MevonPayLedgerEntry;
use App\Services\MavonPayTransferService;
use App\Services\MevonPay\MevonPayPayoutService;
use App\Services\WithdrawalMavonPayPayoutService;
use Mockery;
use Tests\TestCase;

class WithdrawalDebitRoutingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cooldown_is_one_minute(): void
    {
        $this->assertSame(1, WithdrawalMavonPayPayoutService::COOLDOWN_MINUTES);
    }

    public function test_default_debit_is_checkout_pool(): void
    {
        config(['services.mevonpay.debit_account_number' => '9999000011']);
        config(['services.mevonpay.debit_account_name' => 'Checkout']);

        $this->bindPayoutConfigured(true);

        $business = new Business([
            'name' => 'Acme Ltd',
            'withdrawal_debit_source' => 'checkout',
            'rubies_business_account_number' => '8888777766',
            'rubies_business_account_name' => 'Acme Ltd',
        ]);

        $profile = app(WithdrawalMavonPayPayoutService::class)->debitProfile($business);

        $this->assertSame(WithdrawalMavonPayPayoutService::DEBIT_CHECKOUT, $profile['source']);
        $this->assertSame(MevonPayLedgerEntry::PAYOUT_API_CREATETRANSFER, $profile['payout_api']);
        $this->assertSame('9999000011', $profile['debit_account_number']);
    }

    public function test_business_source_without_permanent_va_falls_back_to_checkout(): void
    {
        config(['services.mevonpay.debit_account_number' => '9999000011']);

        $this->bindPayoutConfigured(true);

        $business = new Business([
            'name' => 'Acme Ltd',
            'withdrawal_debit_source' => 'business',
            'rubies_business_account_number' => null,
        ]);

        $profile = app(WithdrawalMavonPayPayoutService::class)->debitProfile($business);

        $this->assertSame(WithdrawalMavonPayPayoutService::DEBIT_CHECKOUT, $profile['source']);
        $this->assertSame(MevonPayLedgerEntry::PAYOUT_API_CREATETRANSFER, $profile['payout_api']);
    }

    public function test_business_source_with_permanent_va_uses_payout(): void
    {
        $this->bindPayoutConfigured(true);

        $business = new Business([
            'name' => 'Acme Ltd',
            'withdrawal_debit_source' => 'business',
            'rubies_business_account_number' => '8888777766',
            'rubies_business_account_name' => 'Acme Ltd VA',
        ]);

        $profile = app(WithdrawalMavonPayPayoutService::class)->debitProfile($business);

        $this->assertSame(WithdrawalMavonPayPayoutService::DEBIT_BUSINESS, $profile['source']);
        $this->assertSame(MevonPayLedgerEntry::PAYOUT_API_PAYOUT, $profile['payout_api']);
        $this->assertSame('8888777766', $profile['debit_account_number']);
        $this->assertSame('Acme Ltd VA', $profile['debit_account_name']);
    }

    public function test_business_source_falls_back_to_checkout_when_payout_not_configured(): void
    {
        config(['services.mevonpay.debit_account_number' => '9999000011']);

        $this->bindPayoutConfigured(false);

        $business = new Business([
            'name' => 'Acme Ltd',
            'withdrawal_debit_source' => 'business',
            'rubies_business_account_number' => '8888777766',
            'rubies_business_account_name' => 'Acme Ltd VA',
        ]);

        $profile = app(WithdrawalMavonPayPayoutService::class)->debitProfile($business);

        $this->assertSame(WithdrawalMavonPayPayoutService::DEBIT_CHECKOUT, $profile['source']);
        $this->assertSame(MevonPayLedgerEntry::PAYOUT_API_CREATETRANSFER, $profile['payout_api']);
    }

    private function bindPayoutConfigured(bool $configured): void
    {
        $payout = Mockery::mock(MevonPayPayoutService::class);
        $payout->shouldReceive('isConfigured')->andReturn($configured);
        $this->app->instance(MevonPayPayoutService::class, $payout);

        $mavon = Mockery::mock(MavonPayTransferService::class);
        $mavon->shouldReceive('isConfigured')->andReturn(true);
        $this->app->instance(MavonPayTransferService::class, $mavon);
    }
}
