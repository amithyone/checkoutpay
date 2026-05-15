<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Services\Whatsapp\PhoneNormalizer;

/**
 * Signed CheckoutNow pay QR payloads (wallet P2P / pay_code).
 */
final class ConsumerWalletPayQrService
{
    public function __construct(
        private ConsumerWalletPayCodeService $payCodes,
    ) {}

    /**
     * @return array{qr_url: string, qr_token: string, pay_code: string, display_name: string, phone_e164: string}
     */
    public function buildReceiveQr(WhatsappWallet $wallet): array
    {
        $wallet = $wallet->fresh();
        $payCode = $this->payCodes->ensureForWallet($wallet);
        $displayName = trim($wallet->normalizedSenderName());
        if ($displayName === '') {
            $displayName = (string) $wallet->phone_e164;
        }

        $payload = [
            'v' => 1,
            't' => 'wallet',
            'phone_e164' => (string) $wallet->phone_e164,
            'pay_code' => $payCode,
            'display_name' => $displayName,
        ];

        $token = $this->signPayload($payload);
        $base = rtrim((string) config('consumer_wallet.pay_qr_base_url', 'https://app.check-outnow.com'), '/');

        return [
            'qr_url' => $base.'/pay/'.$token,
            'qr_token' => $token,
            'pay_code' => $payCode,
            'display_name' => $displayName,
            'phone_e164' => (string) $wallet->phone_e164,
        ];
    }

    /**
     * @return array{ok: true, mode: string, phone_e164: string, display_name: string|null, pay_code: string|null}|array{ok: false, message: string}
     */
    public function resolveScanInput(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => false, 'message' => 'Empty scan payload.'];
        }

        if (preg_match('/^\d{5}$/', $raw)) {
            return $this->resolvePayCode($raw);
        }

        $token = $this->extractToken($raw);
        if ($token === null) {
            return ['ok' => false, 'message' => 'Unrecognized QR code.'];
        }

        $payload = $this->verifyToken($token);
        if ($payload === null) {
            return ['ok' => false, 'message' => 'Invalid or tampered QR code.'];
        }

        if (($payload['t'] ?? '') !== 'wallet' || (int) ($payload['v'] ?? 0) !== 1) {
            return ['ok' => false, 'message' => 'Unsupported QR type.'];
        }

        $phone = PhoneNormalizer::canonicalNgE164Digits((string) ($payload['phone_e164'] ?? ''));
        if ($phone === null) {
            return ['ok' => false, 'message' => 'Invalid wallet in QR code.'];
        }

        $wallet = WhatsappWallet::query()
            ->where('phone_e164', $phone)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->first();

        if (! $wallet) {
            return ['ok' => false, 'message' => 'Wallet not found for this QR code.'];
        }

        $payCode = isset($payload['pay_code']) ? trim((string) $payload['pay_code']) : '';
        if ($payCode !== '' && (string) $wallet->pay_code !== '' && $payCode !== (string) $wallet->pay_code) {
            return ['ok' => false, 'message' => 'QR code is outdated. Ask them to refresh their receive QR.'];
        }

        $displayName = trim((string) ($payload['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = $wallet->normalizedSenderName();
        }

        return [
            'ok' => true,
            'mode' => 'p2p',
            'phone_e164' => $phone,
            'display_name' => $displayName !== '' ? $displayName : null,
            'pay_code' => (string) ($wallet->pay_code ?? $payCode) ?: null,
        ];
    }

    /**
     * @return array{ok: true, mode: string, phone_e164: string, display_name: string|null, pay_code: string}|array{ok: false, message: string}
     */
    private function resolvePayCode(string $code): array
    {
        $wallet = $this->payCodes->findByPayCode($code);
        if (! $wallet) {
            return ['ok' => false, 'message' => 'Pay code not found.'];
        }

        $name = trim($wallet->normalizedSenderName());

        return [
            'ok' => true,
            'mode' => 'p2p',
            'phone_e164' => (string) $wallet->phone_e164,
            'display_name' => $name !== '' ? $name : null,
            'pay_code' => (string) $wallet->pay_code,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signPayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $payloadB64 = $this->base64UrlEncode($json);
        $sig = $this->base64UrlEncode(hash_hmac('sha256', $payloadB64, $this->secret(), true));

        return $payloadB64.'.'.$sig;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadB64, $sigB64] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $payloadB64, $this->secret(), true));
        if (! hash_equals($expected, $sigB64)) {
            return null;
        }

        $json = $this->base64UrlDecode($payloadB64);
        if ($json === null) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private function extractToken(string $raw): ?string
    {
        if (str_contains($raw, '/pay/')) {
            $pos = strrpos($raw, '/pay/');
            if ($pos === false) {
                return null;
            }
            $token = substr($raw, $pos + 5);
            $token = strtok($token, '?#') ?: $token;

            return trim($token) !== '' ? trim($token) : null;
        }

        if (str_contains($raw, '.')) {
            return $raw;
        }

        return null;
    }

    private function secret(): string
    {
        $dedicated = (string) config('consumer_wallet.pay_qr_secret', '');
        if ($dedicated !== '') {
            return $dedicated;
        }

        return (string) config('app.key');
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): ?string
    {
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
