<?php

namespace App\Services\MevonPay;

use Illuminate\Support\Facades\Log;

final class MevonPayUsdAutoFundService
{
    public function __construct(
        private MevonPayBalanceSnapshotService $balances,
        private MevonPayExchangeClient $exchange,
    ) {}

    /**
     * Top up MevonPay USD float from NGN when below the required amount.
     *
     * @return array{
     *   ok: bool,
     *   message: string,
     *   funded?: bool,
     *   funded_ngn?: float,
     *   funded_usd?: float,
     *   usd_balance_before?: ?float,
     *   usd_balance_after?: ?float
     * }
     */
    public function ensureUsdBalance(float $requiredUsd, string $context = 'virtual_card', bool $forceTopUp = false): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => true, 'message' => 'Auto USD top-up disabled.', 'funded' => false];
        }
        if ($requiredUsd <= 0) {
            return ['ok' => true, 'message' => 'No USD required.', 'funded' => false];
        }
        if (! $this->exchange->isConfigured()) {
            return ['ok' => false, 'message' => 'MevonPay is not configured for USD auto top-up.'];
        }

        $buffer = max(0.0, (float) config('virtual_card.auto_fund_usd_buffer', 1));
        $targetUsd = round($requiredUsd + $buffer, 2);

        $snapshot = $this->balances->forDashboard();
        if (! ($snapshot['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($snapshot['message'] ?? 'Could not read MevonPay balances.'),
            ];
        }

        $usdBefore = $snapshot['usd_balance'];
        $nairaAvailable = $snapshot['naira_balance'];
        if ($usdBefore === null || $nairaAvailable === null) {
            return ['ok' => false, 'message' => 'MevonPay balance response is missing USD or NGN amounts.'];
        }

        if (! $forceTopUp && $usdBefore >= $targetUsd) {
            Log::debug('mevonpay.usd_auto_fund.skipped', [
                'context' => $context,
                'required_usd' => $requiredUsd,
                'target_usd' => $targetUsd,
                'usd_balance' => $usdBefore,
            ]);

            return [
                'ok' => true,
                'message' => 'USD balance is sufficient.',
                'funded' => false,
                'usd_balance_before' => $usdBefore,
                'usd_balance_after' => $usdBefore,
            ];
        }

        $shortfallUsd = round(max($targetUsd - $usdBefore, 0), 2);
        if ($forceTopUp) {
            $forceBuyUsd = max(0.01, (float) config('virtual_card.auto_fund_force_buy_usd', 2));
            $shortfallUsd = max($shortfallUsd, $forceBuyUsd);
        }
        $maxPerOp = max(0.0, (float) config('virtual_card.auto_fund_usd_max_per_op', 500));
        if ($maxPerOp > 0 && $shortfallUsd > $maxPerOp) {
            return [
                'ok' => false,
                'message' => 'Required USD top-up exceeds the configured per-operation limit. Fund MevonPay USD manually or raise the limit.',
            ];
        }

        $ngnEstimate = $this->estimateNgnForUsd($shortfallUsd);
        if ($nairaAvailable < $ngnEstimate) {
            return [
                'ok' => false,
                'message' => 'MevonPay NGN balance is too low to auto-buy USD for card operations.',
            ];
        }

        Log::info('mevonpay.usd_auto_fund.converting', [
            'context' => $context,
            'required_usd' => $requiredUsd,
            'target_usd' => $targetUsd,
            'shortfall_usd' => $shortfallUsd,
            'ngn_attempt' => $ngnEstimate,
            'usd_balance_before' => $usdBefore,
            'force_top_up' => $forceTopUp,
        ]);

        $conversion = $this->exchange->convert($ngnEstimate, 'NGN', 'USD');
        if (! ($conversion['ok'] ?? false)) {
            Log::warning('mevonpay.usd_auto_fund.exchange_failed', [
                'context' => $context,
                'required_usd' => $requiredUsd,
                'shortfall_usd' => $shortfallUsd,
                'ngn_attempt' => $ngnEstimate,
                'force_top_up' => $forceTopUp,
                'message' => $conversion['message'] ?? null,
            ]);

            return [
                'ok' => false,
                'message' => (string) ($conversion['message'] ?? 'Could not convert NGN to USD on MevonPay.'),
            ];
        }

        $convertedUsd = $this->convertedUsdAmount($conversion);
        $fundedNgn = round($ngnEstimate, 2);

        $after = $this->balances->forDashboard();
        $usdAfter = ($after['ok'] ?? false) ? $after['usd_balance'] : null;

        if ($usdAfter !== null && $usdAfter < $targetUsd) {
            $remaining = round($targetUsd - $usdAfter, 2);
            $topUpNgn = $this->estimateNgnForUsd($remaining);
            $nairaLeft = ($after['ok'] ?? false) ? ($after['naira_balance'] ?? 0.0) : 0.0;

            if ($topUpNgn > 0 && $nairaLeft >= $topUpNgn) {
                $second = $this->exchange->convert($topUpNgn, 'NGN', 'USD');
                if ($second['ok'] ?? false) {
                    $fundedNgn = round($fundedNgn + $topUpNgn, 2);
                    $convertedUsd = round($convertedUsd + $this->convertedUsdAmount($second), 4);
                    $after = $this->balances->forDashboard();
                    $usdAfter = ($after['ok'] ?? false) ? $after['usd_balance'] : $usdAfter;
                }
            }
        }

        if ($usdAfter !== null && $usdAfter < $requiredUsd) {
            return [
                'ok' => false,
                'message' => 'Auto USD top-up completed but balance is still below the required amount.',
                'funded' => true,
                'funded_ngn' => $fundedNgn,
                'funded_usd' => $convertedUsd,
                'usd_balance_before' => $usdBefore,
                'usd_balance_after' => $usdAfter,
            ];
        }

        Log::info('mevonpay.usd_auto_fund.success', [
            'context' => $context,
            'required_usd' => $requiredUsd,
            'funded_ngn' => $fundedNgn,
            'funded_usd' => $convertedUsd,
            'usd_balance_before' => $usdBefore,
            'usd_balance_after' => $usdAfter,
        ]);

        return [
            'ok' => true,
            'message' => 'MevonPay USD balance topped up automatically.',
            'funded' => true,
            'funded_ngn' => $fundedNgn,
            'funded_usd' => $convertedUsd,
            'usd_balance_before' => $usdBefore,
            'usd_balance_after' => $usdAfter,
        ];
    }

    /**
     * Buy USD after the provider rejected a card call for insufficient merchant float.
     *
     * @return array{ok: bool, message: string, funded?: bool, funded_ngn?: float, funded_usd?: float, usd_balance_before?: ?float, usd_balance_after?: ?float}
     */
    public function fundAfterProviderInsufficientUsd(float $requiredUsd, string $context): array
    {
        return $this->ensureUsdBalance($requiredUsd, $context, forceTopUp: true);
    }

    public function isInsufficientUsdError(string $message): bool
    {
        $haystack = strtolower(trim($message));
        if ($haystack === '') {
            return false;
        }

        if (str_contains($haystack, 'insufficient')
            && (str_contains($haystack, 'usd')
                || str_contains($haystack, 'dollar')
                || str_contains($haystack, 'balance')
                || str_contains($haystack, 'float'))) {
            return true;
        }

        if (str_contains($haystack, 'low') && str_contains($haystack, 'usd')) {
            return true;
        }

        if (str_contains($haystack, 'merchant') && str_contains($haystack, 'balance')) {
            return true;
        }

        return str_contains($haystack, 'not enough') && str_contains($haystack, 'usd');
    }

    private function isEnabled(): bool
    {
        return (bool) config('virtual_card.auto_fund_usd_enabled', true);
    }

    private function estimateNgnForUsd(float $usdAmount): float
    {
        $liveRate = app(MevonPayExchangeRateService::class)->ngnPerUsd();
        $rate = ($liveRate !== null && $liveRate > 0)
            ? $liveRate
            : max(1.0, (float) config('virtual_card.auto_fund_ngn_per_usd', 1400));
        $bufferPercent = max(0.0, (float) config('virtual_card.auto_fund_ngn_buffer_percent', 3));

        return ceil($usdAmount * $rate * (1 + ($bufferPercent / 100)));
    }

    /**
     * @param  array{ok?: bool, data?: mixed, raw?: mixed}  $conversion
     */
    private function convertedUsdAmount(array $conversion): float
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

        $converted = $data['converted_amount'] ?? $data['usd_amount'] ?? null;

        return is_numeric($converted) ? round((float) $converted, 4) : 0.0;
    }
}
