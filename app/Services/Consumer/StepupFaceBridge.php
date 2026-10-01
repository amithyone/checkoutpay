<?php

namespace App\Services\Consumer;

use App\Models\ConsumerDeviceStepupSession;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bridge Namecheap (session source of truth) ↔ Contabo (CheckFace compute).
 * App gets face_continue_token on login 403, calls Contabo face routes, Contabo
 * finalizes back to Namecheap and returns stepup_token in one shot.
 */
class StepupFaceBridge
{
    public function enabled(): bool
    {
        return (bool) config('checkface.stepup_bridge_enabled', true)
            && trim((string) config('checkface.stepup_bridge_secret', '')) !== '';
    }

    public function appFaceApiBase(): string
    {
        return rtrim((string) config('checkface.app_face_api_base', 'https://check-outnow.com'), '/');
    }

    /**
     * @return array{face_api_base: string, face_continue_token: string}|array{}
     */
    public function clientHints(ConsumerDeviceStepupSession $session, WhatsappWallet $wallet): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $token = $this->mintContinueToken($session, $wallet);
        if ($token === null) {
            return [];
        }

        return [
            'face_api_base' => $this->appFaceApiBase(),
            'face_continue_token' => $token,
        ];
    }

    public function mintContinueToken(ConsumerDeviceStepupSession $session, WhatsappWallet $wallet): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $ttl = max(60, (int) config('checkface.stepup_bridge_continue_ttl_seconds', 1800));

        return $this->seal([
            'v' => 1,
            'purpose' => 'face_continue',
            'stepup_session' => $session->session_token,
            'wallet_id' => (int) $wallet->id,
            'account_id' => (int) $session->consumer_wallet_api_account_id,
            'phone_e164' => (string) $session->phone_e164,
            'pending_device_id' => $session->pending_device_id,
            'checkface_user_id' => 'w'.(int) $wallet->id,
            'exp' => time() + $ttl,
            'jti' => Str::random(16),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifyContinueToken(?string $token, ?string $expectedSession = null): ?array
    {
        $claims = $this->open($token);
        if ($claims === null || ($claims['purpose'] ?? '') !== 'face_continue') {
            return null;
        }
        if ($expectedSession !== null && $expectedSession !== ''
            && ! hash_equals((string) $expectedSession, (string) ($claims['stepup_session'] ?? ''))) {
            return null;
        }

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function mintProofToken(string $stepupSession, array $extra = []): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $ttl = max(30, (int) config('checkface.stepup_bridge_proof_ttl_seconds', 300));

        return $this->seal(array_merge([
            'v' => 1,
            'purpose' => 'face_proof',
            'stepup_session' => $stepupSession,
            'exp' => time() + $ttl,
            'jti' => Str::random(16),
        ], $extra));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifyProofToken(?string $token, ?string $expectedSession = null): ?array
    {
        $claims = $this->open($token);
        if ($claims === null || ($claims['purpose'] ?? '') !== 'face_proof') {
            return null;
        }
        if ($expectedSession !== null && $expectedSession !== ''
            && ! hash_equals((string) $expectedSession, (string) ($claims['stepup_session'] ?? ''))) {
            return null;
        }

        return $claims;
    }

    /**
     * Contabo → Namecheap: mark step-up face verified and mint bind token.
     *
     * @return array{ok: bool, message?: string, stepup_token?: string, pin_reset_required?: bool, next_step?: string, stepup_mode?: string}
     */
    public function finalizeOnLive(string $stepupSession, string $faceProof): array
    {
        $url = rtrim((string) config('checkface.stepup_bridge_finalize_url', ''), '/');
        if ($url === '') {
            return ['ok' => false, 'message' => 'Face bridge finalize URL is not configured.'];
        }

        $body = json_encode([
            'stepup_session' => $stepupSession,
            'face_proof' => $faceProof,
        ], JSON_THROW_ON_ERROR);

        $timestamp = (string) time();
        $nonce = Str::random(24);
        $secret = (string) config('checkface.stepup_bridge_secret', '');
        $signature = $this->signRequest($timestamp, $nonce, $body, $secret);

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'X-Face-Bridge-Timestamp' => $timestamp,
                    'X-Face-Bridge-Nonce' => $nonce,
                    'X-Face-Bridge-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (\Throwable $e) {
            Log::warning('stepup_face_bridge_finalize_failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Could not finalize face step-up on live.'];
        }

        $json = $response->json();
        if (! $response->successful() || ! is_array($json) || ! ($json['success'] ?? false)) {
            return [
                'ok' => false,
                'message' => is_array($json) ? (string) ($json['message'] ?? 'Finalize rejected.') : 'Finalize rejected.',
            ];
        }

        $data = is_array($json['data'] ?? null) ? $json['data'] : [];

        return [
            'ok' => true,
            'stepup_token' => (string) ($data['stepup_token'] ?? ''),
            'pin_reset_required' => (bool) ($data['pin_reset_required'] ?? true),
            'next_step' => (string) ($data['next_step'] ?? 'bind'),
            'stepup_mode' => (string) ($data['stepup_mode'] ?? 'device_mismatch'),
        ];
    }

    public function signRequest(string $timestamp, string $nonce, string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$rawBody, $secret);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function seal(array $claims): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $payload, (string) config('checkface.stepup_bridge_secret', ''));

        return $payload.'.'.$sig;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function open(?string $token): ?array
    {
        if (! $this->enabled() || ! is_string($token) || $token === '' || ! str_contains($token, '.')) {
            return null;
        }

        [$payload, $sig] = explode('.', $token, 2);
        $expected = hash_hmac('sha256', $payload, (string) config('checkface.stepup_bridge_secret', ''));
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        try {
            $json = base64_decode(strtr($payload, '-_', '+/'), true);
            $claims = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($claims) || (int) ($claims['exp'] ?? 0) < time()) {
            return null;
        }

        return $claims;
    }
}
