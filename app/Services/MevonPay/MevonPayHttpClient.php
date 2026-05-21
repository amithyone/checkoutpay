<?php

namespace App\Services\MevonPay;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MevonPayHttpClient
{
    public function isConfigured(): bool
    {
        return rtrim((string) config('services.mevonpay.base_url', ''), '/') !== ''
            && trim((string) config('services.mevonpay.secret_key', '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed, http_status?: int}
     */
    public function postJson(string $path, array $payload, string $authStyle = 'bearer'): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'MevonPay is not configured.'];
        }

        $url = $this->url($path);
        $timeout = (int) config('services.mevonpay.timeout_seconds', 20);
        $connect = (int) config('services.mevonpay.connect_timeout_seconds', 3);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connect)
                ->acceptJson()
                ->withHeaders($this->authHeaders($authStyle))
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('mevonpay.http_failed', ['path' => $path, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Could not reach MevonPay.'];
        }

        return $this->parseResponse($response, $path);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed, http_status?: int}
     */
    public function getBalance(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'MevonPay is not configured.'];
        }

        $path = (string) config('mevonpay_vtu.paths.balance', '/V1/balance');
        $url = $this->url($path);
        $timeout = (int) config('services.mevonpay.timeout_seconds', 20);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders($this->authHeaders('raw'))
                ->post($url, []);
        } catch (\Throwable $e) {
            Log::warning('mevonpay.balance_failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Could not reach MevonPay.'];
        }

        return $this->parseResponse($response, 'balance');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.mevonpay.base_url', ''), '/').'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $style): array
    {
        $key = trim((string) config('services.mevonpay.secret_key', ''));

        return match ($style) {
            'raw' => [
                'Authorization' => $key,
                'Content-Type' => 'application/json',
            ],
            default => [
                'Authorization' => 'Bearer '.$key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        };
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed, http_status?: int}
     */
    private function parseResponse(Response $response, string $operation): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            return [
                'ok' => false,
                'message' => 'Invalid response from MevonPay.',
                'raw' => $response->body(),
                'http_status' => $response->status(),
            ];
        }

        $success = $this->isSuccessStatus($json);
        $message = (string) ($json['message'] ?? $json['msg'] ?? ($success ? 'OK' : 'Request failed'));
        $data = $json['data'] ?? null;

        Log::info('mevonpay.response', [
            'operation' => $operation,
            'http_status' => $response->status(),
            'success' => $success,
            'message' => $message,
        ]);

        if ($success) {
            return [
                'ok' => true,
                'message' => $message,
                'data' => $data,
                'raw' => $json,
                'http_status' => $response->status(),
            ];
        }

        return [
            'ok' => false,
            'message' => $message,
            'data' => $data,
            'raw' => $json,
            'http_status' => $response->status(),
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function isSuccessStatus(array $json): bool
    {
        $status = $json['status'] ?? null;
        if ($status === true || $status === 1 || $status === '1') {
            return true;
        }
        if (is_string($status) && strtolower($status) === 'success') {
            return true;
        }

        $code = $json['code'] ?? null;
        if (is_string($code) && strtolower($code) === 'success') {
            return true;
        }

        $nested = $json['data'] ?? null;
        if (is_array($nested)) {
            $inner = $nested['success'] ?? null;
            if ($inner === true || $inner === 1 || $inner === '1') {
                return true;
            }
        }

        return false;
    }
}
