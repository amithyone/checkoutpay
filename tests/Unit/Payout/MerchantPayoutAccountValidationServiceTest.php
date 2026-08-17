<?php

namespace Tests\Unit\Payout;

use App\Services\Payout\MerchantPayoutAccountValidationService;
use App\Services\WhatsappWalletBankPayoutService;
use App\Services\WithdrawalMavonPayPayoutService;
use Mockery;
use Tests\TestCase;

class MerchantPayoutAccountValidationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_validate_returns_verified_account_payload(): void
    {
        $bankPayout = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $bankPayout->shouldReceive('isNameEnquiryAvailable')->once()->andReturn(true);
        $bankPayout->shouldReceive('nameEnquiry')->once()->with('000058', '0123456789')->andReturn([
            'account_name' => 'JANE DOE',
            'bank_code' => '000058',
        ]);

        $payout = Mockery::mock(WithdrawalMavonPayPayoutService::class);
        $payout->shouldReceive('resolveBankCode')->once()->with('000058', 'GTBank')->andReturn('000058');

        $service = new MerchantPayoutAccountValidationService($bankPayout, $payout);
        $result = $service->validate('0123456789', '000058', 'GTBank');

        $this->assertTrue($result['ok']);
        $this->assertSame('JANE DOE', $result['data']['account_name']);
        $this->assertSame('000058', $result['data']['bank_code']);
    }

    public function test_payout_precheck_rejects_name_mismatch(): void
    {
        $bankPayout = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $bankPayout->shouldReceive('isNameEnquiryAvailable')->once()->andReturn(true);
        $bankPayout->shouldReceive('nameEnquiry')->once()->andReturn([
            'account_name' => 'JANE DOE',
            'bank_code' => '000058',
        ]);

        $payout = Mockery::mock(WithdrawalMavonPayPayoutService::class);
        $payout->shouldReceive('resolveBankCode')->once()->andReturn('000058');

        $service = new MerchantPayoutAccountValidationService($bankPayout, $payout);
        $failure = $service->payoutPrecheckFailure(
            '0123456789',
            'John Smith',
            '000058',
            'Guaranty Trust Bank',
        );

        $this->assertNotNull($failure);
        $this->assertSame('account_name_mismatch', $failure['code']);
        $this->assertSame('JANE DOE', $failure['data']['verified_account_name']);
    }

    public function test_payout_precheck_passes_when_name_matches(): void
    {
        $bankPayout = Mockery::mock(WhatsappWalletBankPayoutService::class);
        $bankPayout->shouldReceive('isNameEnquiryAvailable')->once()->andReturn(true);
        $bankPayout->shouldReceive('nameEnquiry')->once()->andReturn([
            'account_name' => 'JANE DOE',
            'bank_code' => '000058',
        ]);

        $payout = Mockery::mock(WithdrawalMavonPayPayoutService::class);
        $payout->shouldReceive('resolveBankCode')->once()->andReturn('000058');

        $service = new MerchantPayoutAccountValidationService($bankPayout, $payout);
        $failure = $service->payoutPrecheckFailure(
            '0123456789',
            'Jane Doe',
            '000058',
            'Guaranty Trust Bank',
        );

        $this->assertNull($failure);
    }
}
