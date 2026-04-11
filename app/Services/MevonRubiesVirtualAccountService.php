<?php

namespace App\Services;

use App\Models\Renter;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MevonRubiesVirtualAccountService
{
    protected string $baseUrl;

    protected string $secretKey;

    protected int $timeoutSeconds;

    protected string $createPath;

    public function __construct()
    {
        $this->baseUrl = (string) (config('services.mevonrubies.base_url') ?: config('services.mevonpay.base_url', ''));
        $this->secretKey = (string) (config('services.mevonrubies.secret_key') ?: config('services.mevonpay.secret_key', ''));
        $this->timeoutSeconds = (int) (config('services.mevonrubies.timeout_seconds') ?: config('services.mevonpay.timeout_seconds', 20));
        $this->createPath = (string) config('services.mevonrubies.create_path', '/V1/createrubies');
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

        return 'Bearer '.$key;
    }

    protected function createrubiesUrl(): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($this->createPath, '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function postCreaterubies(array $body): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('MevonRubies is not configured (base_url/secret_key missing).');
        }

        $resp = Http::withHeaders([
            'Authorization' => $this->authorizationHeaderValue(),
        ])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeoutSeconds)
            ->post($this->createrubiesUrl(), $body);

        $json = $resp->json();
        if (! is_array($json)) {
            $json = [];
        }

        if (! $resp->successful()) {
            $errorCtx = $this->formatCreaterubiesHttpError($resp, $json);
            Log::warning('MevonRubies createrubies non-2xx response', $errorCtx);
            Log::channel('whatsapp_wallet_kyc')->warning('MevonRubies createrubies non-2xx response', $errorCtx);
            throw new \RuntimeException('MevonRubies failed: '.$errorCtx['summary_for_exception']);
        }

        if (($json['status'] ?? null) === false) {
            $parts = [];
            if (array_key_exists('code', $json) && $json['code'] !== null && $json['code'] !== '') {
                $c = $json['code'];
                $parts[] = 'code='.(is_scalar($c) ? (string) $c : json_encode($c, JSON_UNESCAPED_UNICODE));
            }
            $parts[] = (string) ($json['message'] ?? $json['error'] ?? 'Unknown error');
            $msg = implode(' — ', array_filter($parts));
            Log::channel('whatsapp_wallet_kyc')->warning('MevonRubies createrubies status=false (HTTP 2xx)', [
                'response' => $json,
            ]);
            throw new \RuntimeException('MevonRubies: '.$msg);
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{http_status: int, response: array<string, mixed>, raw_body_preview: ?string, summary_for_exception: string}
     */
    protected function formatCreaterubiesHttpError(Response $resp, array $json): array
    {
        $status = $resp->status();
        $parts = ["HTTP {$status}"];

        if (array_key_exists('code', $json) && $json['code'] !== null && $json['code'] !== '') {
            $code = $json['code'];
            $parts[] = 'code='.(is_scalar($code) ? (string) $code : json_encode($code, JSON_UNESCAPED_UNICODE));
        }

        $apiMessage = null;
        foreach (['message', 'error', 'msg', 'description'] as $key) {
            if (isset($json[$key]) && is_string($json[$key]) && $json[$key] !== '') {
                $apiMessage = $json[$key];
                break;
            }
        }
        if ($apiMessage === null && isset($json['errors']) && is_array($json['errors']) && $json['errors'] !== []) {
            $enc = json_encode($json['errors'], JSON_UNESCAPED_UNICODE);
            $apiMessage = $enc !== false ? $enc : null;
        }
        if ($apiMessage !== null) {
            $parts[] = $apiMessage;
        }

        $raw = $resp->body();
        $rawPreview = null;
        if ($raw !== '') {
            $rawPreview = substr($raw, 0, 2000);
        }
        if ($apiMessage === null && $rawPreview !== null && trim($rawPreview) !== '') {
            $oneLine = preg_replace('/\s+/', ' ', $rawPreview) ?? $rawPreview;
            $parts[] = 'raw='.substr($oneLine, 0, 400);
        }

        $summary = implode(' — ', $parts);
        if (strlen($summary) > 600) {
            $summary = substr($summary, 0, 597).'...';
        }

        return [
            'http_status' => $status,
            'response' => $json,
            'raw_body_preview' => $rawPreview,
            'summary_for_exception' => $summary,
        ];
    }

    /**
     * Step 1: initiate — may return account details immediately or a reference for OTP completion.
     *
     * @return array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array<string, mixed>}
     */
    public function initiateRubiesAccount(string $fname, string $lname, string $gender, string $phoneLocal11, string $bvn11): array
    {
        $json = $this->postCreaterubies([
            'action' => 'initiate',
            'fname' => $fname,
            'lname' => $lname,
            'gender' => $gender,
            'phone' => $phoneLocal11,
            'bvn' => $bvn11,
        ]);

        return $this->parseVaPayload($json);
    }

    /**
     * Step 2: submit OTP to complete account creation.
     *
     * @return array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array<string, mixed>}
     */
    public function completeRubiesAccount(string $reference, string $otp): array
    {
        $json = $this->postCreaterubies([
            'action' => 'complete',
            'otp' => $otp,
            'reference' => $reference,
        ]);

        $out = $this->parseVaPayload($json);
        if (trim($out['account_number']) === '') {
            throw new \RuntimeException('MevonRubies complete: missing account_number in response.');
        }

        return $out;
    }

    /**
     * Step 3: resend OTP for a pending reference.
     */
    public function resendRubiesOtp(string $reference): void
    {
        $this->postCreaterubies([
            'action' => 'resendOtp',
            'reference' => $reference,
        ]);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array<string, mixed>}
     */
    protected function parseVaPayload(array $json): array
    {
        $data = $json['data'] ?? $json;
        if (! is_array($data)) {
            $data = [];
        }

        $accountNumber = (string) ($data['account_number'] ?? $data['accountNumber'] ?? '');
        $reference = (string) ($data['reference'] ?? $json['reference'] ?? '');

        return [
            'account_number' => trim($accountNumber),
            'account_name' => (string) ($data['account_name'] ?? $data['accountName'] ?? ''),
            'bank_name' => (string) ($data['bank_name'] ?? $data['bankName'] ?? ''),
            'bank_code' => (string) ($data['bank_code'] ?? $data['bankCode'] ?? ''),
            'reference' => trim($reference),
            'raw' => $json,
        ];
    }

    /**
     * Create renter-specific reusable Rubies account (server-side only).
     * If the provider requires OTP, initiate returns a reference without an account — this method throws
     * because OTP must be completed on a channel tied to the BVN phone (e.g. WhatsApp Tier 2).
     */
    public function createRenterAccount(Renter $renter): array
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

        $phoneLocal = $this->normalizeToLocal11((string) $renter->phone);
        $bvn = preg_replace('/\D+/', '', (string) $renter->bvn) ?? '';
        if (strlen($bvn) !== 11) {
            throw new \RuntimeException('Renter BVN must be 11 digits.');
        }

        $gender = strtolower(trim((string) config('services.mevonrubies.default_gender', 'male')));
        if (! in_array($gender, ['male', 'female'], true)) {
            $gender = 'male';
        }

        $parsed = $this->initiateRubiesAccount($fname, $lname, $gender, $phoneLocal, $bvn);

        if ($parsed['account_number'] !== '') {
            return [
                'account_number' => $parsed['account_number'],
                'account_name' => $parsed['account_name'],
                'bank_name' => $parsed['bank_name'],
                'bank_code' => $parsed['bank_code'],
                'reference' => $parsed['reference'],
                'raw' => $parsed['raw'],
            ];
        }

        if ($parsed['reference'] !== '') {
            throw new \RuntimeException(
                'Rubies requires an OTP sent to the phone linked to this BVN. '.
                'Complete Tier 2 on WhatsApp from that same mobile number, or contact support.'
            );
        }

        throw new \RuntimeException('MevonRubies initiate returned no account number and no reference.');
    }

    protected function normalizeToLocal11(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? '';
        if ($d === '') {
            throw new \RuntimeException('Invalid phone number.');
        }
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            return $d;
        }
        if (strlen($d) === 13 && str_starts_with($d, '234')) {
            return '0'.substr($d, 3);
        }
        if (strlen($d) === 10 && $d[0] !== '0') {
            return '0'.$d;
        }

        throw new \RuntimeException('Renter phone must be a valid Nigerian mobile (e.g. 080… or +234…).');
    }
}
