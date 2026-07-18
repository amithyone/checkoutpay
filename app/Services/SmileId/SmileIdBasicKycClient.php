<?php

namespace App\Services\SmileId;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Smile ID Basic KYC (sync REST) — Kenya National ID / IPRS match.
 */
final class SmileIdBasicKycClient
{
    public function isConfigured(): bool
    {
        return trim((string) config('smile_id.partner_id', '')) !== ''
            && trim((string) config('smile_id.api_key', '')) !== '';
    }

    public function verifyUrl(): string
    {
        return (bool) config('smile_id.sandbox', true)
            ? 'https://testapi.smileidentity.com/v2/verify'
            : 'https://api.smileidentity.com/v2/verify';
    }

    /**
     * @param  array{
     *   country: string,
     *   id_type: string,
     *   id_number: string,
     *   first_name: string,
     *   last_name: string,
     *   dob?: string,
     *   gender?: string,
     *   phone_number?: string|null,
     *   user_id: string,
     *   job_id?: string
     * }  $input
     * @return array{
     *   ok: bool,
     *   matched: bool,
     *   result_code: string|null,
     *   result_text: string|null,
     *   smile_job_id: string|null,
     *   message: string,
     *   raw?: array<string, mixed>
     * }
     */
    public function basicKyc(array $input): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'matched' => false,
                'result_code' => null,
                'result_text' => null,
                'smile_job_id' => null,
                'message' => 'Smile ID is not configured (SMILE_ID_PARTNER_ID / SMILE_ID_API_KEY).',
            ];
        }

        $partnerId = (string) config('smile_id.partner_id');
        $apiKey = (string) config('smile_id.api_key');
        $sig = SmileIdSignature::generate($partnerId, $apiKey);
        $jobId = (string) ($input['job_id'] ?? (string) Str::uuid());

        $gender = strtoupper(trim((string) ($input['gender'] ?? '')));
        if ($gender === 'MALE') {
            $gender = 'M';
        } elseif ($gender === 'FEMALE') {
            $gender = 'F';
        }

        $body = [
            'source_sdk' => 'rest_api',
            'source_sdk_version' => '1.0.0',
            'partner_id' => $partnerId,
            'signature' => $sig['signature'],
            'timestamp' => $sig['timestamp'],
            'country' => strtoupper((string) $input['country']),
            'id_type' => (string) $input['id_type'],
            'id_number' => (string) $input['id_number'],
            'first_name' => (string) $input['first_name'],
            'last_name' => (string) $input['last_name'],
            'partner_params' => [
                'job_id' => $jobId,
                'user_id' => (string) $input['user_id'],
                'job_type' => 5,
            ],
        ];

        if (! empty($input['dob'])) {
            $body['dob'] = (string) $input['dob'];
        }
        if ($gender === 'M' || $gender === 'F') {
            $body['gender'] = $gender;
        }
        if (! empty($input['phone_number'])) {
            $body['phone_number'] = (string) $input['phone_number'];
        }

        $timeout = (int) config('smile_id.timeout_seconds', 45);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->post($this->verifyUrl(), $body);
        } catch (\Throwable $e) {
            Log::warning('smile_id.basic_kyc_http_failed', ['error' => $e->getMessage()]);

            return [
                'ok' => false,
                'matched' => false,
                'result_code' => null,
                'result_text' => null,
                'smile_job_id' => null,
                'message' => 'Could not reach Smile ID. Try again shortly.',
            ];
        }

        $json = $response->json();
        if (! is_array($json)) {
            Log::warning('smile_id.basic_kyc_bad_response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'ok' => false,
                'matched' => false,
                'result_code' => null,
                'result_text' => null,
                'smile_job_id' => null,
                'message' => 'Unexpected response from Smile ID.',
            ];
        }

        $code = isset($json['ResultCode']) ? (string) $json['ResultCode'] : null;
        $text = isset($json['ResultText']) ? (string) $json['ResultText'] : null;
        $smileJobId = isset($json['SmileJobID']) ? (string) $json['SmileJobID'] : null;

        $acceptPartial = (bool) config('smile_id.accept_partial_match', true);
        $matched = $code === '1020' || ($acceptPartial && $code === '1021');

        if ($matched) {
            return [
                'ok' => true,
                'matched' => true,
                'result_code' => $code,
                'result_text' => $text,
                'smile_job_id' => $smileJobId,
                'message' => $text ?: 'Identity verified.',
                'raw' => $json,
            ];
        }

        $message = match ($code) {
            '1022' => 'Details do not match the National ID record. Check name, date of birth, and ID number.',
            '1013' => 'National ID was not found. Check the number and try again.',
            '1014' => 'Invalid National ID format.',
            '1015' => 'Kenya ID authority is temporarily unavailable. Try again later.',
            '1016' => 'Kenya National ID product is not activated on this Smile ID account.',
            default => $text !== null && $text !== ''
                ? $text
                : ('Identity check failed'.($code ? " (code {$code})" : '').'.'),
        };

        Log::info('smile_id.basic_kyc_rejected', [
            'result_code' => $code,
            'result_text' => $text,
            'user_id' => $input['user_id'] ?? null,
        ]);

        return [
            'ok' => true,
            'matched' => false,
            'result_code' => $code,
            'result_text' => $text,
            'smile_job_id' => $smileJobId,
            'message' => $message,
            'raw' => $json,
        ];
    }
}
