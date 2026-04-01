<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MevonPayVirtualAccountService
{
    protected string $baseUrl;
    protected string $secretKey;
    protected int $timeoutSeconds;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.mevonpay.base_url', '');
        $this->secretKey = (string) config('services.mevonpay.secret_key', '');
        $this->timeoutSeconds = (int) config('services.mevonpay.timeout_seconds', 20);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->secretKey !== '';
    }

    protected function authorizationHeaderValue(): string
    {
        $key = trim($this->secretKey);
        if ($key === '') {
            return '';
        }

        // Some environments store "Bearer <token>", others store only "<token>".
        if (stripos($key, 'bearer ') === 0) {
            return $key;
        }

        return 'Bearer ' . $key;
    }

    /**
     * Some temporary-VA endpoints expect raw token without "Bearer ".
     */
    protected function rawAuthorizationHeaderValue(): string
    {
        $key = trim($this->secretKey);
        if ($key === '') {
            return '';
        }

        return (string) preg_replace('/^Bearer\s+/i', '', $key);
    }

    /**
     * Create a temporary virtual account (createtempva).
     *
     * @return array Normalized: account_number, account_name, bank_name, bank_code, raw
     */
    public function createTempVa(
        string $fname,
        string $lname,
        ?string $registrationNumber = null,
        ?string $bvn = null
    ): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('MevonPay is not configured (base_url/secret_key missing).');
        }

        $url = rtrim($this->baseUrl, '/') . '/V1/createtempva.php';

        $authorization = $this->rawAuthorizationHeaderValue();

        $payload = [
            'type' => 'rubies',
            'fname' => $fname,
            'lname' => $lname,
        ];
        if (! empty($registrationNumber)) {
            $payload['registration_number'] = $registrationNumber;
        } elseif (! empty($bvn)) {
            // Backward compatibility fallback when registration number is unavailable.
            $payload['bvn'] = $bvn;
        }

        $resp = Http::withHeaders([
                'Authorization' => $authorization,
            ])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeoutSeconds)
            ->post($url, $payload);

        $json = $resp->json();

        if (! $resp->successful()) {
            Log::warning('MevonPay createtempva non-2xx response', [
                'http_status' => $resp->status(),
                'response' => $json,
            ]);
            throw new \RuntimeException('MevonPay createtempva failed: non-2xx response.');
        }

        // Provider can respond:
        // - { "status": true/false, "message": "...", "data": {...} }
        // - or direct { "data": {...} }
        $status = $json['status'] ?? null;
        if ($status !== null && $status === false) {
            throw new \RuntimeException('MevonPay createtempva error: ' . ($json['message'] ?? 'Unknown error'));
        }

        $data = $json['data'] ?? $json;
        if (!is_array($data)) {
            $data = [];
        }

        $accountNumber = $data['account_number'] ?? $data['accountNumber'] ?? null;
        if (!is_string($accountNumber) || trim($accountNumber) === '') {
            throw new \RuntimeException('MevonPay createtempva missing account_number in response.');
        }

        return [
            'account_number' => (string) $accountNumber,
            'account_name' => (string) ($data['account_name'] ?? $data['accountName'] ?? ''),
            'bank_name' => (string) ($data['bank_name'] ?? ''),
            'bank_code' => (string) ($data['bank_code'] ?? $data['bankCode'] ?? ''),
            'raw' => $json,
        ];
    }

    /**
     * Create a dynamic virtual account (createdynamic).
     *
     * This does NOT require BVN (unlike createtempva) and expects:
     *  - amount
     *  - currency (e.g. NGN)
     *
     * @return array Normalized: account_number, account_name, bank_name, bank_code, expires_on, raw
     */
    public function createDynamicVa(float $amount, string $currency = 'NGN'): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('MevonPay is not configured (base_url/secret_key missing).');
        }

        $url = rtrim($this->baseUrl, '/') . '/V1/createdynamic';

        $authorization = $this->authorizationHeaderValue();

        $payload = [
            'amount' => $amount,
            'currency' => $currency,
        ];

        $resp = Http::withHeaders([
            'Authorization' => $authorization,
        ])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeoutSeconds)
            ->post($url, $payload);

        $json = $resp->json();

        if (! $resp->successful()) {
            Log::warning('MevonPay createdynamic non-2xx response', [
                'http_status' => $resp->status(),
                'response' => $json,
            ]);
            throw new \RuntimeException('MevonPay createdynamic failed: non-2xx response.');
        }

        $status = $json['status'] ?? null;
        if ($status !== null && $status === false) {
            throw new \RuntimeException('MevonPay createdynamic error: ' . ($json['message'] ?? 'Unknown error'));
        }

        $data = $json['data'] ?? $json;
        if (!is_array($data)) {
            $data = [];
        }

        return [
            'account_number' => (string) ($data['accountNumber'] ?? $data['account_number'] ?? ''),
            'account_name' => (string) ($data['accountName'] ?? $data['account_name'] ?? ''),
            'bank_name' => (string) ($data['bankName'] ?? $data['bank_name'] ?? ''),
            'bank_code' => (string) ($data['bankCode'] ?? $data['bank_code'] ?? ''),
            'expires_on' => isset($data['expiresOn']) ? (string) $data['expiresOn'] : null,
            'raw' => $json,
        ];
    }
}

