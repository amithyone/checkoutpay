<?php

namespace App\Services\Payout;

use App\Models\WithdrawalRequest;

final class MerchantWithdrawalFeeService
{
    /** @var list<string> */
    private const CHARGEABLE_SOURCES = [
        WithdrawalRequest::SOURCE_PAYOUT_API,
        WithdrawalRequest::SOURCE_DASHBOARD,
    ];

    public function isEnabled(): bool
    {
        return (bool) config('merchant_withdrawal.fee_enabled', true);
    }

    public function flatFee(): float
    {
        return round(max(0.0, (float) config('merchant_withdrawal.flat_fee', 0)), 2);
    }

    public function isChargeableSource(?string $source): bool
    {
        return in_array((string) $source, self::CHARGEABLE_SOURCES, true);
    }

    public function feeForSource(?string $source): float
    {
        if (! $this->isEnabled() || ! $this->isChargeableSource($source)) {
            return 0.0;
        }

        return $this->flatFee();
    }

    public function totalDebit(float $amount, ?string $source): float
    {
        return round(max(0, $amount) + $this->feeForSource($source), 2);
    }

    /**
     * Maximum payout amount given available balance (amount sent to bank, excluding fee).
     */
    public function maxPayoutAmount(float $availableBalance, ?string $source): float
    {
        $available = round(max(0, $availableBalance), 2);
        $fee = $this->feeForSource($source);

        return round(max(0, $available - $fee), 2);
    }
}
