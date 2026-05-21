<?php

namespace App\Services\MevonPay;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class MevonPayVtuApiClient
{
    public function __construct(
        private MevonPayHttpClient $http,
    ) {}

    public function isConfigured(): bool
    {
        return $this->http->isConfigured()
            && (bool) config('mevonpay_vtu.enabled', true);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getBalance(): array
    {
        return $this->http->getBalance();
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function electricityGetInfo(): array
    {
        return $this->cachedGetInfo('electricity', 'electricity');
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function cableTvGetInfo(): array
    {
        return $this->cachedGetInfo('cabletv', 'cabletv');
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function airtimeGetInfo(): array
    {
        return $this->cachedGetInfo('airtime', 'airtime');
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function dataGetInfo(): array
    {
        return $this->cachedGetInfo('data', 'data');
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function bettingGetInfo(): array
    {
        return $this->cachedGetInfo('betting', 'betting');
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>|null, raw?: mixed}
     */
    public function verifyElectricity(string $providerCode, string $meter, string $planCode): array
    {
        $parsed = $this->http->postJson($this->path('electricity'), [
            'action' => 'verify',
            'meter' => $meter,
            'providerCode' => $providerCode,
            'planCode' => $planCode,
        ]);
        if (! ($parsed['ok'] ?? false)) {
            return $parsed;
        }

        return [
            'ok' => true,
            'message' => $parsed['message'] ?? 'OK',
            'data' => $this->normalizeVerifyPayload($parsed['data'] ?? null),
            'raw' => $parsed['raw'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseElectricity(
        string $providerCode,
        string $meter,
        string $planCode,
        float $amount,
        string $customerName,
        string $phone11,
    ): array {
        return $this->http->postJson($this->path('electricity'), [
            'action' => 'buy',
            'meter' => $meter,
            'providerCode' => $providerCode,
            'planCode' => $planCode,
            'amount' => (int) round($amount, 0),
            'customerName' => $customerName,
            'phone' => $phone11,
        ]);
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>|null, raw?: mixed}
     */
    public function verifyCableTv(string $providerCode, string $smartcard, string $planCode): array
    {
        $parsed = $this->http->postJson($this->path('cabletv'), [
            'action' => 'verify',
            'smartcard' => $smartcard,
            'providerCode' => $providerCode,
            'planCode' => $planCode,
        ]);
        if (! ($parsed['ok'] ?? false)) {
            return $parsed;
        }

        return [
            'ok' => true,
            'message' => $parsed['message'] ?? 'OK',
            'data' => $this->normalizeVerifyPayload($parsed['data'] ?? null),
            'raw' => $parsed['raw'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseCableTv(
        string $providerCode,
        string $smartcard,
        string $planCode,
        float $amount,
        string $customerName,
        string $phone11,
    ): array {
        return $this->http->postJson($this->path('cabletv'), [
            'action' => 'buy',
            'smartcard' => $smartcard,
            'providerCode' => $providerCode,
            'planCode' => $planCode,
            'amount' => (int) round($amount, 0),
            'customerName' => $customerName,
            'phone' => $phone11,
        ]);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseAirtime(string $providerCode, string $phone11, float $amount): array
    {
        return $this->http->postJson($this->path('airtime'), [
            'action' => 'buy',
            'providerCode' => $providerCode,
            'phone' => $phone11,
            'amount' => (int) round($amount, 0),
            'reference' => 'CP-MAIR-'.strtoupper(Str::random(12)),
        ]);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseData(string $providerCode, string $phone11, string $planCode, float $amount): array
    {
        return $this->http->postJson($this->path('data'), [
            'action' => 'buy',
            'providerCode' => $providerCode,
            'phone' => $phone11,
            'planCode' => $planCode,
            'amount' => (int) round($amount, 0),
            'reference' => 'CP-MDAT-'.strtoupper(Str::random(12)),
        ]);
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>|null, raw?: mixed}
     */
    public function verifyBetting(string $providerCode, string $customerId): array
    {
        $parsed = $this->http->postJson($this->path('betting'), [
            'action' => 'verify',
            'providerCode' => $providerCode,
            'customerId' => $customerId,
        ]);
        if (! ($parsed['ok'] ?? false)) {
            return $parsed;
        }

        return [
            'ok' => true,
            'message' => $parsed['message'] ?? 'OK',
            'data' => $this->normalizeVerifyPayload($parsed['data'] ?? null),
            'raw' => $parsed['raw'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseBetting(string $providerCode, string $customerId, float $amount, string $phone11): array
    {
        return $this->http->postJson($this->path('betting'), [
            'action' => 'buy',
            'providerCode' => $providerCode,
            'customerId' => $customerId,
            'amount' => (int) round($amount, 0),
            'phone' => $phone11,
            'reference' => 'CP-MBET-'.strtoupper(Str::random(12)),
        ]);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    private function cachedGetInfo(string $cacheKey, string $pathKey): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'MevonPay VTU is not configured.'];
        }

        $ttl = (int) config('mevonpay_vtu.catalog_cache_seconds', 300);
        $cacheId = 'mevonpay.vtu.getinfo.'.$cacheKey;

        return Cache::remember($cacheId, $ttl, function () use ($pathKey) {
            return $this->http->postJson($this->path($pathKey), ['action' => 'getInfo']);
        });
    }

    private function path(string $key): string
    {
        return (string) config('mevonpay_vtu.paths.'.$key, '/V1/'.$key);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeVerifyPayload(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }
        $inner = $data['data'] ?? $data;
        if (is_array($inner) && isset($inner['data']) && is_array($inner['data'])) {
            $inner = $inner['data'];
        }
        if (! is_array($inner)) {
            return null;
        }

        $name = (string) ($inner['customerName'] ?? $inner['customer_name'] ?? '');
        $id = (string) ($inner['customerId'] ?? $inner['customer_id'] ?? '');

        return array_filter([
            'customer_name' => $name !== '' ? $name : null,
            'customer_id' => $id !== '' ? $id : null,
            'due_date' => $inner['dueDate'] ?? $inner['due_date'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');
    }
}
