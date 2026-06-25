<?php

namespace App\Services\MevonPay;

use App\Services\MavonPayTransferService;
use App\Services\Whatsapp\WhatsappBankTransferReceiptDetails;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
* Tier 2 WhatsApp wallet bank payouts via POST /V1/payout (debit user's permanent Rubies VA).
*/
class MevonPayPayoutService
{
    protected string $baseUrl;

    protected string $secretKey;

    protected string $currentPassword;

    protected int $timeoutSeconds;

    protected int $connectTimeoutSeconds;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.mevonpay.base_url', '');
        $this->secretKey = (string) config('services.mevonpay.secret_key', '');
        $this->currentPassword = (string) config('services.mevonpay.current_password', '');
        $this->timeoutSeconds = (int) config('services.mevonpay.timeout_seconds', 20);
        $this->connectTimeoutSeconds = (int) config('services.mevonpay.connect_timeout_seconds', 3);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->secretKey !== '' && $this->currentPassword !== '';
    }

    /**
    * @param  array{
    *   amount: float|int|string,
    *   bankCode: string,
    *   bankName: string,
    *   creditAccountName: string,
    *   creditAccountNumber: string,
    *   debitAccountNumber: string,
    *   debitAccountName: string,
    *   narration?: string,
    *   reference: string
    * }  $args
    * @return array{bucket: string, response_code: ?string, response_message: ?string, reference: ?string, raw: mixed, http_status: ?int}
    */
    public function createPayout(array $args): array
    {
        if (! $this->isConfigured()) {
            return [
                'bucket' => MavonPayTransferService::BUCKET_FAILED,
                'response_code' => null,
                'response_message' => 'MevonPay payout is not configured.',
                'reference' => $args['reference'] ?? null,
                'raw' => null,
                'http_status' => null,
            ];
        }

        $debitNumber = trim((string) ($args['debitAccountNumber'] ?? ''));
        $debitName = trim((string) ($args['debitAccountName'] ?? ''));
        if ($debitNumber === '' || $debitName === '') {
            return [
                'bucket' => MavonPayTransferService::BUCKET_FAILED,
                'response_code' => null,
                'response_message' => 'Debit account details are required for payout.',
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
            'debitAccountNumber' => $debitNumber,
            'debitAccountName' => $debitName,
            'narration' => $args['narration'] ?? 'WhatsApp wallet bank transfer',
            'reference' => $args['reference'] ?? null,
            'currentPassword' => $this->currentPassword,
        ];

        $url = rtrim($this->baseUrl, '/').'/V1/payout';
        $authHeader = str_starts_with($this->secretKey, 'Token ')
            ? $this->secretKey
            : 'Token '.$this->secretKey;

        try {
            $resp = Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->acceptJson()
                ->asJson()
                ->connectTimeout($this->connectTimeoutSeconds)
                ->timeout($this->timeoutSeconds)
                ->retry(1, 0, throw: false)
                ->post($url, $payload);

            return $this->normalizeResponse($resp->json(), (string) $resp->body(), $resp->status(), (string) ($payload['reference'] ?? ''));
        } catch (\Throwable $e) {
            $ambiguous = MevonPayTransportErrorClassifier::isAmbiguousTransportFailure($e);

            Log::error('MevonPay payout error', [
                'message' => $e->getMessage(),
                'reference' => $payload['reference'],
                'ambiguous_transport' => $ambiguous,
            ]);

            return [
                'bucket' => $ambiguous
                    ? MavonPayTransferService::BUCKET_PENDING
                    : MavonPayTransferService::BUCKET_FAILED,
                'response_code' => null,
                'response_message' => $ambiguous
                    ? 'Transfer status unknown (provider timeout). We will confirm with the bank before updating your wallet.'
                    : $e->getMessage(),
                'reference' => $payload['reference'],
                'raw' => null,
                'http_status' => null,
                'provider_response_unknown' => $ambiguous,
            ];
        }
    }

    /**
    * @param  mixed  $raw
    * @return array{bucket: string, response_code: ?string, response_message: ?string, reference: string, raw: mixed, http_status: int}
    */
    private function normalizeResponse(mixed $raw, string $body, int $httpStatus, string $reference): array
    {
        $json = is_array($raw) ? $raw : [];
        $code = (string) ($json['responseCode'] ?? $json['code'] ?? '');
        $message = (string) ($json['responseMessage'] ?? $json['message'] ?? '');
        $statusFlag = $json['status'] ?? null;

        $bucket = MavonPayTransferService::BUCKET_FAILED;
        if ($code === '00') {
            $bucket = MavonPayTransferService::BUCKET_SUCCESSFUL;
        } elseif (in_array($code, ['09', '90', '99'], true)) {
            $bucket = MavonPayTransferService::BUCKET_PENDING;
        }

        if ($this->responseLooksSuccessful($message, $statusFlag) && $bucket !== MavonPayTransferService::BUCKET_SUCCESSFUL) {
            $bucket = MavonPayTransferService::BUCKET_SUCCESSFUL;
            $code = $code !== '' ? $code : '00';
        }

        if ($code === '' && $this->responseLooksSuccessful($message, $statusFlag)) {
            $bucket = MavonPayTransferService::BUCKET_SUCCESSFUL;
            $code = '00';
        }

        if ($httpStatus >= 200 && $httpStatus < 300 && $code === '' && trim($body) === '') {
            $bucket = MavonPayTransferService::BUCKET_SUCCESSFUL;
            $code = '00';
            $message = 'Empty body fallback treated as successful payout.';
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $bucket = MavonPayTransferService::BUCKET_FAILED;
            if ($code !== '' && in_array($code, ['09', '90', '99'], true)) {
                $bucket = MavonPayTransferService::BUCKET_PENDING;
            }
            Log::warning('MevonPay payout non-2xx response', ['http_status' => $httpStatus, 'response' => $json]);
        }

        Log::info('MevonPay payout normalized result', [
            'reference' => $reference,
            'bucket' => $bucket,
            'response_code' => $code !== '' ? $code : null,
            'http_status' => $httpStatus,
        ]);

        $sessionId = WhatsappBankTransferReceiptDetails::resolveSessionId(
            ['raw' => $json],
            null,
        );

        return [
            'bucket' => $bucket,
            'response_code' => $code !== '' ? $code : null,
            'response_message' => $message !== '' ? $message : null,
            'reference' => $reference,
            'session_id' => $sessionId !== '' ? $sessionId : null,
            'raw' => $json !== [] ? $json : null,
            'http_status' => $httpStatus,
        ];
    }

    private function responseLooksSuccessful(string $message, mixed $statusFlag): bool
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return $statusFlag === true;
        }

        if (str_contains($lower, 'transfer successful') || str_contains($lower, 'successfully') || str_contains($lower, 'payout successful')) {
            return true;
        }

        return $statusFlag === true && str_contains($lower, 'successful');
    }
}
