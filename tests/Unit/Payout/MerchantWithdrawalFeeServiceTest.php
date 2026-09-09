<?php

namespace Tests\Unit\Payout;

use App\Models\WithdrawalRequest;
use App\Services\Payout\MerchantWithdrawalFeeService;
use Tests\TestCase;

class MerchantWithdrawalFeeServiceTest extends TestCase
{
    public function test_dashboard_and_payout_api_are_chargeable(): void
    {
        config([
            'merchant_withdrawal.fee_enabled' => true,
            'merchant_withdrawal.flat_fee' => 50,
        ]);

        $service = app(MerchantWithdrawalFeeService::class);

        $this->assertSame(50.0, $service->feeForSource(WithdrawalRequest::SOURCE_DASHBOARD));
        $this->assertSame(50.0, $service->feeForSource(WithdrawalRequest::SOURCE_PAYOUT_API));
        $this->assertSame(1050.0, $service->totalDebit(1000, WithdrawalRequest::SOURCE_PAYOUT_API));
        $this->assertSame(950.0, $service->maxPayoutAmount(1000, WithdrawalRequest::SOURCE_DASHBOARD));
    }

    public function test_admin_auto_and_rentals_sources_are_free(): void
    {
        config([
            'merchant_withdrawal.fee_enabled' => true,
            'merchant_withdrawal.flat_fee' => 50,
        ]);

        $service = app(MerchantWithdrawalFeeService::class);

        foreach ([
            WithdrawalRequest::SOURCE_ADMIN,
            WithdrawalRequest::SOURCE_AUTO,
            WithdrawalRequest::SOURCE_RENTALS_API,
            null,
        ] as $source) {
            $this->assertSame(0.0, $service->feeForSource($source));
            $this->assertSame(1000.0, $service->totalDebit(1000, $source));
        }
    }

    public function test_fee_disabled_returns_zero(): void
    {
        config([
            'merchant_withdrawal.fee_enabled' => false,
            'merchant_withdrawal.flat_fee' => 50,
        ]);

        $service = app(MerchantWithdrawalFeeService::class);

        $this->assertSame(0.0, $service->feeForSource(WithdrawalRequest::SOURCE_PAYOUT_API));
    }
}
