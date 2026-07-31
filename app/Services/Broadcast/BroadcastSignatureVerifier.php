<?php

namespace App\Services\Broadcast;

/**
 * Checkout Broadcast packet signing — HMAC-SHA256 (v2.0 SDK) and Ed25519 (CheckoutNow Pay at shop).
 */
class BroadcastSignatureVerifier
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload, string $signatureAlg, string $signature, string $signingKey, ?string $publicKey): bool
    {
        $alg = strtoupper(trim($signatureAlg));

        if ($alg === 'ED25519') {
            return $publicKey !== null && $publicKey !== ''
                && $this->verifyEd25519($payload, $publicKey, $signature);
        }

        if ($alg === 'HMAC-SHA256') {
            return $this->verifyHmacSha256($payload, $signingKey, $signature);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyHmacSha256(array $payload, string $signingKey, string $signatureB64): bool
    {
        if ($signatureB64 === '' || $signingKey === '') {
            return false;
        }

        $canonical = $this->canonicalJson($this->sortKeysRecursive($payload));
        $expected = base64_encode(hash_hmac('sha256', $canonical, $signingKey, true));

        return hash_equals($expected, $signatureB64);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyEd25519(array $payload, string $publicKeyB64, string $signature): bool
    {
        if ($signature === '' || $publicKeyB64 === '') {
            return false;
        }

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }

        $message = $this->canonicalJson($this->sortKeysRecursive($payload));
        $publicKey = $this->decodeKeyMaterial($publicKeyB64, 32);
        $sigBytes = $this->decodeSignature($signature, 64);

        if ($publicKey === null || $sigBytes === null) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sigBytes, $message, $publicKey);
    }

    /**
     * Derive Ed25519 public key (base64) from a POS signing key (32-byte seed or 64-byte secret).
     */
    public function derivePublicKeyFromSigningKey(string $signingKeyB64): ?string
    {
        if (! function_exists('sodium_crypto_sign_publickey_from_secretkey')) {
            return null;
        }

        $secretKey = $this->decodeKeyMaterial($signingKeyB64, 64);
        if ($secretKey !== null) {
            return base64_encode(sodium_crypto_sign_publickey_from_secretkey($secretKey));
        }

        $seed = $this->decodeKeyMaterial($signingKeyB64, 32);
        if ($seed === null) {
            return null;
        }

        $keypair = sodium_crypto_sign_seed_keypair($seed);

        return base64_encode(sodium_crypto_sign_publickey($keypair));
    }

    /**
     * @return array{public_key: string, signing_key: string}
     */
    public function generateEd25519Keypair(): array
    {
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $secretKey = sodium_crypto_sign_secretkey($keypair);

        return [
            'public_key' => base64_encode($publicKey),
            'signing_key' => base64_encode($secretKey),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function signEd25519(array $payload, string $secretKeyB64): string
    {
        $secretKey = $this->decodeKeyMaterial($secretKeyB64, 64);
        if ($secretKey === null) {
            throw new \InvalidArgumentException('Invalid Ed25519 secret key');
        }

        $message = $this->canonicalJson($this->sortKeysRecursive($payload));
        $signature = sodium_crypto_sign_detached($message, $secretKey);

        return base64_encode($signature);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function canonicalJson(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sortKeysRecursive(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $data[$key] = array_map(
                        fn ($item) => is_array($item) ? $this->sortKeysRecursive($item) : $item,
                        $value
                    );
                } else {
                    $data[$key] = $this->sortKeysRecursive($value);
                }
            }
        }

        return $data;
    }

    private function decodeSignature(string $value, int $expectedLength): ?string
    {
        $decoded = $this->decodeKeyMaterial($value, $expectedLength);

        return $decoded;
    }

    private function decodeKeyMaterial(string $value, int $expectedLength): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = base64_decode($trimmed, true);
        if ($decoded !== false && strlen($decoded) === $expectedLength) {
            return $decoded;
        }

        if (ctype_xdigit($trimmed) && strlen($trimmed) === $expectedLength * 2) {
            $hex = hex2bin($trimmed);

            return $hex !== false ? $hex : null;
        }

        return null;
    }
}
