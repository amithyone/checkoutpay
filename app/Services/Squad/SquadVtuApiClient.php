<?php

namespace App\Services\Squad;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Squad (GTB) Value Added Services — airtime & data only.
 *
 * @see https://docs.squadco.com/Value-added-services/vas/
 */
final class SquadVtuApiClient
{
    public function isConfigured(): bool
    {
        if (! (bool) config('squad_vtu.enabled', false)) {
            return false;
        }

        return rtrim((string) config('squad_vtu.base_url', ''), '/') !== ''
            && trim((string) config('squad_vtu.secret_key', '')) !== '';
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed, http_status?: int}
     */
    public function purchaseAirtime(string $phone11, float $amountNaira): array
    {
        $min = (float) config('squad_vtu.airtime_min', 50);
        $amount = (int) round($amountNaira);
        if ($amount < (int) $min) {
            return ['ok' => false, 'message' => "Minimum airtime is ₦".number_format($min, 0).'.'];
        }

        return $this->request('POST', (string) config('squad_vtu.paths.airtime', '/vending/purchase/airtime'), [
            'phone_number' => $phone11,
            'amount' => $amount,
        ], 'airtime');
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed, http_status?: int}
     */
    public function purchaseData(string $phone11, string $planCode, float $amountNaira): array
    {
        $planCode = trim($planCode);
        if ($planCode === '') {
            return ['ok' => false, 'message' => 'Data plan code is required.'];
        }

        $amount = (int) round($amountNaira);
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Invalid data plan amount.'];
        }

        return $this->request('POST', (string) config('squad_vtu.paths.data', '/vending/purchase/data'), [
            'phone_number' => $phone11,
            'amount' => $amount,
            'plan_code' => $planCode,
        ], 'data');
    }

    /**
     * @return array{ok: bool, message: string, plans?: list<array<string, mixed>>, raw?: mixed}
     */
    public function fetchDataBundles(string $squadNetwork): array
    {
        $network = strtoupper(trim($squadNetwork));
        if ($network === '') {
            return ['ok' => false, 'message' => 'Network is required.'];
        }

        $cacheKey = 'squad_vtu:data_bundles:'.$network;
        $ttl = (int) config('squad_vtu.data_plans_cache_seconds', 600);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['ok'] ?? false)) {
            return $cached;
        }

        $path = (string) config('squad_vtu.paths.data_bundles', '/vending/data-bundles');
        $result = $this->request('GET', $path.'?network='.urlencode($network), [], 'data_bundles');
        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        $rows = $result['data'] ?? null;
        if (! is_array($rows)) {
            return ['ok' => false, 'message' => 'Unexpected data-bundles response.', 'raw' => $result['raw'] ?? null];
        }

        // Squad returns data as a list of plans.
        $payload = [
            'ok' => true,
            'message' => (string) ($result['message'] ?? 'OK'),
            'plans' => array_values($rows),
            'raw' => $result['raw'] ?? null,
        ];
        Cache::put($cacheKey, $payload, $ttl);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed, http_status?: int}
     */
    private function request(string $method, string $path, array $body, string $operation): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Squad VTU is not configured.'];
        }

        $url = rtrim((string) config('squad_vtu.base_url'), '/').'/'.ltrim($path, '/');
        $timeout = (int) config('squad_vtu.timeout_seconds', 45);
        $connect = (int) config('squad_vtu.connect_timeout_seconds', 5);
        $key = trim((string) config('squad_vtu.secret_key', ''));

        try {
            $pending = Http::timeout($timeout)
                ->connectTimeout($connect)
                ->acceptJson()
                ->withToken($key)
                ->withHeaders(['Content-Type' => 'application/json']);

            $response = match (strtoupper($method)) {
                'GET' => $pending->get($url),
                default => $pending->post($url, $body),
            };
        } catch (\Throwable $e) {
            Log::warning('squad.vtu_http_failed', [
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Could not reach Squad VTU.'];
        }

        return $this->parseResponse($response, $operation);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed, http_status?: int}
     */
    private function parseResponse(Response $response, string $operation): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            Log::warning('squad.vtu_invalid_response', [
                'operation' => $operation,
                'http_status' => $response->status(),
                'body' => substr((string) $response->body(), 0, 400),
            ]);

            return [
                'ok' => false,
                'message' => 'Invalid response from Squad.',
                'raw' => (string) $response->body(),
                'http_status' => $response->status(),
            ];
        }

        $success = (bool) ($json['success'] ?? false);
        $httpOk = $response->successful();
        $message = (string) ($json['message'] ?? ($success ? 'OK' : 'Squad request failed.'));
        $data = $json['data'] ?? null;

        if ($success && $httpOk) {
            // Airtime often returns status=pending while success=true — treat as accepted.
            return [
                'ok' => true,
                'message' => $message !== '' ? $message : 'OK',
                'data' => $data,
                'raw' => $json,
                'http_status' => $response->status(),
            ];
        }

        Log::warning('squad.vtu_rejected', [
            'operation' => $operation,
            'http_status' => $response->status(),
            'message' => $message,
        ]);

        return [
            'ok' => false,
            'message' => $message !== '' ? $message : 'Squad request failed.',
            'data' => $data,
            'raw' => $json,
            'http_status' => $response->status(),
        ];
    }
}
