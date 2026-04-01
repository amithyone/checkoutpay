<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MevonRubiesVirtualAccountService
{
    protected string $baseUrl;
    protected string $secretKey;
    protected int $timeoutSeconds;

    public function __construct()
    {
        $this->baseUrl = (string) (config('services.mevonrubies.base_url') ?: config('services.mevonpay.base_url', ''));
        $this->secretKey = (string) (config('services.mevonrubies.secret_key') ?: config('services.mevonpay.secret_key', ''));
        $this->timeoutSeconds = (int) (config('services.mevonrubies.timeout_seconds') ?: config('services.mevonpay.timeout_seconds', 20));
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->secretKey !== '';
    }

    protected function authorizationHeaderValue(): string
    {
        $key = trim($this->secretKey);
        if ($key === '') {
            return '';
        }

        if (stripos($key, 'bearer ') === 0) {
            return $key;
        }

        return 'Bearer ' . $key;
    }

    /**
     * Create a Rubies virtual account using /V1/mevonrubies.
     *
     * Expected payload keys:
     * - action: "create"
     * - fname, lname, phone, dob, bvn, email
     */
    public function createRubiesAccount(array $payload): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('MevonRubies is not configured (base_url/secret_key missing).');
        }

        $url = rtrim($this->baseUrl, '/') . '/V1/mevonrubies';

        $resp = Http::withHeaders([
            'Authorization' => $this->authorizationHeaderValue(),
        ])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeoutSeconds)
            ->post($url, $payload);

        $json = $resp->json();

        if (! $resp->successful()) {
            Log::warning('MevonRubies non-2xx response', [
                'http_status' => $resp->status(),
                'response' => $json,
            ]);
            throw new \RuntimeException('MevonRubies failed: non-2xx response.');
        }

        $status = $json['status'] ?? null;
        if ($status !== null && $status === false) {
            throw new \RuntimeException('MevonRubies error: ' . ($json['message'] ?? 'Unknown error'));
        }

        $data = $json['data'] ?? $json;
        if (!is_array($data)) {
            $data = [];
        }

        $accountNumber = (string) ($data['account_number'] ?? $data['accountNumber'] ?? '');
        if (trim($accountNumber) === '') {
            throw new \RuntimeException('MevonRubies missing account_number in response.');
        }

        return [
            'account_number' => $accountNumber,
            'account_name' => (string) ($data['account_name'] ?? $data['accountName'] ?? ''),
            'bank_name' => (string) ($data['bank_name'] ?? $data['bankName'] ?? ''),
            'bank_code' => (string) ($data['bank_code'] ?? $data['bankCode'] ?? ''),
            'reference' => (string) ($data['reference'] ?? ''),
            'raw' => $json,
        ];
    }

    /**
     * Create renter-specific reusable Rubies account.
     */
    public function createRenterAccount(\App\Models\Renter $renter): array
    {
        $renterName = trim((string) ($renter->name ?? $renter->verified_account_name ?? ''));
        $nameParts = preg_split('/\s+/', $renterName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $fname = (string) ($nameParts[0] ?? '');
        $lname = (string) (count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : $fname);

        if ($fname === '' || $lname === '') {
            throw new \RuntimeException('Renter name is required for Rubies account creation.');
        }
        if (empty($renter->phone)) {
            throw new \RuntimeException('Renter phone is required for Rubies account creation.');
        }
        if (empty($renter->email)) {
            throw new \RuntimeException('Renter email is required for Rubies account creation.');
        }
        if (empty($renter->bvn)) {
            throw new \RuntimeException('Renter BVN is required for Rubies account creation.');
        }

        $dob = '1990-01-01';
        if (! empty($renter->age)) {
            $age = (int) $renter->age;
            if ($age >= 18 && $age <= 120) {
                $dob = now()->subYears($age)->toDateString();
            }
        }

        return $this->createRubiesAccount([
            'action' => 'create',
            'fname' => $fname,
            'lname' => $lname,
            'phone' => (string) $renter->phone,
            'dob' => $dob,
            'bvn' => (string) $renter->bvn,
            'email' => (string) $renter->email,
        ]);
    }
}

