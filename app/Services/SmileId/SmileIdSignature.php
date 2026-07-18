<?php

namespace App\Services\SmileId;

/**
 * Smile ID request signature (HMAC-SHA256).
 *
 * @see https://legacy-docs.usesmileid.com/integration-options/rest-api/signing-your-api-request/generate-signature
 */
final class SmileIdSignature
{
    /**
     * @return array{signature: string, timestamp: string}
     */
    public static function generate(string $partnerId, string $apiKey, ?string $timestamp = null): array
    {
        $ts = $timestamp ?? gmdate('Y-m-d\TH:i:s.v\Z');
        $message = $ts.$partnerId.'sid_request';
        $signature = base64_encode(hash_hmac('sha256', $message, $apiKey, true));

        return [
            'signature' => $signature,
            'timestamp' => $ts,
        ];
    }
}
