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

    /**
     * Mevon NGN wallet credit for inbound funding: +(gross − inbound_fee).
     */
    public function netInboundImpact(float $gross): float
    {
        $gross = round(max(0, $gross), 2);
        $fee = $this->inboundFee($gross);

        return round($gross - $fee, 2);
    }

    /**
     * Success/pending: −(gross + outbound API fee). Failed/reversed (chargeApiFee=false): 0.
     */
    public function netOutboundImpact(float $gross, bool $chargeApiFee = true): float
    {
        if (! $chargeApiFee) {
            return 0.0;
        }

        $fee = $this->outboundApiFee();

        return round(-1 * (round(max(0, $gross), 2) + $fee), 2);
    }

    /**
     * @return array{inbound_fee: int, net_mevon_impact: float}
     */
    public function inboundBreakdown(float $gross): array
    {
        $gross = round(max(0, $gross), 2);
        $fee = $this->inboundFee($gross);

        return [
            'inbound_fee' => $fee,
            'net_mevon_impact' => round($gross - $fee, 2),
        ];
    }

    /**
     * @return array{outbound_fee: int, net_mevon_impact: float}
     */
    public function outboundBreakdown(float $gross, bool $chargeApiFee = true): array
    {
        if (! $chargeApiFee) {
            return [
                'outbound_fee' => 0,
                'net_mevon_impact' => 0.0,
            ];
        }

        $fee = $this->outboundApiFee();

        return [
            'outbound_fee' => $fee,
            'net_mevon_impact' => round(-1 * (round(max(0, $gross), 2) + $fee), 2),
        ];
    }
}
