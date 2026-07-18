<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $key = 'broadcast-verify:'.$request->ip();
        $limit = max(1, (int) config('broadcast.rate_limit_verify', 120));

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retry = RateLimiter::availableIn($key);

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
            return response()->json(['valid' => false, 'error' => 'Invalid packet'], 422);
        }

        $terminalId = (string) ($payload['terminal_id'] ?? '');
        $terminal = DB::table('broadcast_terminals')
            ->where('terminal_id', $terminalId)
            ->where('active', 1)
            ->first();

        if (! $terminal) {
            return response()->json(['valid' => false, 'error' => 'Unknown terminal_id']);
        }

        $timestampMs = (int) ($payload['timestamp_ms'] ?? 0);
        if (abs((int) (microtime(true) * 1000) - $timestampMs) > self::MAX_AGE_MS) {
            return response()->json(['valid' => false, 'error' => 'Timestamp outside allowed window']);
        }

        $session = (string) ($payload['session_uuid_v4'] ?? '');
        if ($session === '' || ! $this->consumeSession($session, $terminalId)) {
            return response()->json(['valid' => false, 'error' => 'Session UUID already used (replay)']);
        }

        $display = $payload['account_info_public_display'] ?? [];
        if (! is_array($display) || ($display['bank_name_hash'] ?? '') !== $terminal->bank_name_hash) {
            return response()->json(['valid' => false, 'error' => 'Bank name hash mismatch']);
        }

        if (! $this->verifySignature($payload, (string) $terminal->signing_key, (string) ($packet['signature'] ?? ''))) {
            return response()->json(['valid' => false, 'error' => 'Invalid signature']);
        }

        $amount = (int) data_get($payload, 'transaction_details.total_amount_ngn', 0);

        return response()->json([
            'valid' => true,
            'merchant_name' => $terminal->merchant_name,
            'amount_ngn' => $amount,
            'masked_account_suffix' => $terminal->masked_account_suffix,
            'session_uuid' => $session,
            'terminal_id' => $terminalId,
            'recipient_account' => $terminal->account_number,
            'recipient_bank_code' => $terminal->recipient_bank_code,
            'business_id' => $terminal->business_id,
        ]);
    }

    public function registerTerminal(Request $request): JsonResponse
    {
        if (! $this->adminAuthorized($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'terminal_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'signing_key' => 'required|string|min:16|max:256',
            'merchant_name' => 'required|string|max:128',
            'bank_name' => 'required|string|max:64',
            'masked_account_suffix' => ['required', 'regex:/^\*{3}[0-9]{4}$/'],
            'account_number' => 'nullable|digits:10',
            'recipient_bank_code' => 'nullable|string|max:6',
            'business_id' => 'nullable|integer|exists:businesses,id',
        ]);

        $bankNameHash = 'sha256:'.hash('sha256', strtolower(trim($data['bank_name'])));
        $now = now();
        $row = [
            'signing_key' => $data['signing_key'],
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
            DB::table('broadcast_terminals')->where('terminal_id', $data['terminal_id'])->update($row);
        } else {
            DB::table('broadcast_terminals')->insert(array_merge($row, [
                'terminal_id' => $data['terminal_id'],
                'created_at' => $now,
            ]));
        }

        return response()->json([
            'ok' => true,
            'status' => 'registered',
            'terminal_id' => $data['terminal_id'],
        ]);
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

    private function consumeSession(string $sessionUuid, string $terminalId): bool
    {
        if (! Str::isUuid($sessionUuid)) {
            return false;
        }

        try {
            $inserted = DB::table('broadcast_used_sessions')->insertOrIgnore([
                'session_uuid' => $sessionUuid,
                'terminal_id' => $terminalId,
                'used_at' => (int) (microtime(true) * 1000),
            ]);

            return (int) $inserted > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Canonical JSON HMAC-SHA256 — must match checkout_broadcast signing (sort_keys + compact JSON).
     */
    private function verifySignature(array $payload, string $signingKey, string $signatureB64): bool
    {
        if ($signatureB64 === '' || $signingKey === '') {
            return false;
        }

        $canonical = $this->canonicalJson($this->sortKeysRecursive($payload));
        $expected = base64_encode(hash_hmac('sha256', $canonical, $signingKey, true));

        return hash_equals($expected, $signatureB64);
    }

    private function canonicalJson(array $data): string
    {
        // Match Python: json.dumps(..., sort_keys=True, separators=(",", ":"))
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function sortKeysRecursive(array $data): array
    {
        ksort($data);
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Distinguish list vs object: preserve list order; sort associative
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
}
