<?php

namespace App\Services\MevonPay;

use Illuminate\Support\Facades\Log;

/**
 * MevonPay standalone BVN verification: POST /V1/bvn-verify (₦30 per success).
 */
final class MevonBvnVerifyService
{
    public function __construct(
        private MevonPayHttpClient $http,
    ) {}

    public function isConfigured(): bool
    {
        return $this->http->isConfigured();
    }

    /**
     * @return array{
     *   reference: string,
     *   full_name: string,
     *   dob: string,
     *   id_number: string,
     *   photo: string,
     *   raw: array<string, mixed>
     * }
     */
    public function verify(
        string $bvn11,
        string $dobYmd,
        string $firstName,
        string $lastName,
    ): array {
        $bvn = preg_replace('/\D+/', '', $bvn11) ?? '';
        if (strlen($bvn) !== 11) {
            throw new \RuntimeException('BVN must be exactly 11 digits.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobYmd)) {
            throw new \RuntimeException('Date of birth must be YYYY-MM-DD.');
        }
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        if ($firstName === '' || $lastName === '') {
            throw new \RuntimeException('First and last name are required for BVN verification.');
        }

        $path = (string) config('services.mevonpay.bvn_verify_path', '/V1/bvn-verify');
        $payload = [
            'bvn' => $bvn,
            'dob' => $dobYmd,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];

        $result = $this->http->postJson($path, $payload, 'bearer');
        if (! ($result['ok'] ?? false)) {
            $retry = $this->http->postJson($path, $payload, 'raw');
            if ($retry['ok'] ?? false) {
                $result = $retry;
            } else {
                $message = (string) ($result['message'] ?? $retry['message'] ?? 'BVN verification failed.');
                Log::warning('mevonpay.bvn_verify_failed', [
                    'message' => $message,
                    'http_status' => $result['http_status'] ?? $retry['http_status'] ?? null,
                ]);
                throw new \RuntimeException($message !== '' ? $message : 'BVN verification failed.');
            }
        }

        /** @var array<string, mixed> $raw */
        $raw = is_array($result['raw'] ?? null) ? $result['raw'] : [];
        $details = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        if ($details === [] && is_array($result['data'] ?? null)) {
            $details = $result['data'];
        }

        $idNumber = preg_replace('/\D+/', '', (string) ($details['idNumber'] ?? $bvn)) ?? $bvn;

        return [
            'reference' => (string) ($raw['reference'] ?? ''),
            'full_name' => trim((string) ($details['fullName'] ?? '')),
            'dob' => (string) ($details['dob'] ?? $dobYmd),
            'id_number' => strlen($idNumber) === 11 ? $idNumber : $bvn,
            'photo' => (string) ($details['photo'] ?? ''),
            'raw' => $raw,
        ];
    }
}
