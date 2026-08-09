<?php

namespace App\Services\MevonPay;

use Illuminate\Support\Facades\Cache;

final class MevonPayExchangeRateService
{
    public const CACHE_KEY = 'mevonpay_usd_ngn_rate';

    public const STALE_CACHE_KEY = 'mevonpay_usd_ngn_rate_stale';

    public function __construct(
        private MevonPayExchangeClient $exchange,
    ) {}

    /**
     * Live MevonPay NGN per 1 USD from POST /V1/exchange (cached).
     * Failures do not overwrite a good cache entry with null.
     */
    public function ngnPerUsd(): ?float
    {
        if (! $this->exchange->isConfigured()) {
            return null;
        }

        $cacheSeconds = max(60, (int) config('virtual_card.mevon_rate_cache_seconds', 600));

        return $this->fetchAndCacheNgnPerUsd($cacheSeconds, forceRefresh: false);
    }

    /** Bypass cache — for admin live rate tracker polling. */
    public function ngnPerUsdFresh(): ?float
    {
        if (! $this->exchange->isConfigured()) {
            return null;
        }

        Cache::forget(self::CACHE_KEY);

        $cacheSeconds = max(60, (int) config('virtual_card.mevon_rate_cache_seconds', 600));

        return $this->fetchAndCacheNgnPerUsd($cacheSeconds, forceRefresh: true);
    }

    private function fetchAndCacheNgnPerUsd(int $cacheSeconds, bool $forceRefresh): ?float
    {
        if (! $forceRefresh) {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_numeric($cached) && (float) $cached > 0) {
                return (float) $cached;
            }
        }

        $response = $this->exchange->convert(1, 'NGN', 'USD');
        if ($response['ok'] ?? false) {
            $rate = $this->extractRate($response);
            if ($rate !== null && $rate > 0) {
                $rounded = round($rate, 4);
                Cache::put(self::CACHE_KEY, $rounded, $cacheSeconds);
                Cache::put(self::STALE_CACHE_KEY, $rounded, now()->addDays(7));

                try {
                    app(\App\Services\Admin\MevonPayFxRateTrackerService::class)
                        ->recordLive($rounded, source: 'mevon_live');
                } catch (\Throwable) {
                    // Tracking must not break live rate fetch.
                }

                return $rounded;
            }
        }

        $stale = Cache::get(self::STALE_CACHE_KEY);
        if (is_numeric($stale) && (float) $stale > 0) {
            // Keep serving last good rate briefly so dashboard/FX publish do not block.
            Cache::put(self::CACHE_KEY, (float) $stale, min(120, $cacheSeconds));

            return (float) $stale;
        }

        return null;
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
