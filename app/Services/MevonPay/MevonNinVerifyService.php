<?php

namespace App\Services\MevonPay;

use App\Models\MevonPayLedgerEntry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MevonPay standalone NIN verification: POST /V1/nin-verify (₦50 per success).
 */
final class MevonNinVerifyService
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
        string $nin11,
        string $dobYmd,
        string $firstName,
        string $lastName,
        ?string $reference = null,
    ): array {
        $nin = preg_replace('/\D+/', '', $nin11) ?? '';
        if (strlen($nin) !== 11) {
            throw new \RuntimeException('NIN must be exactly 11 digits.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobYmd)) {
            throw new \RuntimeException('Date of birth must be YYYY-MM-DD.');
        }
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        if ($firstName === '' || $lastName === '') {
            throw new \RuntimeException('First and last name are required for NIN verification.');
        }

        $path = (string) config('services.mevonpay.nin_verify_path', '/V1/nin-verify');
        $ref = trim((string) ($reference ?? ''));
        if ($ref === '') {
            $ref = 'NIN-'.Str::upper(Str::random(12));
        }

        // Auth section of Mevon docs uses Bearer; some Mevon endpoints accept raw key.
        $payload = [
            'idNumber' => $nin,
            'dob' => $dobYmd,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'reference' => $ref,
        ];

        $result = $this->http->postJson($path, $payload, 'bearer');
        if (! ($result['ok'] ?? false)) {
            // Retry with raw Authorization (same style as createtransfer / createrubies).
            $retry = $this->http->postJson($path, $payload, 'raw');
            if ($retry['ok'] ?? false) {
                $result = $retry;
            } else {
                $message = (string) ($result['message'] ?? $retry['message'] ?? 'NIN verification failed.');
                Log::warning('mevonpay.nin_verify_failed', [
                    'message' => $message,
                    'http_status' => $result['http_status'] ?? $retry['http_status'] ?? null,
                ]);
                throw new \RuntimeException($message !== '' ? $message : 'NIN verification failed.');
            }
        }

        /** @var array<string, mixed> $raw */
        $raw = is_array($result['raw'] ?? null) ? $result['raw'] : [];
        $details = is_array($raw['nin_details'] ?? null) ? $raw['nin_details'] : [];
        if ($details === [] && is_array($result['data'] ?? null)) {
            $data = $result['data'];
            $details = is_array($data['nin_details'] ?? null) ? $data['nin_details'] : $data;
        }

        $idNumber = preg_replace('/\D+/', '', (string) ($details['idNumber'] ?? $nin)) ?? $nin;

        $outRef = (string) ($raw['reference'] ?? $ref);
        try {
            app(MevonPayLedgerRecorder::class)->recordNgnDrain(
                MevonPayLedgerEntry::FLOW_IDENTITY_FEE,
                (float) config('mevonpay_fees.nin_verify_fee', 50),
                'id-nin-'.$outRef,
                MevonPayLedgerEntry::PAYOUT_API_IDENTITY,
                null,
                ['kind' => 'nin', 'nin_last4' => substr($nin, -4)],
            );
        } catch (\Throwable $e) {
            Log::warning('mevonpay.nin_verify.ledger_failed', ['error' => $e->getMessage()]);
        }

        return [
            'reference' => $outRef,
            'full_name' => trim((string) ($details['fullName'] ?? '')),
            'dob' => (string) ($details['dob'] ?? $dobYmd),
            'id_number' => strlen($idNumber) === 11 ? $idNumber : $nin,
            'photo' => (string) ($details['photo'] ?? ''),
            'raw' => $raw,
        ];
    }
}
