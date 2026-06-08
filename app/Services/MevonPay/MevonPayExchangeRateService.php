<?php

namespace App\Services\MevonPay;

use Illuminate\Support\Facades\Cache;

final class MevonPayExchangeRateService
{
    public function __construct(
        private MevonPayExchangeClient $exchange,
    ) {}

    /**
     * Live MevonPay NGN per 1 USD from POST /V1/exchange (cached).
     */
    public function ngnPerUsd(): ?float
    {
        if (! $this->exchange->isConfigured()) {
            return null;
        }

        $cacheSeconds = max(60, (int) config('virtual_card.mevon_rate_cache_seconds', 600));

        return Cache::remember('mevonpay_usd_ngn_rate', $cacheSeconds, function () {
            $response = $this->exchange->convert(1, 'NGN', 'USD');
            if (! ($response['ok'] ?? false)) {
                return null;
            }

            $rate = $this->extractRate($response);
            if ($rate !== null && $rate > 0) {
                $rounded = round($rate, 4);
                try {
                    app(\App\Services\Admin\MevonPayFxRateTrackerService::class)
                        ->recordLive($rounded, source: 'mevon_live');
                } catch (\Throwable) {
                    // Tracking must not break live rate fetch.
                }

                return $rounded;
            }

            return null;
        });
    }

    /**
     * @param  array{data?: mixed, raw?: mixed}  $response
     */
    private function extractRate(array $response): ?float
    {
        $data = $response['data'] ?? null;
        if (! is_array($data)) {
            $raw = $response['raw'] ?? null;
            if (is_array($raw)) {
                $data = $raw['data'] ?? $raw;
            }
        }

        if (! is_array($data)) {
            return null;
        }

        $rate = $data['rate'] ?? $data['exchange_rate'] ?? $data['usd_ngn_rate'] ?? null;

        return is_numeric($rate) ? (float) $rate : null;
    }
}
