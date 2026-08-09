<?php

namespace App\Services\MevonPay;

use App\Services\MevonRubiesVirtualAccountService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mevon permanent account provisioning via POST /V1/pivateaccount.
 * Both business and personal use action create_business_account; pass company name or person full name in business_name.
 */
class MevonPrivateAccountService
{
    public function __construct(
        private MevonRubiesVirtualAccountService $vaParser,
    ) {}

    public function isConfigured(): bool
    {
        $baseUrl = (string) (config('services.mevonpay.base_url') ?: config('services.mevonrubies.base_url', ''));
        $secret = (string) (config('services.mevonpay.secret_key') ?: config('services.mevonrubies.secret_key', ''));

        return $baseUrl !== '' && $secret !== '';
    }

    /**
     * @return array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array<string, mixed>}
     */
    public function createBusinessAccount(
        string $businessName,
        string $cac,
        string $phoneLocal11,
        string $dobYmd,
        string $email,
        ?string $bvn11 = null,
        ?string $nin11 = null,
    ): array {
        $businessName = trim($businessName);
        $cac = strtoupper(trim($cac));
        $email = strtolower(trim($email));
        if ($businessName === '') {
            throw new \RuntimeException('Business name is required.');
        }
        if ($cac === '' || strlen($cac) < 3) {
            throw new \RuntimeException('CAC / company registration number is required.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dobYmd)) {
            throw new \RuntimeException('Date of birth must be YYYY-MM-DD.');
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Valid email is required.');
        }

        $phoneLocal11 = $this->normalizeToLocal11($phoneLocal11);
        $body = [
            'action' => 'create_business_account',
            'business_name' => $businessName,
            'cac' => $cac,
            'phone' => $phoneLocal11,
            'dob' => $dobYmd,
            'email' => $email,
        ];
        $this->attachIdentityNumber($body, $bvn11, $nin11);

        return $this->postAndParse($body, 'business');
    }

    /**
     * @return array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array<string, mixed>}
     */
    public function createPersonalAccount(
        string $fname,
        string $lname,
        string $phoneLocal11,
        string $dobYmd,
        string $email,
        ?string $bvn11 = null,
        ?string $nin11 = null,
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

        $phoneLocal11 = $this->normalizeToLocal11($phoneLocal11);
        $body = [
            'action' => 'create_business_account',
            'business_name' => trim($fname.' '.$lname),
            'phone' => $phoneLocal11,
            'dob' => $dobYmd,
            'email' => $email,
        ];
        $this->attachIdentityNumber($body, $bvn11, $nin11);

        return $this->postAndParse($body, 'personal');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{account_number: string, account_name: string, bank_name: string, bank_code: string, reference: string, raw: array<string, mixed>}
     */
    private function postAndParse(array $body, string $kind): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Mevon private account API is not configured.');
        }

        $url = $this->endpointUrl();
        $timeout = (int) config('services.mevonpay.private_account_timeout_seconds', 90);

        $resp = Http::withHeaders(\App\Support\MevonPayEgress::mergeClientHeaders([
            'Authorization' => $this->authorizationHeaderValue(),
        ]))
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->post($url, $body);

        $json = $resp->json();
        if (! is_array($json)) {
            $body = (string) $resp->body();
            if (\App\Support\Imunify360Ops::looksLikeWafBlock($body)) {
                Log::warning('mevonpay.private_account_waf_blocked', ['kind' => $kind, 'http_status' => $resp->status()]);
                throw new \RuntimeException(\App\Support\Imunify360Ops::wafBlockMessage());
            }
            $json = [];
        }

        if (! $resp->successful()) {
            $summary = $this->formatHttpError($resp, $json);
            Log::warning('mevonpay.private_account_failed', [
                'kind' => $kind,
                'summary' => $summary,
            ]);
            throw new \RuntimeException('Mevon private account failed: '.$summary);
        }

        if (($json['status'] ?? null) === false) {
            $message = (string) ($json['message'] ?? $json['error'] ?? 'Unknown error');
            Log::warning('mevonpay.private_account_status_false', [
                'kind' => $kind,
                'response' => $json,
            ]);
            throw new \RuntimeException('Mevon private account: '.$message);
        }

        $out = $this->vaParser->parseVirtualAccountResponse($json);
        if (trim($out['account_number']) === '') {
            Log::error('mevonpay.private_account_missing_number', [
                'kind' => $kind,
                'response' => $json,
            ]);
            throw new \RuntimeException('Mevon private account: missing account_number in response.');
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function attachIdentityNumber(array &$body, ?string $bvn11, ?string $nin11): void
    {
        $bvn = $bvn11 !== null ? (preg_replace('/\D+/', '', $bvn11) ?? '') : '';
        $nin = $nin11 !== null ? (preg_replace('/\D+/', '', $nin11) ?? '') : '';
        if (strlen($bvn) === 11) {
            $body['bvn'] = $bvn;

            return;
        }
        if (strlen($nin) === 11) {
            $body['nin'] = $nin;

            return;
        }

        throw new \RuntimeException('BVN or NIN (11 digits) is required.');
    }

    private function endpointUrl(): string
    {
        $baseUrl = rtrim((string) (config('services.mevonpay.base_url') ?: config('services.mevonrubies.base_url', '')), '/');
        $path = (string) config('services.mevonpay.private_account_path', '/V1/pivateaccount');

        return $baseUrl.'/'.ltrim($path, '/');
    }

    private function authorizationHeaderValue(): string
    {
        return trim((string) (config('services.mevonpay.secret_key') ?: config('services.mevonrubies.secret_key', '')));
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function formatHttpError(Response $resp, array $json): string
    {
        $parts = ['HTTP '.$resp->status()];
        foreach (['message', 'error', 'msg'] as $key) {
            if (! empty($json[$key]) && is_string($json[$key])) {
                $parts[] = $json[$key];
                break;
            }
        }

        return implode(' — ', $parts);
    }

    private function normalizeToLocal11(string $phone): string
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
