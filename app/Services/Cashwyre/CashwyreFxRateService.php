<?php

namespace App\Services\Cashwyre;

use Illuminate\Support\Facades\Cache;

final class CashwyreFxRateService
{
    public function __construct(
        private CashwyreHttpClient $http,
    ) {}

    public function isConfigured(): bool
    {
        return $this->http->isConfigured();
    }

    /**
     * Live Cashwyre NGN/USD rates from POST /businessRate/getFxRates (cached).
     *
     * @return array{
     *     ok: bool,
     *     message: string,
     *     currency?: string,
     *     buy_rate?: float,
     *     sell_rate?: float,
     *     mid?: float,
     *     buy_rate_info?: string,
     *     sell_rate_info?: string,
     *     fetched_at?: string
     * }
     */
    public function ngnUsdRates(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Cashwyre is not configured.'];
        }

        $cacheSeconds = max(60, (int) config('cashwyre.fx_rate_cache_seconds', 600));

        return Cache::remember('cashwyre_fx_ngn_usd', $cacheSeconds, function () {
            return $this->fetchNgnUsdRates();
        });
    }

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     currency?: string,
     *     buy_rate?: float,
     *     sell_rate?: float,
     *     mid?: float,
     *     buy_rate_info?: string,
     *     sell_rate_info?: string,
     *     fetched_at?: string
     * }
     */
    public function ngnUsdRatesFresh(): array
    {
        Cache::forget('cashwyre_fx_ngn_usd');

        return $this->ngnUsdRates();
    }

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     currency?: string,
     *     buy_rate?: float,
     *     sell_rate?: float,
     *     mid?: float,
     *     buy_rate_info?: string,
     *     sell_rate_info?: string,
     *     fetched_at?: string
     * }
     */
    private function fetchNgnUsdRates(): array
    {
        $path = (string) config('cashwyre.paths.get_fx_rates', '/businessRate/getFxRates');
        $resp = $this->http->postJson($path, [
            'currency' => 'NGN',
        ], 'cashwyre-fx-ngn-'.time());

        if (! ($resp['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($resp['message'] ?? 'Could not fetch Cashwyre FX rates.'),
            ];
        }

        $row = $this->extractCurrencyRow($resp['data'] ?? null, 'NGN');
        if ($row === null) {
            return ['ok' => false, 'message' => 'Cashwyre did not return NGN FX rates.'];
        }

        $buy = $this->toFloat($row['buyRate'] ?? null);
        $sell = $this->toFloat($row['sellRate'] ?? null);

        if ($buy === null || $sell === null || $buy <= 0 || $sell <= 0) {
            return ['ok' => false, 'message' => 'Cashwyre returned invalid NGN FX rates.'];
        }

        return [
            'ok' => true,
            'message' => 'OK',
            'currency' => 'NGN',
            'buy_rate' => round($buy, 4),
            'sell_rate' => round($sell, 4),
            'mid' => round(($buy + $sell) / 2, 4),
            'buy_rate_info' => trim((string) ($row['buyRateInfo'] ?? '')),
            'sell_rate_info' => trim((string) ($row['sellRateInfo'] ?? '')),
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  mixed  $data
     * @return array<string, mixed>|null
     */
    private function extractCurrencyRow(mixed $data, string $currency): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $needle = strtoupper(trim($currency));

        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }

            $code = strtoupper(trim((string) ($row['currency'] ?? '')));
            if ($code === $needle) {
                return $row;
            }
        }

        return null;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return $float > 0 ? $float : null;
    }
}
