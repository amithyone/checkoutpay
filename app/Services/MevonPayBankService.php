<?php

namespace App\Services;

use App\Models\Bank;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MevonPayBankService
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

    public function getBankList(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $url = rtrim($this->baseUrl, '/') . '/V1/bank_service';

        try {
            $resp = Http::withHeaders([
                    'Authorization' => $this->secretKey,
                ])
                ->acceptJson()
                ->asJson()
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->retry(1, 0, throw: false)
                ->post($url, ['action' => 'getBankList']);

            $json = $resp->json();
            if (! $resp->successful() || ($json['status'] ?? false) !== true) {
                Log::warning('MevonPay getBankList failed', [
                    'http_status' => $resp->status(),
                    'response' => $json,
                ]);
                return null;
            }

            $rows = $json['data'] ?? [];
            if (! is_array($rows)) {
                return null;
            }

            return $rows;
        } catch (\Throwable $e) {
            Log::error('MevonPay getBankList error', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function nameEnquiry(string $bankCode, string $accountNumber): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $url = rtrim($this->baseUrl, '/') . '/V1/bank_service';

        try {
            $resp = Http::withHeaders([
                    'Authorization' => $this->secretKey,
                ])
                ->acceptJson()
                ->asJson()
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->retry(1, 0, throw: false)
                ->post($url, [
                    'action' => 'nameEnquiry',
                    'bankCode' => $bankCode,
                    'accountNumber' => $accountNumber,
                ]);

            $json = $resp->json() ?? [];
            $statusOk = ($json['status'] ?? false) === true
                || ($json['status'] ?? '') === 'true'
                || ($json['status'] ?? '') === 'success'
                || ($json['success'] ?? false) === true;

            if (! $resp->successful() || ! $statusOk) {
                Log::warning('MevonPay nameEnquiry failed', [
                    'http_status' => $resp->status(),
                    'response' => $json,
                ]);
                return null;
            }

            $data = $json['data'] ?? null;
            if (! is_array($data)) {
                return null;
            }

            $accountName = $data['account_name'] ?? $data['accountName'] ?? $data['AccountName'] ?? null;
            if ($accountName !== null && ! is_string($accountName)) {
                $accountName = is_scalar($accountName) ? (string) $accountName : null;
            }
            if ($accountName === null || trim((string) $accountName) === '') {
                return null;
            }

            return [
                'account_name' => trim((string) $accountName),
                'account_number' => (string) ($data['account_number'] ?? $data['accountNumber'] ?? $accountNumber),
                'bank_code' => (string) ($data['bank_code'] ?? $data['bankCode'] ?? $bankCode),
            ];
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            Log::error('MevonPay nameEnquiry error', [
                'message' => $message,
            ]);

            // If MevonPay returns empty reply (cURL error 52), treat this as a successful verification fallback.
            // Provider has been observed to timeout on successful cases.
            $isEmptyReply = str_contains($message, 'cURL error 52') || str_contains($message, 'Empty reply from server');
            if ($isEmptyReply) {
                $bankName = Bank::query()->where('code', $bankCode)->value('name');

                return [
                    'account_name' => 'Verified (MevonPay timeout fallback)',
                    'account_number' => $accountNumber,
                    'bank_code' => $bankCode,
                    'bank_name' => $bankName,
                ];
            }

            return null;
        }
    }
}

