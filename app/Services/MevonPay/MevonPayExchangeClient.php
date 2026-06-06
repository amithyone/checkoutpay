<?php

namespace App\Services\MevonPay;

final class MevonPayExchangeClient
{
    public function __construct(
        private MevonPayHttpClient $http,
    ) {}

    public function isConfigured(): bool
    {
        return $this->http->isConfigured();
    }

    /**
     * Convert between MevonPay wallet currencies via POST /V1/exchange (raw auth).
     *
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): array
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));

        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Exchange amount must be greater than zero.'];
        }
        if ($from === '' || $to === '') {
            return ['ok' => false, 'message' => 'Exchange currencies are required.'];
        }

        return $this->http->postJson((string) config('mevonpay_vtu.paths.exchange', '/V1/exchange'), [
            'amount' => round($amount, 2),
            'from_currency' => $from,
            'to_currency' => $to,
        ], 'raw');
    }
}
