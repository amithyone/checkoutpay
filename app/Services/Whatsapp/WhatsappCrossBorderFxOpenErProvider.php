<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * USD-based spot quotes from open.er-api.com (ExchangeRate-API, no key on free tier).
 * Rates are "units of currency per 1 USD" (same as the API's base USD payload).
 *
 * @see https://www.exchangerate-api.com/docs/free
 */
final class WhatsappCrossBorderFxOpenErProvider
{
    public const CACHE_KEY = 'whatsapp_cross_border_fx_open_er_usd_v1';

    public const CACHE_TTL_SECONDS = 3600;

    private const ENDPOINT = 'https://open.er-api.com/v6/latest/USD';

    /**
     * Units of $to per 1 unit of $from (e.g. NGN per 1 USD).
     */
    public function multiplier(string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return 1.0;
        }

        $perUsd = $this->unitsPerOneUsd();
        if ($perUsd === []) {
            return null;
        }
        if (! isset($perUsd[$from], $perUsd[$to])) {
            return null;
        }
        $a = (float) $perUsd[$from];
        $b = (float) $perUsd[$to];
        if ($a <= 0 || $b <= 0) {
            return null;
        }

        return $b / $a;
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, float> currency code => units per 1 USD
     */
    private function unitsPerOneUsd(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
                $response = Http::timeout(12)
                    ->acceptJson()
                    ->get(self::ENDPOINT);
                if (! $response->successful()) {
                    throw new \RuntimeException('HTTP '.$response->status());
                }
                $j = $response->json();
                if (($j['result'] ?? '') !== 'success' || ! is_array($j['rates'] ?? null)) {
                    throw new \RuntimeException('unexpected_payload');
                }
                $out = [];
                foreach ($j['rates'] as $code => $val) {
                    if (! is_string($code) || $code === '' || ! is_numeric($val)) {
                        continue;
                    }
                    $f = (float) $val;
                    if ($f > 0) {
                        $out[strtoupper($code)] = $f;
                    }
                }

                if (count($out) < 5 || ! isset($out['USD'])) {
                    throw new \RuntimeException('too_few_rates');
                }

                return $out;
            });
        } catch (\Throwable $e) {
            Log::warning('whatsapp.fx.open_er_fetch_failed', ['message' => $e->getMessage()]);

            return [];
        }
    }
}
