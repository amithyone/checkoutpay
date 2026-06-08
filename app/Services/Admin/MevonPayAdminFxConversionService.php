<?php

namespace App\Services\Admin;

use App\Services\MevonPay\MevonPayBalanceSnapshotService;
use App\Services\MevonPay\MevonPayExchangeClient;
use App\Services\MevonPay\MevonPayExchangeRateService;
use Illuminate\Support\Facades\Log;

final class MevonPayAdminFxConversionService
{
    public function __construct(
        private MevonPayBalanceSnapshotService $balances,
        private MevonPayExchangeClient $exchange,
        private MevonPayExchangeRateService $rates,
    ) {}

    /**
     * Buy USD on MevonPay by spending NGN (POST /V1/exchange NGN → USD).
     *
     * @return array<string, mixed>
     */
    public function buyUsd(float $usdAmount, ?int $adminId = null): array
    {
        if (! $this->exchange->isConfigured()) {
            return ['ok' => false, 'message' => 'MevonPay is not configured for currency exchange.'];
        }

        $usdAmount = round($usdAmount, 2);
        if ($usdAmount <= 0) {
            return ['ok' => false, 'message' => 'USD amount must be greater than zero.'];
        }

        $maxPerOp = $this->maxUsdPerOp();
        if ($maxPerOp > 0 && $usdAmount > $maxPerOp) {
            return ['ok' => false, 'message' => 'Amount exceeds the per-operation limit of $'.number_format($maxPerOp, 2).' USD.'];
        }

        $before = $this->balances->forDashboard();
        if (! ($before['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($before['message'] ?? 'Could not read MevonPay balances.')];
        }

        $ngnAvailable = $before['naira_balance'];
        if ($ngnAvailable === null) {
            return ['ok' => false, 'message' => 'MevonPay NGN balance is unavailable.'];
        }

        $ngnSpend = $this->estimateNgnForUsd($usdAmount);
        if ($ngnAvailable < $ngnSpend) {
            return [
                'ok' => false,
                'message' => 'Insufficient NGN on MevonPay. Need about ₦'.number_format($ngnSpend, 2).' to buy $'.number_format($usdAmount, 2).' USD.',
            ];
        }

        Log::info('mevonpay.admin_fx.buy_usd', [
            'admin_id' => $adminId,
            'usd_target' => $usdAmount,
            'ngn_spend' => $ngnSpend,
            'naira_before' => $ngnAvailable,
            'usd_before' => $before['usd_balance'] ?? null,
        ]);

        $conversion = $this->exchange->convert($ngnSpend, 'NGN', 'USD');
        if (! ($conversion['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($conversion['message'] ?? 'MevonPay could not convert NGN to USD.'),
            ];
        }

        $convertedUsd = $this->extractConvertedAmount($conversion, 'USD');
        $after = $this->balances->forDashboard();
        $this->recordSnapshotAfterTrade('admin_buy_usd');

        return [
            'ok' => true,
            'message' => 'Bought $'.number_format($convertedUsd > 0 ? $convertedUsd : $usdAmount, 2).' USD using ₦'.number_format($ngnSpend, 2).' NGN.',
            'direction' => 'buy',
            'usd_amount' => $convertedUsd > 0 ? $convertedUsd : $usdAmount,
            'ngn_amount' => $ngnSpend,
            'balances_before' => [
                'naira' => $ngnAvailable,
                'usd' => $before['usd_balance'] ?? null,
            ],
            'balances_after' => [
                'naira' => $after['naira_balance'] ?? null,
                'usd' => $after['usd_balance'] ?? null,
            ],
        ];
    }

    /**
     * Sell USD on MevonPay for NGN (POST /V1/exchange USD → NGN).
     *
     * @return array<string, mixed>
     */
    public function sellUsd(float $usdAmount, ?int $adminId = null): array
    {
        if (! $this->exchange->isConfigured()) {
            return ['ok' => false, 'message' => 'MevonPay is not configured for currency exchange.'];
        }

        $usdAmount = round($usdAmount, 2);
        if ($usdAmount <= 0) {
            return ['ok' => false, 'message' => 'USD amount must be greater than zero.'];
        }

        $maxPerOp = $this->maxUsdPerOp();
        if ($maxPerOp > 0 && $usdAmount > $maxPerOp) {
            return ['ok' => false, 'message' => 'Amount exceeds the per-operation limit of $'.number_format($maxPerOp, 2).' USD.'];
        }

        $before = $this->balances->forDashboard();
        if (! ($before['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($before['message'] ?? 'Could not read MevonPay balances.')];
        }

        $usdAvailable = $before['usd_balance'];
        if ($usdAvailable === null) {
            return ['ok' => false, 'message' => 'MevonPay USD balance is unavailable.'];
        }
        if ($usdAvailable < $usdAmount) {
            return [
                'ok' => false,
                'message' => 'Insufficient USD on MevonPay. Available: $'.number_format($usdAvailable, 2).'.',
            ];
        }

        Log::info('mevonpay.admin_fx.sell_usd', [
            'admin_id' => $adminId,
            'usd_sell' => $usdAmount,
            'usd_before' => $usdAvailable,
            'naira_before' => $before['naira_balance'] ?? null,
        ]);

        $conversion = $this->exchange->convert($usdAmount, 'USD', 'NGN');
        if (! ($conversion['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($conversion['message'] ?? 'MevonPay could not convert USD to NGN.'),
            ];
        }

        $convertedNgn = $this->extractConvertedAmount($conversion, 'NGN');
        $after = $this->balances->forDashboard();
        $this->recordSnapshotAfterTrade('admin_sell_usd');

        return [
            'ok' => true,
            'message' => 'Sold $'.number_format($usdAmount, 2).' USD'.($convertedNgn > 0 ? ' for ₦'.number_format($convertedNgn, 2).' NGN' : '').'.',
            'direction' => 'sell',
            'usd_amount' => $usdAmount,
            'ngn_amount' => $convertedNgn > 0 ? $convertedNgn : null,
            'balances_before' => [
                'naira' => $before['naira_balance'] ?? null,
                'usd' => $usdAvailable,
            ],
            'balances_after' => [
                'naira' => $after['naira_balance'] ?? null,
                'usd' => $after['usd_balance'] ?? null,
            ],
        ];
    }

    public function estimateNgnForUsd(float $usdAmount): float
    {
        $liveRate = $this->rates->ngnPerUsdFresh();
        $rate = ($liveRate !== null && $liveRate > 0)
            ? $liveRate
            : max(1.0, (float) config('virtual_card.auto_fund_ngn_per_usd', 1400));
        $bufferPercent = max(0.0, (float) config('virtual_card.auto_fund_ngn_buffer_percent', 3));

        return ceil($usdAmount * $rate * (1 + ($bufferPercent / 100)));
    }

    public function estimateNgnFromUsd(float $usdAmount): float
    {
        $liveRate = $this->rates->ngnPerUsd();
        $rate = ($liveRate !== null && $liveRate > 0)
            ? $liveRate
            : max(1.0, (float) config('virtual_card.auto_fund_ngn_per_usd', 1400));

        return round($usdAmount * $rate, 2);
    }

    public function maxUsdPerOp(): float
    {
        return max(0.0, (float) config('virtual_card.auto_fund_usd_max_per_op', 500));
    }

    private function recordSnapshotAfterTrade(string $source): void
    {
        try {
            $live = $this->rates->ngnPerUsdFresh();
            if ($live !== null && $live > 0) {
                app(MevonPayFxRateTrackerService::class)->recordLive($live, source: $source);
            }
        } catch (\Throwable) {
            // FX tracking must not block wallet conversion.
        }
    }

    /**
     * @param  array{ok?: bool, data?: mixed, raw?: mixed}  $conversion
     */
    private function extractConvertedAmount(array $conversion, string $toCurrency): float
    {
        $data = $conversion['data'] ?? null;
        if (! is_array($data)) {
            $raw = $conversion['raw'] ?? null;
            if (is_array($raw)) {
                $data = $raw['data'] ?? $raw;
            }
        }

        if (! is_array($data)) {
            return 0.0;
        }

        $to = strtoupper($toCurrency);
        $keys = $to === 'USD'
            ? ['converted_amount', 'usd_amount', 'new_usd_balance']
            : ['converted_amount', 'ngn_amount', 'naira_amount', 'new_balance'];

        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if (is_numeric($value)) {
                return round((float) $value, $to === 'USD' ? 4 : 2);
            }
        }

        return 0.0;
    }
}
