<?php

namespace App\Services;

use App\Support\MevonPayEgress;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MevonPayVirtualAccountService
{
    protected string $baseUrl;
    protected string $secretKey;
    protected int $timeoutSeconds;
    protected int $connectTimeoutSeconds;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.mevonpay.base_url', '');
        $this->secretKey = (string) config('services.mevonpay.secret_key', '');
        $this->timeoutSeconds = (int) config('services.mevonpay.timeout_seconds', 20);
        $this->connectTimeoutSeconds = (int) config('services.mevonpay.connect_timeout_seconds', 3);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->secretKey !== '';
    }

    protected function shouldLogAccountRequests(): bool
    {
        return (bool) config('services.mevonpay.account_logs_enabled', false);
    }

    protected function authorizationHeaderValue(): string
    {
        $key = trim($this->secretKey);
        if ($key === '') {
            return '';
        }

        $mode = strtolower(trim((string) config('services.mevonpay.temp_va_auth', 'bearer')));
        if (str_starts_with($key, 'Bearer ') || str_starts_with($key, 'Token ')) {
            return $key;
        }

        return $mode === 'raw' ? $key : 'Bearer '.$key;
    }

    /**
     * Create a temporary virtual account (create_tem_va).
     *
     * Payload (Mevon):
     *  - rc_number (CAC RC / BN / IT)
     *  - business_type: RC | BN | IT
     *  - bank_type: e.g. Rubies (mandatory)
     *
     * @return array Normalized: account_number, account_name, bank_name, bank_code, expires_on?, raw
     */
    public function createTempVa(
        ?string $rcNumber = null,
        ?string $businessType = null,
        ?string $bankType = null
    ): array {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('MevonPay is not configured (base_url/secret_key missing).');
        }

        $path = (string) config('services.mevonpay.temp_va_path', '/V1/create_tem_va');
        if ($path === '' || $path[0] !== '/') {
            $path = '/'.ltrim($path, '/');
        }
        $url = rtrim($this->baseUrl, '/').$path;

        $effectiveRc = strtoupper(preg_replace('/\s+/', '', trim((string) ($rcNumber ?: config('services.mevonpay.temp_va_registration_number', '')))) ?? '');
        $effectiveRc = preg_replace('/[^A-Z0-9]/', '', $effectiveRc) ?? '';
        if ($effectiveRc === '') {
            throw new \RuntimeException('MevonPay create_tem_va requires an RC/BN number (platform or business CAC).');
        }

        $type = strtoupper(trim((string) ($businessType ?: '')));
        if (! in_array($type, ['RC', 'BN', 'IT'], true)) {
            $type = $this->inferBusinessTypeFromRc($effectiveRc);
        }

        $bank = trim((string) ($bankType ?: config('services.mevonpay.temp_va_bank_type', 'Rubies')));
        if ($bank === '') {
            $bank = 'Rubies';
        }

        $payload = [
            'rc_number' => $effectiveRc,
            'business_type' => $type,
            'bank_type' => $bank,
        ];

        if ($this->shouldLogAccountRequests()) {
            Log::info('MevonPay create_tem_va request payload', [
                'url' => $url,
                'payload' => $payload,
            ]);
        }

        try {
            $resp = Http::withHeaders(MevonPayEgress::mergeClientHeaders([
                'Authorization' => $this->authorizationHeaderValue(),
            ]))
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeoutSeconds)
                ->connectTimeout($this->connectTimeoutSeconds)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('MevonPay create_tem_va unreachable', ['error' => $e->getMessage()]);
            throw new \RuntimeException('MevonPay is unreachable (timeout). Temporary account numbers cannot be created right now.');
        }

        $json = $resp->json();

        if ($this->shouldLogAccountRequests()) {
            Log::info('MevonPay create_tem_va response payload', [
                'http_status' => $resp->status(),
                'response' => $json,
            ]);
        }

        if (! $resp->successful()) {
            Log::warning('MevonPay create_tem_va non-2xx response', [
                'http_status' => $resp->status(),
                'response' => $json,
            ]);
            throw new \RuntimeException('MevonPay create_tem_va failed: non-2xx response.');
        }

        $status = $json['status'] ?? null;
        if ($status !== null && $status === false) {
            throw new \RuntimeException('MevonPay create_tem_va error: '.($json['message'] ?? 'Unknown error'));
        }

        $data = $json['data'] ?? $json;
        if (! is_array($data)) {
            $data = [];
        }

        $accountNumber = $data['account_number'] ?? $data['accountNumber'] ?? null;
        if (! is_string($accountNumber) || trim($accountNumber) === '') {
            throw new \RuntimeException('MevonPay create_tem_va missing account_number in response.');
        }

        return [
            'account_number' => (string) $accountNumber,
            'account_name' => (string) ($data['account_name'] ?? $data['accountName'] ?? ''),
            'bank_name' => (string) ($data['bank_name'] ?? $data['bankName'] ?? ''),
            'bank_code' => (string) ($data['bank_code'] ?? $data['bankCode'] ?? ''),
            'expires_on' => isset($data['expires_on'])
                ? (string) $data['expires_on']
                : (isset($data['expiresOn']) ? (string) $data['expiresOn'] : null),
            'raw' => $json,
        ];
    }

    public function inferBusinessTypeFromRc(string $rcNumber): string
    {
        $rc = strtoupper(preg_replace('/[^A-Z0-9]/', '', $rcNumber) ?? '');
        if (str_starts_with($rc, 'BN')) {
            return 'BN';
        }
        if (str_starts_with($rc, 'IT')) {
            return 'IT';
        }

        return 'RC';
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

        $authorization = trim($this->secretKey);

        $payload = [
            'amount' => $amount,
            'currency' => $currency,
        ];

        if ($this->shouldLogAccountRequests()) {
            Log::info('MevonPay createdynamic request payload', [
                'url' => $url,
                'payload' => $payload,
            ]);
        }

        try {
            $resp = Http::withHeaders(MevonPayEgress::mergeClientHeaders([
                'Authorization' => $authorization,
            ]))
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeoutSeconds)
                ->connectTimeout($this->connectTimeoutSeconds)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('MevonPay createdynamic unreachable', ['error' => $e->getMessage()]);
            throw new \RuntimeException('MevonPay is unreachable (timeout). Dynamic account numbers cannot be created right now.');
        }

        $json = $resp->json();

        if ($this->shouldLogAccountRequests()) {
            Log::info('MevonPay createdynamic response payload', [
                'http_status' => $resp->status(),
                'response' => $json,
            ]);
        }

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
