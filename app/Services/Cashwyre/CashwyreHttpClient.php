<?php

namespace App\Services\Cashwyre;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class CashwyreHttpClient
{
    public function isConfigured(): bool
    {
        return rtrim((string) config('cashwyre.base_url', ''), '/') !== ''
            && trim((string) config('cashwyre.api_key', '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed, http_status?: int}
     */
    public function postJson(string $path, array $payload = []): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Cashwyre is not configured.'];
        }

        $url = $this->url($path);
        $timeout = (int) config('cashwyre.timeout_seconds', 30);
        $connect = (int) config('cashwyre.connect_timeout_seconds', 5);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($connect)
                ->acceptJson()
                ->withHeaders($this->authHeaders())
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('cashwyre.http_failed', ['path' => $path, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Could not reach Cashwyre.'];
        }

        return $this->parseResponse($response, $path);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('cashwyre.base_url', ''), '/').'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        $apiKey = trim((string) config('cashwyre.api_key', ''));
        if ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
            $headers['X-API-Key'] = $apiKey;
        }

        $secret = trim((string) config('cashwyre.secret_key', ''));
        if ($secret !== '') {
            $headers['X-Secret-Key'] = $secret;
        }

        return $headers;
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed, http_status?: int}
     */
    private function parseResponse(Response $response, string $path): array
    {
        $json = $response->json();
        $body = is_array($json) ? $json : [];
        $httpStatus = $response->status();

        $success = $response->successful();
        if (array_key_exists('success', $body)) {
            $success = (bool) $body['success'];
        } elseif (array_key_exists('status', $body) && is_string($body['status'])) {
            $success = in_array(strtolower($body['status']), ['success', 'ok', 'approved', 'active'], true);
        }

        $message = trim((string) ($body['message'] ?? $body['msg'] ?? ''));
        if ($message === '') {
            $message = $success ? 'OK' : 'Cashwyre request failed.';
        }

        $data = $body['data'] ?? $body['result'] ?? ($success ? $body : null);

        if (! $success) {
            Log::warning('cashwyre.api_error', [
                'path' => $path,
                'http_status' => $httpStatus,
                'message' => $message,
            ]);
        }

        return [
            'ok' => $success,
            'message' => $message,
            'data' => $data,
            'raw' => $body,
            'http_status' => $httpStatus,
        ];
    }
}
