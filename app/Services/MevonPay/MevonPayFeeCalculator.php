<?php

namespace App\Services\MevonPay;

final class MevonPayFeeCalculator
{
    public function inboundThreshold(): float
    {
        return (float) config('mevonpay_fees.inbound_threshold', 10000);
    }

    public function inboundFee(float $gross): int
    {
        return $gross < $this->inboundThreshold()
            ? (int) config('mevonpay_fees.inbound_fee_below', 30)
            : (int) config('mevonpay_fees.inbound_fee_at_or_above', 50);
    }

    public function outboundApiFee(): int
    {
        return (int) config('mevonpay_fees.outbound_api_fee', 10);
    }

    public function netInboundImpact(float $gross): float
    {
        return round(-1 * $this->inboundFee($gross), 2);
    }

    public function netOutboundImpact(float $gross, bool $chargeApiFee = true): float
    {
        $fee = $chargeApiFee ? $this->outboundApiFee() : 0;

        return round(-1 * ($gross + $fee), 2);
    }

    /**
    * @return array{inbound_fee: int, net_mevon_impact: float}
    */
    public function inboundBreakdown(float $gross): array
    {
        $fee = $this->inboundFee($gross);

        return [
            'inbound_fee' => $fee,
            'net_mevon_impact' => round(-1 * $fee, 2),
        ];
    }

    /**
    * @return array{outbound_fee: int, net_mevon_impact: float}
    */
    public function outboundBreakdown(float $gross, bool $chargeApiFee = true): array
    {
        $fee = $chargeApiFee ? $this->outboundApiFee() : 0;

        return [
            'outbound_fee' => $fee,
            'net_mevon_impact' => round(-1 * ($gross + $fee), 2),
        ];
    }
}
