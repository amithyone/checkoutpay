<?php

namespace App\Services\VtuNg;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VtuNgApiClient
{
    public function isConfigured(): bool
    {
        if (! config('vtu.enabled', false)) {
            return false;
        }

        return trim((string) config('vtu.username', '')) !== ''
            && trim((string) config('vtu.password', '')) !== '';
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function getBalance(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Bill payments are not configured.'];
        }

        $response = $this->requestGet('/balance', []);

        return $this->parseResponse($response, 'balance');
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseAirtime(string $networkId, string $phone11, float $amount): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Bill payments are not configured.'];
        }

        $response = $this->requestPostJson('/airtime', [
            'request_id' => 'CP-AIR-'.strtoupper(Str::random(14)),
            'service_id' => $networkId,
            'phone' => $phone11,
            'amount' => (int) round($amount, 0),
        ]);

        return $this->parseResponse($response, 'airtime');
    }

    /**
     * @return array{ok: bool, message: string, plans?: list<array{variation_id:int,label:string,price:float,available:bool}>, raw?: mixed}
     */
    public function fetchDataPlans(string $serviceId): array
    {
        $response = $this->requestGet('/variations/data', [
            'service_id' => $serviceId,
        ]);

        $parsed = $this->parseResponse($response, 'variations.data', ['service_id' => $serviceId]);
        if (! $parsed['ok']) {
            return $parsed;
        }

        $rows = $parsed['data'] ?? [];
        if (! is_array($rows)) {
            return ['ok' => false, 'message' => 'Unexpected data plans format.', 'raw' => $parsed['raw'] ?? null];
        }

        $preferReseller = (bool) config('vtu.prefer_reseller_price', false);
        $plans = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $vid = isset($row['variation_id']) ? (int) $row['variation_id'] : 0;
            if ($vid < 1) {
                continue;
            }
            $label = (string) ($row['data_plan'] ?? $row['name'] ?? 'Plan '.$vid);
            $priceRaw = $preferReseller && isset($row['reseller_price']) && $row['reseller_price'] !== ''
                ? $row['reseller_price']
                : ($row['price'] ?? 0);
            $price = round((float) $priceRaw, 2);
            $avail = strtolower((string) ($row['availability'] ?? 'available')) === 'available';
            $plans[] = [
                'variation_id' => $vid,
                'label' => $label,
                'price' => $price,
                'available' => $avail,
            ];
        }

        return ['ok' => true, 'message' => 'OK', 'plans' => $plans, 'raw' => $parsed['raw']];
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseData(string $networkId, string $phone11, int $variationId): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Bill payments are not configured.'];
        }

        $response = $this->requestPostJson('/data', [
            'request_id' => 'CP-DAT-'.strtoupper(Str::random(14)),
            'service_id' => $networkId,
            'phone' => $phone11,
            'variation_id' => $variationId,
        ]);

        return $this->parseResponse($response, 'data_purchase');
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>|null, raw?: mixed}
     */
    public function verifyElectricityCustomer(string $serviceId, string $meterNumber, string $variationId): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Bill payments are not configured.'];
        }

        $response = $this->requestPostJson('/verify-customer', [
            'request_id' => 'CP-VEL-'.strtoupper(Str::random(14)),
            'service_id' => $serviceId,
            'customer_id' => $meterNumber,
            'variation_id' => $variationId,
        ]);

        return $this->parseResponse($response, 'verify_customer', [
            'service_id' => $serviceId,
            'variation_id' => $variationId,
        ]);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseElectricity(
        string $serviceId,
        string $meterNumber,
        string $phone11,
        float $amount,
        string $variationId
    ): array {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Bill payments are not configured.'];
        }

        $response = $this->requestPostJson('/electricity', [
            'request_id' => 'CP-EL-'.strtoupper(Str::random(14)),
            'service_id' => $serviceId,
            'meter_number' => $meterNumber,
            'phone' => $phone11,
            'amount' => (int) round($amount, 0),
            'variation_id' => $variationId,
        ]);

        return $this->parseResponse($response, 'electricity');
    }

    private function url(string $path): string
    {
        return rtrim((string) config('vtu.base_url'), '/').'/'.ltrim($path, '/');
    }

    private function jwtAuthUrl(): string
    {
        $override = config('vtu.jwt_token_url');
        if (is_string($override) && $override !== '') {
            return rtrim($override, '/');
        }
        $base = rtrim((string) config('vtu.base_url'), '/');
        if (str_ends_with($base, '/api/v2')) {
            return substr($base, 0, -strlen('/api/v2')).'/jwt-auth/v1/token';
        }

        return 'https://vtu.ng/wp-json/jwt-auth/v1/token';
    }

    private function forgetJwtCache(): void
    {
        $user = trim((string) config('vtu.username'));
        if ($user !== '') {
            Cache::forget('vtu.ng.jwt.'.sha1($user));
        }
    }

    /**
     * Obtain JWT for WordPress REST; cached to avoid hitting /token on every request.
     */
    private function fetchJwt(): ?string
    {
        $user = trim((string) config('vtu.username'));
        $pass = (string) config('vtu.password');
        if ($user === '' || $pass === '') {
            return null;
        }
        $cacheKey = 'vtu.ng.jwt.'.sha1($user);
        try {
            return Cache::remember($cacheKey, now()->addHours(12), function () use ($user, $pass) {
                $res = Http::timeout((int) config('vtu.timeout', 60))
                    ->acceptJson()
                    ->asJson()
                    ->post($this->jwtAuthUrl(), [
                        'username' => $user,
                        'password' => $pass,
                    ]);
                $j = $res->json();
                if (! is_array($j) || empty($j['token']) || ! is_string($j['token'])) {
                    Log::warning('vtu.ng.jwt_obtain_failed', [
                        'status' => $res->status(),
                        'body_preview' => substr($res->body(), 0, 500),
                    ]);
                    throw new \RuntimeException('VTU.ng JWT not returned');
                }

                return $j['token'];
            });
        } catch (\Throwable) {
            return null;
        }
    }

    private function authedHttp(): ?PendingRequest
    {
        $token = $this->fetchJwt();
        if ($token === null || $token === '') {
            return null;
        }

        return Http::timeout((int) config('vtu.timeout', 60))
            ->acceptJson()
            ->withToken($token);
    }

    private function baseHttp(): PendingRequest
    {
        return Http::timeout((int) config('vtu.timeout', 60))->acceptJson();
    }

    /**
     * @param  array<string, string>  $query
     */
    private function requestGet(string $path, array $query): Response
    {
        $http = $this->authedHttp();
        if ($http !== null) {
            $response = $http->get($this->url($path), $query);
            if ($response->status() !== 401) {
                return $response;
            }
            $this->forgetJwtCache();
            $http = $this->authedHttp();
            if ($http !== null) {
                $response = $http->get($this->url($path), $query);
                if ($response->status() !== 401) {
                    return $response;
                }
            }
        }

        return $this->baseHttp()->get($this->url($path), array_merge($this->authQuery(), $query));
    }

    /**
     * @param  array<string, string>  $formFields
     */
    private function requestPostForm(string $path, array $formFields): Response
    {
        $http = $this->authedHttp();
        if ($http !== null) {
            $body = array_merge($this->pinOnlyForm(), $formFields);
            $response = $http->asForm()->post($this->url($path), $body);
            if ($response->status() !== 401) {
                return $response;
            }
            $this->forgetJwtCache();
            $http = $this->authedHttp();
            if ($http !== null) {
                $response = $http->asForm()->post($this->url($path), $body);
                if ($response->status() !== 401) {
                    return $response;
                }
            }
        }

        return $this->baseHttp()
            ->asForm()
            ->post($this->url($path), array_merge($this->authForm(), $formFields));
    }

    /**
     * JSON body (VTU.ng v2 expects this for some routes).
     *
     * @param  array<string, mixed>  $payload
     */
    private function requestPostJson(string $path, array $payload): Response
    {
        $body = array_merge($payload, $this->pinOnlyForm());
        $http = $this->authedHttp();
        if ($http !== null) {
            $response = $http->asJson()->post($this->url($path), $body);
            if ($response->status() !== 401) {
                return $response;
            }
            $this->forgetJwtCache();
            $http = $this->authedHttp();
            if ($http !== null) {
                $response = $http->asJson()->post($this->url($path), $body);
                if ($response->status() !== 401) {
                    return $response;
                }
            }
        }

        return $this->baseHttp()
            ->asJson()
            ->post($this->url($path), array_merge($body, [
                'username' => (string) config('vtu.username'),
                'password' => (string) config('vtu.password'),
            ]));
    }

    /**
     * @return array<string, string>
     */
    private function pinOnlyForm(): array
    {
        $pin = trim((string) config('vtu.pin', ''));

        return $pin !== '' ? ['pin' => $pin] : [];
    }

    /**
     * @return array<string, string>
     */
    private function authQuery(): array
    {
        return $this->authParams();
    }

    /**
     * @return array<string, string>
     */
    private function authForm(): array
    {
        return $this->authParams();
    }

    /**
     * Username, password, and optional PIN for VTU.ng (query or form, per their API).
     *
     * @return array<string, string>
     */
    private function authParams(): array
    {
        $out = [
            'username' => (string) config('vtu.username'),
            'password' => (string) config('vtu.password'),
        ];
        $pin = trim((string) config('vtu.pin', ''));
        if ($pin !== '') {
            $out['pin'] = $pin;
        }

        return $out;
    }

    /**
     * @param  array<string, scalar|null>  $context
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    private function parseResponse(Response $response, string $operation, array $context = []): array
    {
        $body = $response->body();
        $json = $response->json();

        if (! is_array($json)) {
            Log::warning('vtu.ng.non_json', [
                'operation' => $operation,
                'context' => $context,
                'status' => $response->status(),
                'body' => substr($body, 0, 500),
            ]);

            return ['ok' => false, 'message' => 'Invalid response from bill provider.', 'raw' => $body];
        }

        $this->logVtuNgResponse($operation, $response->status(), $body, $json, $context);

        $code = strtolower((string) ($json['code'] ?? ''));
        if ($code === 'success') {
            return [
                'ok' => true,
                'message' => (string) ($json['message'] ?? 'Success'),
                'data' => $json['data'] ?? null,
                'raw' => $json,
            ];
        }

        $msg = (string) ($json['message'] ?? $json['error'] ?? 'Request failed');

        return ['ok' => false, 'message' => $msg, 'data' => $json['data'] ?? null, 'raw' => $json];
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, scalar|null>  $context
     */
    private function logVtuNgResponse(string $operation, int $httpStatus, string $body, array $json, array $context): void
    {
        $maxBody = (int) config('vtu.log_response_body_max_chars', 12000);
        $data = $json['data'] ?? null;
        $dataInfo = null;
        if (is_array($data)) {
            $dataInfo = ['data_is_array' => true, 'data_count' => count($data)];
        }

        $responseBody = null;
        if ($maxBody > 0) {
            $responseBody = strlen($body) > $maxBody
                ? substr($body, 0, $maxBody).'…[truncated]'
                : $body;
        }

        $log = [
            'operation' => $operation,
            'http_status' => $httpStatus,
            'api_code' => $json['code'] ?? null,
            'api_message' => $json['message'] ?? $json['error'] ?? null,
            'context' => $context === [] ? null : $context,
            'data_info' => $dataInfo,
            'response_body' => $responseBody,
        ];

        Log::info('vtu.ng.response', $log);
    }
}
