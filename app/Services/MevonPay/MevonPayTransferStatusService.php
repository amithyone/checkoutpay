<?php

namespace App\Services\MevonPay;

use App\Services\MavonPayTransferService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MevonPay transfer status (TSQ) via POST /V1/tsk with reference payload.
 */
class MevonPayTransferStatusService
{
    public function isAvailable(): bool
    {
        return $this->statusPath() !== '' && $this->baseUrl() !== '' && $this->secretKey() !== '';
    }

    /**
     * @return array{
     *     available: bool,
     *     message: string,
     *     bucket?: string,
     *     response_code?: ?string,
     *     response_message?: ?string,
     *     transaction_status?: ?string,
     *     reference?: string,
     *     details?: array<string, mixed>,
     *     raw?: mixed,
     *     http_status?: ?int
     * }
     */
    public function checkStatus(string $reference, ?string $payoutApi = null): array
    {
        if (! $this->isAvailable()) {
            return [
                'available' => false,
                'message' => 'MevonPay transfer status API is not configured. Set MEVONPAY_BASE_URL, MEVONPAY_SECRET_KEY, and MEVONPAY_TRANSFER_STATUS_PATH (default /V1/tsk).',
            ];
        }

        $reference = trim($reference);
        if ($reference === '') {
            return [
                'available' => false,
                'message' => 'Missing transfer reference.',
            ];
        }

        $url = rtrim($this->baseUrl(), '/').$this->statusPath();

        $body = array_filter([
            'reference' => $reference,
            'payoutApi' => $payoutApi,
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $response = Http::timeout((int) config('services.mevonpay.timeout_seconds', 30))
                ->connectTimeout((int) config('services.mevonpay.connect_timeout_seconds', 3))
                ->withHeaders([
                    'Authorization' => $this->authorizationHeader(),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $body);

            $json = $response->json();
            if (! is_array($json)) {
                $json = [];
            }

            $normalized = $this->normalizeResponse($json, $response->status(), $reference);

            return array_merge([
                'available' => true,
                'message' => (string) ($json['message'] ?? 'Provider status retrieved.'),
            ], $normalized);
        } catch (\Throwable $e) {
            $ambiguous = MevonPayTransportErrorClassifier::isAmbiguousTransportFailure($e);

            Log::warning('MevonPay transfer status check failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'ambiguous_transport' => $ambiguous,
            ]);

            return [
                'available' => false,
                'skipped' => true,
                'message' => $ambiguous
                    ? 'Provider status check timed out. Will retry later.'
                    : $e->getMessage(),
                'response_code' => null,
                'response_message' => $e->getMessage(),
                'transaction_status' => null,
                'reference' => $reference,
                'details' => [],
                'raw' => null,
                'http_status' => null,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{
     *     bucket: string,
     *     response_code: ?string,
     *     response_message: ?string,
     *     transaction_status: ?string,
     *     reference: string,
     *     details: array<string, mixed>,
     *     raw: array<string, mixed>,
     *     http_status: int
     * }
     */
    private function normalizeResponse(array $json, int $httpStatus, string $reference): array
    {
        $details = is_array($json['details'] ?? null) ? $json['details'] : [];
        $flat = array_merge($json, $details);

        $code = trim((string) (
            $details['responseCode']
            ?? $json['responseCode']
            ?? $flat['code']
            ?? ''
        ));
        $message = trim((string) (
            $details['responseMessage']
            ?? $json['responseMessage']
            ?? $json['message']
            ?? ''
        ));
        $transactionStatus = trim((string) ($details['transactionStatus'] ?? $flat['transactionStatus'] ?? ''));
        $topStatus = strtolower(trim((string) ($json['status'] ?? '')));

        $bucket = $this->resolveBucket($code, $transactionStatus, $topStatus, $message, $httpStatus);

        if ($code === '' && $bucket === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            $code = '00';
        }

        return [
            'bucket' => $bucket,
            'response_code' => $code !== '' ? $code : null,
            'response_message' => $message !== '' ? $message : null,
            'transaction_status' => $transactionStatus !== '' ? $transactionStatus : null,
            'reference' => trim((string) ($json['reference'] ?? $reference)),
            'details' => $details,
            'raw' => $json,
            'http_status' => $httpStatus,
        ];
    }

    private function resolveBucket(
        string $code,
        string $transactionStatus,
        string $topStatus,
        string $message,
        int $httpStatus,
    ): string {
        if ($code === '00') {
            return MavonPayTransferService::BUCKET_SUCCESSFUL;
        }

        if (in_array($code, ['09', '90', '99'], true)) {
            return MavonPayTransferService::BUCKET_PENDING;
        }

        $txLower = strtolower($transactionStatus);
        if (in_array($txLower, ['success', 'successful', 'completed'], true)) {
            return MavonPayTransferService::BUCKET_SUCCESSFUL;
        }
        if (in_array($txLower, ['pending', 'processing', 'in progress', 'in_progress'], true)) {
            return MavonPayTransferService::BUCKET_PENDING;
        }
        if (in_array($txLower, ['failed', 'failure', 'declined', 'reversed'], true)) {
            return MavonPayTransferService::BUCKET_FAILED;
        }

        if ($topStatus === 'success' && $transactionStatus === '') {
            return MavonPayTransferService::BUCKET_SUCCESSFUL;
        }

        if ($this->responseLooksSuccessful($message, $topStatus)) {
            return MavonPayTransferService::BUCKET_SUCCESSFUL;
        }

        if ($httpStatus < 200 || $httpStatus >= 300) {
            return in_array($code, ['09', '90', '99'], true)
                ? MavonPayTransferService::BUCKET_PENDING
                : MavonPayTransferService::BUCKET_FAILED;
        }

        return MavonPayTransferService::BUCKET_FAILED;
    }

    private function responseLooksSuccessful(string $message, string $topStatus): bool
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return $topStatus === 'success';
        }

        if (str_contains($lower, 'verification complete') || str_contains($lower, 'transfer successful')) {
            return true;
        }

        return $topStatus === 'success' && str_contains($lower, 'success');
    }

    private function authorizationHeader(): string
    {
        $key = trim($this->secretKey());
        if ($key === '') {
            return '';
        }

        if (str_starts_with($key, 'Bearer ') || str_starts_with($key, 'Token ')) {
            return $key;
        }

        $style = strtolower((string) config('services.mevonpay.transfer_status_auth', 'bearer'));

        return match ($style) {
            'token' => 'Token '.$key,
            'raw' => $key,
            default => 'Bearer '.$key,
        };
    }

    private function statusPath(): string
    {
        $path = (string) config('services.mevonpay.transfer_status_path', '/V1/tsk');
        if ($path === '') {
            return '';
        }

        return str_starts_with($path, '/') ? $path : '/'.$path;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.mevonpay.base_url', ''), '/');
    }

    private function secretKey(): string
    {
        return trim((string) config('services.mevonpay.secret_key', ''));
    }
}
