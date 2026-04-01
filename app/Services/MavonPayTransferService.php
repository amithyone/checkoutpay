<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MavonPayTransferService
{
    public const PROVIDER = 'mevonpay';

    public const BUCKET_SUCCESSFUL = 'successful';
    public const BUCKET_PENDING = 'pending';
    public const BUCKET_FAILED = 'failed';

    protected string $baseUrl;
    protected string $secretKey;
    protected string $debitAccountName;
    protected string $debitAccountNumber;
    protected string $currentPassword;
    protected int $timeoutSeconds;
    protected int $connectTimeoutSeconds;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.mevonpay.base_url', '');
        $this->secretKey = (string) config('services.mevonpay.secret_key', '');
        $this->debitAccountName = (string) config('services.mevonpay.debit_account_name', '');
        $this->debitAccountNumber = (string) config('services.mevonpay.debit_account_number', '');
        $this->currentPassword = (string) config('services.mevonpay.current_password', '');
        $this->timeoutSeconds = (int) config('services.mevonpay.timeout_seconds', 20);
        $this->connectTimeoutSeconds = (int) config('services.mevonpay.connect_timeout_seconds', 3);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->secretKey !== '';
    }

    /**
     * Create a transfer via MavonPay /V1/createtransfer.
     *
     * Expected args:
     * - amount (int|float|string): amount in NGN (as required by provider)
     * - bankCode, bankName, creditAccountName, creditAccountNumber
     * - narration, reference, sessionId
     */
    public function createTransfer(array $args): array
    {
        if (! $this->isConfigured()) {
            return [
                'bucket' => self::BUCKET_FAILED,
                'response_code' => null,
                'response_message' => 'MavonPay is not configured.',
                'reference' => $args['reference'] ?? null,
                'raw' => null,
                'http_status' => null,
            ];
        }

        $payload = [
            'amount' => $args['amount'] ?? null,
            'bankCode' => $args['bankCode'] ?? null,
            'bankName' => $args['bankName'] ?? null,
            'creditAccountName' => $args['creditAccountName'] ?? null,
            'creditAccountNumber' => $args['creditAccountNumber'] ?? null,
            'debitAccountName' => $this->debitAccountName,
            'debitAccountNumber' => $this->debitAccountNumber,
            'narration' => $args['narration'] ?? null,
            'reference' => $args['reference'] ?? null,
            'sessionId' => $args['sessionId'] ?? null,
            'currentPassword' => $this->currentPassword,
        ];

        $url = rtrim($this->baseUrl, '/') . '/V1/createtransfer';

        try {
            $resp = Http::withHeaders([
                    'Authorization' => $this->secretKey,
                ])
                ->acceptJson()
                ->asJson()
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->retry(1, 0, throw: false)
                ->post($url, $payload);

            $raw = $resp->json();
            $body = trim((string) $resp->body());
            $code = (string) ($raw['responseCode'] ?? $raw['code'] ?? '');
            $message = (string) ($raw['responseMessage'] ?? $raw['message'] ?? '');
            $statusFlag = $raw['status'] ?? null;

            $bucket = self::BUCKET_FAILED;
            if ($code === '00') {
                $bucket = self::BUCKET_SUCCESSFUL;
            } elseif (in_array($code, ['09', '90', '99'], true)) {
                $bucket = self::BUCKET_PENDING;
            }

            // MevonPay sometimes omits responseCode on successful transfers but still returns a success message.
            if ($code === '') {
                $looksSuccessful = str_contains(strtolower($message), 'transfer successful')
                    || ($statusFlag === true && str_contains(strtolower($message), 'successful'));
                if ($looksSuccessful) {
                    $bucket = self::BUCKET_SUCCESSFUL;
                    $code = '00';
                }
            }

            // Some successful MevonPay transfers return empty body/JSON; treat 2xx + empty payload as success fallback.
            if ($resp->successful() && $code === '' && $body === '') {
                $bucket = self::BUCKET_SUCCESSFUL;
                $code = '00';
                $message = 'Empty body fallback treated as successful transfer.';
            }

            if (! $resp->successful()) {
                Log::warning('MavonPay createtransfer non-2xx response', [
                    'http_status' => $resp->status(),
                    'response' => $raw,
                ]);

                $bucket = self::BUCKET_FAILED;
                if ($code !== '' && in_array($code, ['09', '90', '99'], true)) {
                    $bucket = self::BUCKET_PENDING;
                }
            }

            Log::info('MavonPay createtransfer normalized result', [
                'reference' => $payload['reference'],
                'bucket' => $bucket,
                'response_code' => $code !== '' ? $code : null,
                'http_status' => $resp->status(),
            ]);

            return [
                'bucket' => $bucket,
                'response_code' => $code !== '' ? $code : null,
                'response_message' => $message !== '' ? $message : null,
                'reference' => $payload['reference'],
                'raw' => $raw,
                'http_status' => $resp->status(),
            ];
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            Log::error('MavonPay createtransfer error', [
                'message' => $message,
            ]);

            return [
                'bucket' => self::BUCKET_FAILED,
                'response_code' => null,
                'response_message' => $message,
                'reference' => $payload['reference'],
                'raw' => null,
                'http_status' => null,
            ];
        }
    }
}

