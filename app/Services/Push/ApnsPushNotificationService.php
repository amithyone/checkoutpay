<?php

namespace App\Services\Push;

use App\Services\PushNotificationService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Apple Push Notification service (HTTP/2) for CheckoutNow iOS device tokens.
 */
class ApnsPushNotificationService
{
    private const PROFILE = PushNotificationService::PROFILE_CHECKOUTNOW;

    private ?string $cachedJwt = null;

    private int $cachedJwtExpiresAt = 0;

    public function isConfigured(string $profile = self::PROFILE): bool
    {
        if ($profile !== self::PROFILE) {
            return false;
        }

        return $this->keyId() !== ''
            && $this->teamId() !== ''
            && $this->bundleId() !== ''
            && $this->resolvePrivateKeyPath() !== null;
    }

    /**
     * @return list<string> device tokens rejected by APNs
     */
    public function sendToDevice(
        string $deviceToken,
        string $title,
        string $body,
        array $data = [],
        string $profile = self::PROFILE,
    ): array {
        if (! $this->isConfigured($profile)) {
            Log::warning('APNs push skipped — not configured', ['profile' => $profile]);

            return [];
        }

        $deviceToken = strtolower(preg_replace('/[<>\s]/', '', $deviceToken) ?? '');
        if ($deviceToken === '') {
            return [''];
        }

        $jwt = $this->jwt();
        if ($jwt === null) {
            return [$deviceToken];
        }

        $payload = $this->buildPayload($title, $body, $data);
        $url = $this->host().'/3/device/'.$deviceToken;

        try {
            $client = new Client([
                'version' => 2.0,
                'timeout' => 10,
                'curl' => [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
                ],
            ]);

            $response = $client->post($url, [
                'headers' => [
                    'authorization' => 'bearer '.$jwt,
                    'apns-topic' => $this->bundleId(),
                    'apns-push-type' => 'alert',
                    'apns-priority' => '10',
                    'content-type' => 'application/json',
                ],
                'body' => json_encode($payload, JSON_THROW_ON_ERROR),
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                Log::info('APNs push accepted', [
                    'profile' => $profile,
                    'environment' => $this->environment(),
                    'apns_id' => $response->getHeaderLine('apns-id'),
                    'token_suffix' => substr($deviceToken, -12),
                    'type' => $data['type'] ?? null,
                ]);

                return [];
            }

            $responseBody = (string) $response->getBody();
            Log::warning('APNs push rejected', [
                'profile' => $profile,
                'environment' => $this->environment(),
                'status' => $status,
                'token_suffix' => substr($deviceToken, -12),
                'body' => substr($responseBody, 0, 500),
            ]);

            if ($this->isInvalidDeviceToken($status, $responseBody)) {
                return [$deviceToken];
            }

            return [];
        } catch (\Throwable $e) {
            Log::warning('APNs push send failed', [
                'profile' => $profile,
                'environment' => $this->environment(),
                'token_suffix' => substr($deviceToken, -12),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildPayload(string $title, string $body, array $data): array
    {
        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'sound' => 'default',
            ],
        ];

        foreach ($this->normalizeData($data) as $key => $value) {
            $payload[(string) $key] = $value;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function normalizeData(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            $normalized[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $normalized;
    }

    private function isInvalidDeviceToken(int $status, string $body): bool
    {
        if ($status === 410) {
            return true;
        }

        if ($status !== 400) {
            return false;
        }

        $lower = strtolower($body);

        return str_contains($lower, 'baddevicetoken')
            || str_contains($lower, 'unregistered')
            || str_contains($lower, 'devicetokennotfortopic');
    }

    private function jwt(): ?string
    {
        if ($this->cachedJwt !== null && time() < $this->cachedJwtExpiresAt) {
            return $this->cachedJwt;
        }

        $keyPath = $this->resolvePrivateKeyPath();
        if ($keyPath === null) {
            return null;
        }

        $privateKey = openssl_pkey_get_private((string) file_get_contents($keyPath));
        if ($privateKey === false) {
            Log::warning('APNs private key could not be loaded', ['path' => $keyPath]);

            return null;
        }

        $header = $this->base64UrlEncode(json_encode(['alg' => 'ES256', 'kid' => $this->keyId()], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $this->teamId(),
            'iat' => time(),
        ], JSON_THROW_ON_ERROR));
        $signingInput = $header.'.'.$claims;

        $derSignature = '';
        if (! openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::warning('APNs JWT signing failed');

            return null;
        }

        $this->cachedJwt = $signingInput.'.'.$this->base64UrlEncode($this->derEcdsaSignatureToConcat($derSignature));
        $this->cachedJwtExpiresAt = time() + 3000;

        return $this->cachedJwt;
    }

    private function derEcdsaSignatureToConcat(string $der): string
    {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) {
            throw new \RuntimeException('Invalid APNs ECDSA DER signature');
        }

        $this->readDerLength($der, $offset);

        if (ord($der[$offset++]) !== 0x02) {
            throw new \RuntimeException('Invalid APNs ECDSA R integer');
        }

        $rLength = $this->readDerLength($der, $offset);
        $r = substr($der, $offset, $rLength);
        $offset += $rLength;

        if (ord($der[$offset++]) !== 0x02) {
            throw new \RuntimeException('Invalid APNs ECDSA S integer');
        }

        $sLength = $this->readDerLength($der, $offset);
        $s = substr($der, $offset, $sLength);
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        return str_pad($r, 32, "\x00", STR_PAD_LEFT).str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    private function readDerLength(string $der, int &$offset): int
    {
        $length = ord($der[$offset++]);
        if ($length & 0x80) {
            $byteCount = $length & 0x7F;
            $length = 0;
            for ($i = 0; $i < $byteCount; $i++) {
                $length = ($length << 8) | ord($der[$offset++]);
            }
        }

        return $length;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function host(): string
    {
        return $this->environment() === 'sandbox'
            ? 'https://api.sandbox.push.apple.com'
            : 'https://api.push.apple.com';
    }

    private function environment(): string
    {
        $env = strtolower((string) config('services.apns.checkoutnow.environment', 'production'));

        return $env === 'sandbox' ? 'sandbox' : 'production';
    }

    private function keyId(): string
    {
        return trim((string) config('services.apns.checkoutnow.key_id', ''));
    }

    private function teamId(): string
    {
        return trim((string) config('services.apns.checkoutnow.team_id', ''));
    }

    private function bundleId(): string
    {
        return trim((string) config('services.apns.checkoutnow.bundle_id', 'com.checkoutnow.mobile'));
    }

    private function resolvePrivateKeyPath(): ?string
    {
        $configured = trim((string) config('services.apns.checkoutnow.private_key', ''));
        if ($configured === '') {
            return null;
        }

        if (is_file($configured)) {
            return $configured;
        }

        $candidate = base_path($configured);

        return is_file($candidate) ? $candidate : null;
    }
}
