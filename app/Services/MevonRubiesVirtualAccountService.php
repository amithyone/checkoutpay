<?php

namespace App\Services;

use App\Models\Business;
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

    /**
     * Same style as {@see MavonPayTransferService}: send the configured secret as the raw
     * Authorization header value (no automatic "Bearer " prefix). Mevon createrubies appears
     * to accept the same token format as createtransfer. If your key must include a prefix,
     * put the full value in MEVONRUBIES_SECRET_KEY or MEVONPAY_SECRET_KEY.
     */
    protected function authorizationHeaderValue(): string
    {
        return trim($this->secretKey);
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
     * Create a personal Rubies VA in one call (no OTP). Mevon createrubies: action=create, account_type=personal.
     * Supply either bvn11 or nin (not both required by caller; API accepts bvn or nin per provider docs).
     *
     * @return array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array<string, mixed>}
     */
    public function createRubiesPersonalAccount(
        string $fname,
        string $lname,
        string $phoneLocal11,
        string $dobYmd,
        string $email,
        ?string $bvn11 = null,
        ?string $nin = null,
    ): array {
        $fname = trim($fname);
        $lname = trim($lname);
        $email = strtolower(trim($email));
        if ($fname === '' || $lname === '') {
            throw new \RuntimeException('First and last name are required.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobYmd)) {
            throw new \RuntimeException('Date of birth must be YYYY-MM-DD.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Valid email is required.');
        }
        $bvn = $bvn11 !== null ? (preg_replace('/\D+/', '', $bvn11) ?? '') : '';
        $ninDigits = $nin !== null ? (preg_replace('/\D+/', '', $nin) ?? '') : '';

        $phoneLocal11 = $this->normalizeToLocal11($phoneLocal11);

        $body = [
            'action' => 'create',
            'account_type' => 'personal',
            'fname' => $fname,
            'lname' => $lname,
            'phone' => $phoneLocal11,
            'dob' => $dobYmd,
            'email' => $email,
        ];
        if (strlen($bvn) === 11) {
            $body['bvn'] = $bvn;
        } elseif (strlen($ninDigits) === 11) {
            $body['nin'] = $ninDigits;
        } else {
            throw new \RuntimeException('BVN or NIN (11 digits each) is required.');
        }

        $json = $this->postCreaterubies($body);
        $out = $this->parseVaPayload($json);
        if (trim($out['account_number']) === '') {
            Log::channel('whatsapp_wallet_kyc')->error('MevonRubies create: unparseable VA payload', [
                'top_level_keys' => array_keys($json),
                'data_is_array' => isset($json['data']) && is_array($json['data']),
                'data_keys' => isset($json['data']) && is_array($json['data']) ? array_keys($json['data']) : [],
                'raw_response' => $json,
                'va_field_debug' => $this->rubiesVaFieldDebugSnapshot($json),
            ]);
            throw new \RuntimeException('MevonRubies create: missing account_number in response.');
        }

        return $out;
    }

    /**
     * Create a business Rubies VA in one call (no OTP). Mevon createrubies: action=create, account_type=business.
     *
     * @return array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array<string, mixed>}
     */
    public function createRubiesBusinessAccount(
        string $cac,
        string $phoneLocal11,
        string $dobYmd,
        string $email,
    ): array {
        $cac = strtoupper(trim($cac));
        $email = strtolower(trim($email));
        if ($cac === '' || strlen($cac) < 3) {
            throw new \RuntimeException('CAC / company registration number is required.');
        }
        if (strlen($cac) > 100) {
            throw new \RuntimeException('CAC / registration number is too long.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobYmd)) {
            throw new \RuntimeException('Date of birth must be YYYY-MM-DD.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Valid email is required.');
        }

        $phoneLocal11 = $this->normalizeToLocal11($phoneLocal11);

        $json = $this->postCreaterubies([
            'action' => 'create',
            'account_type' => 'business',
            'cac' => $cac,
            'phone' => $phoneLocal11,
            'dob' => $dobYmd,
            'email' => $email,
        ]);

        $out = $this->parseVaPayload($json);
        if (trim($out['account_number']) === '') {
            Log::channel('whatsapp_wallet_kyc')->error('MevonRubies business create: unparseable VA payload', [
                'top_level_keys' => array_keys($json),
                'data_is_array' => isset($json['data']) && is_array($json['data']),
                'data_keys' => isset($json['data']) && is_array($json['data']) ? array_keys($json['data']) : [],
                'raw_response' => $json,
                'va_field_debug' => $this->rubiesVaFieldDebugSnapshot($json),
            ]);
            throw new \RuntimeException('MevonRubies business create: missing account_number in response.');
        }

        return $out;
    }

    /**
     * Merchant KYC: create business Rubies VA from profile (CAC, phone, signatory DOB, email).
     *
     * @return array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array<string, mixed>}
     */
    public function createRubiesBusinessAccountForBusiness(Business $business): array
    {
        $cac = strtoupper(trim((string) $business->cac_registration_number));
        if ($cac === '') {
            throw new \RuntimeException('CAC / company registration number is missing on the business profile.');
        }

        $email = strtolower(trim((string) $business->email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Business email is required.');
        }

        $phone = trim((string) $business->phone);
        if ($phone === '') {
            throw new \RuntimeException('Business phone is required.');
        }

        $dob = null;
        $signatoryDob = $business->rubies_signatory_dob;
        if ($signatoryDob !== null) {
            $dob = $signatoryDob instanceof \DateTimeInterface
                ? $signatoryDob->format('Y-m-d')
                : (string) $signatoryDob;
        }
        if ($dob === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $dob = (string) config('services.mevonrubies.business_signatory_placeholder_dob', '1990-01-01');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $dob = '1990-01-01';
        }

        return $this->createRubiesBusinessAccount($cac, $phone, $dob, $email);
    }

    /**
     * @deprecated Legacy initiate + OTP flow; prefer {@see createRubiesPersonalAccount}.
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
     * @deprecated Legacy OTP completion; prefer {@see createRubiesPersonalAccount}.
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
            Log::channel('whatsapp_wallet_kyc')->error('MevonRubies complete: unparseable VA payload', [
                'top_level_keys' => array_keys($json),
                'data_is_array' => isset($json['data']) && is_array($json['data']),
                'data_keys' => isset($json['data']) && is_array($json['data']) ? array_keys($json['data']) : [],
                'raw_response' => $json,
                'va_field_debug' => $this->rubiesVaFieldDebugSnapshot($json),
            ]);
            throw new \RuntimeException('MevonRubies complete: missing account_number in response.');
        }

        return $out;
    }

    /**
     * @deprecated Legacy OTP flow.
     */
    public function resendRubiesOtp(string $reference): void
    {
        $this->postCreaterubies([
            'action' => 'resendOtp',
            'reference' => $reference,
        ]);
    }

    /**
     * Read a VA field from Mevon JSON. Fields may live under `data` or at the root.
     * Important: `data: []` is a valid key in PHP — it must not replace the whole response
     * or we miss root-level `account_number` after OTP complete.
     *
     * @param  array<string, mixed>  $json
     * @param  list<string>  $keys
     */
    /**
     * Log-friendly snapshot of VA-related keys (type + raw value) when parsing fails.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, array{type: string, value: mixed}>
     */
    protected function rubiesVaFieldDebugSnapshot(array $json): array
    {
        $watch = [
            'status', 'message', 'account_number', 'accountNumber', 'account_name', 'accountName',
            'bank_name', 'bankName', 'bank_code', 'bankCode', 'reference',
            'account_parent', 'accountParent', 'nuban',
        ];
        $out = [];
        foreach ($watch as $key) {
            if (! array_key_exists($key, $json)) {
                continue;
            }
            $v = $json[$key];
            $out[$key] = [
                'type' => get_debug_type($v),
                'value' => $v,
            ];
        }

        if (isset($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $k => $v) {
                $sk = is_string($k) ? $k : 'idx_'.$k;
                $out['data.'.$sk] = [
                    'type' => get_debug_type($v),
                    'value' => $v,
                ];
            }
        }

        return $out;
    }

    protected function rubiesVaField(array $json, string ...$keys): string
    {
        $nested = $json['data'] ?? null;
        $nestedUsable = is_array($nested) && $nested !== [];

        foreach ($keys as $key) {
            if ($nestedUsable && array_key_exists($key, $nested)) {
                $v = trim((string) $nested[$key]);
                if ($v !== '') {
                    return $v;
                }
            }
        }
        foreach ($keys as $key) {
            if (array_key_exists($key, $json)) {
                $v = trim((string) $json[$key]);
                if ($v !== '') {
                    return $v;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array<string, mixed>}
     */
    protected function parseVaPayload(array $json): array
    {
        $accountNumber = $this->rubiesVaField($json, 'account_number', 'accountNumber', 'nuban');
        if ($accountNumber === '') {
            // Initiate/complete sometimes expose the dedicated VA as account_parent only.
            $accountNumber = $this->rubiesVaField($json, 'account_parent', 'accountParent');
        }

        $reference = $this->rubiesVaField($json, 'reference');

        return [
            'account_number' => $accountNumber,
            'account_name' => $this->rubiesVaField($json, 'account_name', 'accountName'),
            'bank_name' => $this->rubiesVaField($json, 'bank_name', 'bankName'),
            'bank_code' => $this->rubiesVaField($json, 'bank_code', 'bankCode'),
            'reference' => $reference,
            'raw' => $json,
        ];
    }

    /**
     * Create renter-specific reusable Rubies account (server-side only). Uses createrubies action=create (no OTP).
     * DOB comes from config {@see config('services.mevonrubies.renter_placeholder_dob')} when not on the renter model.
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

        $dob = (string) config('services.mevonrubies.renter_placeholder_dob', '1990-01-01');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $dob = '1990-01-01';
        }

        $parsed = $this->createRubiesPersonalAccount(
            $fname,
            $lname,
            $phoneLocal,
            $dob,
            strtolower(trim((string) $renter->email)),
            $bvn,
            null
        );

        return [
            'account_number' => $parsed['account_number'],
            'account_name' => $parsed['account_name'],
            'bank_name' => $parsed['bank_name'],
            'bank_code' => $parsed['bank_code'],
            'reference' => $parsed['reference'],
            'raw' => $parsed['raw'],
        ];
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

        throw new \RuntimeException('Phone must be a valid Nigerian mobile (e.g. 080… or +234…).');
    }
}
