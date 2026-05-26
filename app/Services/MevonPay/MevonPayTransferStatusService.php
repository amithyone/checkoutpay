<?php

namespace App\Services\MevonPay;

use App\Services\MavonPayTransferService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Optional MevonPay transfer status (TSQ) lookup — enabled when transfer_status_path is configured.
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
     *     reference?: string,
     *     raw?: mixed,
     *     http_status?: ?int
     * }
     */
    public function checkStatus(string $reference, ?string $payoutApi = null): array
    {
        if (! $this->isAvailable()) {
            return [
                'available' => false,
                'message' => 'MevonPay transfer status API is not configured. Set MEVONPAY_TRANSFER_STATUS_PATH when your provider documents the endpoint.',
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

        try {
            $response = Http::timeout((int) config('services.mevonpay.timeout_seconds', 20))
                ->connectTimeout((int) config('services.mevonpay.connect_timeout_seconds', 3))
                ->withHeaders([
                    'Authorization' => $this->secretKey(),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($url, array_filter([
                    'reference' => $reference,
                    'payoutApi' => $payoutApi,
                ]));

            $body = (string) $response->body();
            $json = $response->json();
            $normalized = $this->normalizeResponse(
                is_array($json) ? $json : [],
                $body,
                $response->status(),
                $reference,
            );

            return array_merge([
                'available' => true,
                'message' => 'Provider status retrieved.',
            ], $normalized);
        } catch (\Throwable $e) {
            Log::warning('MevonPay transfer status check failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return [
                'available' => true,
                'message' => $e->getMessage(),
                'bucket' => MavonPayTransferService::BUCKET_FAILED,
                'response_code' => null,
                'response_message' => $e->getMessage(),
                'reference' => $reference,
                'raw' => null,
                'http_status' => null,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{bucket: string, response_code: ?string, response_message: ?string, reference: string, raw: mixed, http_status: int}
     */
    private function normalizeResponse(array $json, string $body, int $httpStatus, string $reference): array
    {
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

        if ($httpStatus < 200 || $httpStatus >= 300) {
            $bucket = MavonPayTransferService::BUCKET_FAILED;
            if ($code !== '' && in_array($code, ['09', '90', '99'], true)) {
                $bucket = MavonPayTransferService::BUCKET_PENDING;
            }
        }

        return [
            'bucket' => $bucket,
            'response_code' => $code !== '' ? $code : null,
            'response_message' => $message !== '' ? $message : null,
            'reference' => $reference,
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

        if (str_contains($lower, 'transfer successful') || str_contains($lower, 'successfully')) {
            return true;
        }

        return $statusFlag === true && str_contains($lower, 'successful');
    }

    private function statusPath(): string
    {
        $path = (string) config('services.mevonpay.transfer_status_path', '');

        return $path !== '' && str_starts_with($path, '/') ? $path : '';
    }

    private function baseUrl(): string
    {
        return (string) config('services.mevonpay.base_url', '');
    }

    private function secretKey(): string
    {
        return (string) config('services.mevonpay.secret_key', '');
    }
}
