<?php

namespace App\Services\MevonPay;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MevonPay standalone BVN verification: POST /V1/bvn-verify.
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
     *   raw: array<string, mixed>
     * }
     */
    public function verify(
        string $bvn11,
        string $dobYmd,
        string $firstName,
        string $lastName,
        ?string $reference = null,
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
        $ref = trim((string) ($reference ?? ''));
        if ($ref === '') {
            $ref = 'BVN-'.Str::upper(Str::random(12));
        }

        $payload = [
            'idNumber' => $bvn,
            'bvn' => $bvn,
            'dob' => $dobYmd,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'reference' => $ref,
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
        $details = is_array($raw['bvn_details'] ?? null) ? $raw['bvn_details'] : [];
        if ($details === [] && is_array($result['data'] ?? null)) {
            $data = $result['data'];
            $details = is_array($data['bvn_details'] ?? null) ? $data['bvn_details'] : $data;
        }

        $idNumber = preg_replace('/\D+/', '', (string) ($details['idNumber'] ?? $details['bvn'] ?? $bvn)) ?? $bvn;

        return [
            'reference' => (string) ($raw['reference'] ?? $ref),
            'full_name' => trim((string) ($details['fullName'] ?? $details['accountName'] ?? '')),
            'dob' => (string) ($details['dob'] ?? $dobYmd),
            'id_number' => strlen($idNumber) === 11 ? $idNumber : $bvn,
            'raw' => $raw,
        ];
    }
}
