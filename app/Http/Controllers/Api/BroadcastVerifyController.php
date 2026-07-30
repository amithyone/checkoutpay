<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Broadcast\BroadcastSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Checkout Broadcast verify API for check-outpay.com
 * Port of checkout_broadcast/bank_api — used by CheckoutNow + merchant POS terminals.
 *
 * Mobile default: EXPO_PUBLIC_CHECKOUT_BROADCAST_API=https://check-outpay.com/api/v1/broadcast
 */
class BroadcastVerifyController extends Controller
{
    private const MAX_AGE_MS = 600_000;

    public function __construct(
        private readonly BroadcastSignatureVerifier $signatures,
    ) {}

    public function health(): JsonResponse
    {
        $terminals = DB::table('broadcast_terminals')->where('active', 1)->count();

        return response()->json([
            'ok' => true,
            'status' => 'ok',
            'terminals' => $terminals,
        ]);
    }

    public function verifyBroadcast(Request $request): JsonResponse
    {
        $logBase = [
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 256),
        ];

        $key = 'broadcast-verify:'.$request->ip();
        $limit = max(1, (int) config('broadcast.rate_limit_verify', 120));

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retry = RateLimiter::availableIn($key);
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => 'Rate limit exceeded',
                'http_status' => 429,
            ]);

            return response()->json([
                'valid' => false,
                'error' => 'Rate limit exceeded',
                'retry_after_seconds' => $retry,
            ], 429)->header('Retry-After', (string) $retry);
        }
        RateLimiter::hit($key, 60);

        $packet = $request->all();
        $payload = $packet['payload'] ?? null;
        if (! is_array($payload)) {
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => 'Invalid packet',
                'http_status' => 422,
            ]);

            return response()->json(['valid' => false, 'error' => 'Invalid packet'], 422);
        }

        $terminalId = (string) ($payload['terminal_id'] ?? '');
        $session = (string) ($payload['session_uuid_v4'] ?? '');
        $amount = (int) data_get($payload, 'transaction_details.total_amount_ngn', 0);
        $logBase['terminal_id'] = $terminalId;
        $logBase['session_uuid'] = $session;
        $logBase['amount_ngn'] = $amount;
        $logBase['signature_alg'] = (string) ($packet['signature_alg'] ?? '');

        $terminal = DB::table('broadcast_terminals')
            ->where('terminal_id', $terminalId)
            ->where('active', 1)
            ->first();

        if (! $terminal) {
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => 'Unknown terminal_id',
                'http_status' => 200,
            ]);

            return response()->json(['valid' => false, 'error' => 'Unknown terminal_id']);
        }

        $logBase['business_id'] = $terminal->business_id;
        $logBase['merchant_name'] = $terminal->merchant_name;

        if (! $terminal->active) {
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => 'Terminal is disabled',
                'http_status' => 200,
            ]);

            return response()->json(['valid' => false, 'error' => 'Terminal is disabled']);
        }

        if ($terminal->business_id) {
            $owner = DB::table('businesses')
                ->where('id', $terminal->business_id)
                ->first(['broadcast_pay_at_shop_enabled', 'broadcast_pay_at_shop_active', 'is_active']);
            if (! $owner || ! $owner->is_active || ! $owner->broadcast_pay_at_shop_enabled || ! $owner->broadcast_pay_at_shop_active) {
                $this->logVerifyAttempt($logBase, [
                    'valid' => false,
                    'error' => 'Pay at shop is not active for this merchant',
                    'http_status' => 200,
                ]);

                return response()->json(['valid' => false, 'error' => 'Pay at shop is not active for this merchant']);
            }
        }

        $timestampMs = (int) ($payload['timestamp_ms'] ?? 0);
        if (abs((int) (microtime(true) * 1000) - $timestampMs) > self::MAX_AGE_MS) {
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => 'Timestamp outside allowed window',
                'http_status' => 200,
            ]);

            return response()->json(['valid' => false, 'error' => 'Timestamp outside allowed window']);
        }

        if ($session === '' || ! Str::isUuid($session)) {
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => 'Invalid session UUID',
                'http_status' => 200,
            ]);

            return response()->json(['valid' => false, 'error' => 'Invalid session UUID']);
        }

        $display = $payload['account_info_public_display'] ?? [];
        if (! is_array($display) || ($display['bank_name_hash'] ?? '') !== $terminal->bank_name_hash) {
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => 'Bank name hash mismatch',
                'http_status' => 200,
            ]);

            return response()->json(['valid' => false, 'error' => 'Bank name hash mismatch']);
        }

        $signatureAlg = (string) ($packet['signature_alg'] ?? $terminal->signature_alg ?? 'HMAC-SHA256');
        $verified = $this->signatures->verify(
            $payload,
            $signatureAlg,
            (string) ($packet['signature'] ?? ''),
            (string) $terminal->signing_key,
            $terminal->public_key ?? null,
        );

        if (! $verified) {
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => 'Invalid signature',
                'http_status' => 200,
            ]);

            return response()->json(['valid' => false, 'error' => 'Invalid signature']);
        }

        $sessionReplay = $this->sessionAlreadyUsed($session);
        $this->recordSession($session, $terminalId);

        $maskedSuffix = (string) data_get(
            $display,
            'masked_account_suffix',
            $terminal->masked_account_suffix,
        );

        $this->logVerifyAttempt($logBase, [
            'valid' => true,
            'http_status' => 200,
            'recipient_account_suffix' => $maskedSuffix,
            'idempotent_replay' => $sessionReplay,
        ]);

        return response()->json([
            'valid' => true,
            'merchant_name' => $terminal->merchant_name,
            'amount_ngn' => $amount,
            'masked_account_suffix' => $maskedSuffix,
            'session_uuid' => $session,
            'terminal_id' => $terminalId,
            'recipient_account' => $terminal->account_number,
            'recipient_bank_code' => $terminal->recipient_bank_code,
        ]);
    }

    public function registerTerminal(Request $request): JsonResponse
    {
        if (! $this->adminAuthorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'terminal_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'signature_alg' => 'nullable|string|in:ed25519,ED25519,HMAC-SHA256,hmac-sha256',
            'signing_key' => 'nullable|string|min:16|max:256',
            'public_key' => 'nullable|string|max:128',
            'merchant_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'api_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'merchant_name' => 'required|string|max:128',
            'bank_name' => 'required|string|max:64',
            'masked_account_suffix' => ['required', 'regex:/^\*{3}[0-9]{4}$/'],
            'account_number' => 'nullable|digits:10',
            'recipient_bank_code' => 'nullable|string|max:6',
            'business_id' => 'nullable|integer|exists:businesses,id',
            'generate_signing_key' => 'nullable|boolean',
        ]);

        $signatureAlg = $this->normalizeSignatureAlg((string) ($data['signature_alg'] ?? 'ed25519'));
        $generatedSigningKey = null;
        $publicKey = $data['public_key'] ?? null;
        $signingKey = $data['signing_key'] ?? null;

        if ($signatureAlg === 'ED25519') {
            if ($publicKey === null && ($data['generate_signing_key'] ?? true)) {
                $keypair = $this->signatures->generateEd25519Keypair();
                $publicKey = $keypair['public_key'];
                $generatedSigningKey = $keypair['signing_key'];
                $signingKey = '';
            } elseif ($publicKey === null) {
                return response()->json([
                    'error' => 'public_key is required for ed25519 terminals (or set generate_signing_key=true)',
                ], 422);
            }
        } elseif ($signingKey === null || $signingKey === '') {
            return response()->json([
                'error' => 'signing_key is required for HMAC-SHA256 terminals',
            ], 422);
        }

        $merchantId = $data['merchant_id'] ?? ('MCH-'.$data['terminal_id']);
        $apiKey = $data['api_key'] ?? ('bk_'.Str::lower(Str::random(32)));

        $bankNameHash = 'sha256:'.hash('sha256', strtolower(trim($data['bank_name'])));
        $now = now();
        $row = [
            'merchant_id' => $merchantId,
            'api_key' => $apiKey,
            'signing_key' => $signingKey ?? '',
            'public_key' => $publicKey,
            'signature_alg' => $signatureAlg,
            'merchant_name' => $data['merchant_name'],
            'bank_name' => $data['bank_name'],
            'bank_name_hash' => $bankNameHash,
            'masked_account_suffix' => $data['masked_account_suffix'],
            'account_number' => $data['account_number'] ?? null,
            'recipient_bank_code' => $data['recipient_bank_code'] ?? null,
            'business_id' => $data['business_id'] ?? null,
            'active' => 1,
            'updated_at' => $now,
        ];

        $existing = DB::table('broadcast_terminals')->where('terminal_id', $data['terminal_id'])->exists();
        if ($existing) {
            unset($row['api_key'], $row['merchant_id']);
            DB::table('broadcast_terminals')->where('terminal_id', $data['terminal_id'])->update($row);
            $stored = DB::table('broadcast_terminals')->where('terminal_id', $data['terminal_id'])->first();
            $merchantId = $stored->merchant_id ?? $merchantId;
            $apiKey = $stored->api_key ?? $apiKey;
        } else {
            DB::table('broadcast_terminals')->insert(array_merge($row, [
                'terminal_id' => $data['terminal_id'],
                'created_at' => $now,
            ]));
        }

        $response = [
            'ok' => true,
            'status' => 'registered',
            'terminal_id' => $data['terminal_id'],
            'merchant_id' => $merchantId,
            'api_key' => $apiKey,
            'signature_alg' => $signatureAlg,
        ];

        if ($generatedSigningKey !== null) {
            $response['signing_key'] = $generatedSigningKey;
            $response['credentials_note'] = 'Store signing_key securely on the POS — it is shown once at registration.';
        }

        return response()->json($response);
    }

    public function showTerminal(Request $request, string $id): JsonResponse
    {
        if (! $this->adminAuthorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $terminal = DB::table('broadcast_terminals')->where('terminal_id', $id)->first();
        if (! $terminal) {
            return response()->json(['error' => 'Terminal not found'], 404);
        }

        return response()->json([
            'terminal_id' => $terminal->terminal_id,
            'merchant_id' => $terminal->merchant_id,
            'api_key' => $terminal->api_key,
            'signature_alg' => $terminal->signature_alg ?? 'HMAC-SHA256',
            'merchant_name' => $terminal->merchant_name,
            'bank_name' => $terminal->bank_name,
            'masked_account_suffix' => $terminal->masked_account_suffix,
            'recipient_bank_code' => $terminal->recipient_bank_code,
            'account_number' => $terminal->account_number,
            'business_id' => $terminal->business_id,
            'active' => (bool) $terminal->active,
        ]);
    }

    public function listTerminals(Request $request): JsonResponse
    {
        if (! $this->adminAuthorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $rows = DB::table('broadcast_terminals')
            ->orderBy('terminal_id')
            ->get([
                'terminal_id',
                'merchant_id',
                'api_key',
                'signature_alg',
                'merchant_name',
                'bank_name',
                'masked_account_suffix',
                'recipient_bank_code',
                'business_id',
                'active',
            ]);

        return response()->json([
            'terminals' => $rows->map(fn ($t) => [
                'terminal_id' => $t->terminal_id,
                'merchant_id' => $t->merchant_id,
                'api_key' => $t->api_key,
                'signature_alg' => $t->signature_alg ?? 'HMAC-SHA256',
                'merchant_name' => $t->merchant_name,
                'bank_name' => $t->bank_name,
                'masked_account_suffix' => $t->masked_account_suffix,
                'recipient_bank_code' => $t->recipient_bank_code,
                'business_id' => $t->business_id,
                'active' => (bool) $t->active,
            ])->values(),
        ]);
    }

    private function adminAuthorized(Request $request): bool
    {
        $configured = (string) config('broadcast.admin_key', '');
        if ($configured === '' || $configured === 'change-me-before-production') {
            return false;
        }

        $provided = (string) ($request->header('X-Admin-Key') ?? '');

        return $provided !== '' && hash_equals($configured, $provided);
    }

    private function sessionAlreadyUsed(string $sessionUuid): bool
    {
        return DB::table('broadcast_used_sessions')->where('session_uuid', $sessionUuid)->exists();
    }

    private function recordSession(string $sessionUuid, string $terminalId): void
    {
        if (! Str::isUuid($sessionUuid)) {
            return;
        }

        try {
            DB::table('broadcast_used_sessions')->insertOrIgnore([
                'session_uuid' => $sessionUuid,
                'terminal_id' => $terminalId,
                'used_at' => (int) (microtime(true) * 1000),
            ]);
        } catch (\Throwable) {
            // Audit-only; verification outcome must not depend on insert success.
        }
    }

    private function normalizeSignatureAlg(string $alg): string
    {
        return match (strtolower(trim($alg))) {
            'ed25519' => 'ED25519',
            'hmac-sha256' => 'HMAC-SHA256',
            default => strtoupper(trim($alg)),
        };
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $result
     */
    private function logVerifyAttempt(array $base, array $result): void
    {
        $context = array_merge($base, $result);

        try {
            if (config('logging.channels.broadcast_verify')) {
                Log::channel('broadcast_verify')->info('verify-broadcast', $context);

                return;
            }
        } catch (\Throwable) {
            // Stale config cache or partial deploy — fall back below.
        }

        Log::info('verify-broadcast', $context);
    }
}
