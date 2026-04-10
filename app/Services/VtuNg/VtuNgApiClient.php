<?php

namespace App\Services\VtuNg;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $response = Http::timeout((int) config('vtu.timeout', 60))
            ->acceptJson()
            ->get($this->url('/balance'), $this->authQuery());

        return $this->parseResponse($response);
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    public function purchaseAirtime(string $networkId, string $phone11, float $amount): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Bill payments are not configured.'];
        }

        $response = Http::timeout((int) config('vtu.timeout', 60))
            ->acceptJson()
            ->asForm()
            ->post($this->url('/airtime'), array_merge($this->authForm(), [
                'phone' => $phone11,
                'network_id' => $networkId,
                'amount' => (string) (int) round($amount, 0),
            ]));

        return $this->parseResponse($response);
    }

    /**
     * @return array{ok: bool, message: string, plans?: list<array{variation_id:int,label:string,price:float,available:bool}>, raw?: mixed}
     */
    public function fetchDataPlans(string $serviceId): array
    {
        $response = Http::timeout((int) config('vtu.timeout', 60))
            ->acceptJson()
            ->get($this->url('/variations/data'), [
                'service_id' => $serviceId,
            ]);

        $parsed = $this->parseResponse($response);
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

        $response = Http::timeout((int) config('vtu.timeout', 60))
            ->acceptJson()
            ->asForm()
            ->post($this->url('/data'), array_merge($this->authForm(), [
                'phone' => $phone11,
                'network_id' => $networkId,
                'variation_id' => (string) $variationId,
            ]));

        return $this->parseResponse($response);
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>|null, raw?: mixed}
     */
    public function verifyElectricityCustomer(string $serviceId, string $meterNumber, string $variationId): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Bill payments are not configured.'];
        }

        $response = Http::timeout((int) config('vtu.timeout', 60))
            ->acceptJson()
            ->asForm()
            ->post($this->url('/verify-customer'), array_merge($this->authForm(), [
                'service_id' => $serviceId,
                'customer_id' => $meterNumber,
                'variation_id' => $variationId,
            ]));

        return $this->parseResponse($response);
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

        $response = Http::timeout((int) config('vtu.timeout', 60))
            ->acceptJson()
            ->asForm()
            ->post($this->url('/electricity'), array_merge($this->authForm(), [
                'phone' => $phone11,
                'service_id' => $serviceId,
                'meter_number' => $meterNumber,
                'amount' => (string) (int) round($amount, 0),
                'variation_id' => $variationId,
            ]));

        return $this->parseResponse($response);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('vtu.base_url'), '/').'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, string>
     */
    private function authQuery(): array
    {
        return [
            'username' => (string) config('vtu.username'),
            'password' => (string) config('vtu.password'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authForm(): array
    {
        return [
            'username' => (string) config('vtu.username'),
            'password' => (string) config('vtu.password'),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: mixed, raw?: mixed}
     */
    private function parseResponse(Response $response): array
    {
        $body = $response->body();
        $json = $response->json();

        if (! is_array($json)) {
            Log::warning('vtu.ng.non_json', ['status' => $response->status(), 'body' => substr($body, 0, 500)]);

            return ['ok' => false, 'message' => 'Invalid response from bill provider.', 'raw' => $body];
        }

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
}
