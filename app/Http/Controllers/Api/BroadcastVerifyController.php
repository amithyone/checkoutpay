<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Broadcast\BroadcastPayAtShopProximityPushService;
use App\Services\Broadcast\BroadcastBankNameHashMatcher;
use App\Services\Broadcast\BroadcastSessionPaymentMatcher;
use App\Services\Broadcast\BroadcastSessionService;
use App\Services\Broadcast\BroadcastSignatureVerifier;
use App\Services\Broadcast\BroadcastTerminalProvisioner;
use App\Services\Broadcast\BroadcastWireExpand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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

    private const VERIFY_PROFILE = '2026-07-31-v2.1-presence';

    public function __construct(
        private readonly BroadcastSignatureVerifier $signatures,
        private readonly BroadcastSessionService $sessions,
        private readonly BroadcastBankNameHashMatcher $bankNameHashes,
        private readonly BroadcastSessionPaymentMatcher $paymentMatcher,
        private readonly BroadcastTerminalProvisioner $terminalProvisioner,
        private readonly BroadcastWireExpand $wireExpand,
        private readonly BroadcastPayAtShopProximityPushService $proximityPush,
    ) {}

    public function health(): JsonResponse
    {
        $terminals = DB::table('broadcast_terminals')->where('active', 1)->count();

        return response()->json([
            'ok' => true,
            'status' => 'ok',
            'terminals' => $terminals,
            'verify_profile' => self::VERIFY_PROFILE,
            'features' => [
                'sync-signing-key',
                'wire-expand',
                'ed25519-kobo-amounts',
                'optional-bank-hash',
                'presence-idle',
                'compact-wire-vsidtid',
                'proximity-push',
            ],
        ]);
    }

    /**
     * POS: FCM nudge to CheckoutNow wallets while idle/presence beacon is active.
     * Auth: X-Terminal-Api-Key. Ignores device hostname — uses merchant_name from registry.
     */
    public function presenceNudge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'terminal_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'session_kind' => 'nullable|string|in:presence,pos_checkout',
        ]);

        $terminalId = (string) $data['terminal_id'];
        $terminal = $this->terminalAuthorized($request, $terminalId);
        if ($terminal === null) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $result = $this->proximityPush->notifyFromTerminal(
            $terminalId,
            (string) ($data['session_kind'] ?? 'presence'),
        );

        if (! ($result['ok'] ?? false)) {
            $status = ($result['error'] ?? '') === 'Unauthorized' ? 401 : 200;

            return response()->json([
                'ok' => false,
                'error' => $result['error'] ?? 'Push not sent',
                'merchant_name' => $result['merchant_name'] ?? null,
            ], $status === 401 ? 401 : 422);
        }

        return response()->json([
            'ok' => true,
            'sent' => $result['sent'],
            'skipped' => $result['skipped'],
            'merchant_name' => $result['merchant_name'],
            'title' => config('broadcast.pay_at_shop_proximity_push_title'),
            'body' => $this->proximityPush->buildBody($result['merchant_name']),
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

        $packet = $this->wireExpand->normalizeForVerify($request->all());
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
        $packetAmount = (int) data_get($payload, 'transaction_details.total_amount_ngn', 0);
        $signatureAlg = (string) ($packet['signature_alg'] ?? $packet['alg'] ?? '');
        $logBase['terminal_id'] = $terminalId;
        $logBase['session_uuid'] = $session;
        $logBase['signature_alg'] = $signatureAlg;
        $logBase['payload_timestamp_ms'] = array_key_exists('timestamp_ms', $payload)
            ? $payload['timestamp_ms']
            : null;

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

        if ($signatureAlg === '') {
            $signatureAlg = str_starts_with(strtoupper($terminalId), 'CP-')
                ? 'ed25519'
                : (string) ($terminal->signature_alg ?? 'HMAC-SHA256');
        }
        $sessionAmountKobo = $packetAmount;
        $sessionKind = $this->resolveSessionKind($payload, $sessionAmountKobo);
        $isPresence = $sessionKind === 'presence';
        $displayAmountNgn = $isPresence ? 0.0 : $this->displayAmountNgn($packetAmount, $signatureAlg);
        $logBase['amount_ngn'] = $displayAmountNgn;
        $logBase['session_kind'] = $sessionKind;
        $logBase['signature_alg'] = $signatureAlg;

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

        $timestampMs = $this->parsePayloadTimestampMs($payload);
        if ($timestampMs === null) {
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => 'Missing timestamp_ms in payload',
                'http_status' => 200,
            ]);

            return response()->json(['valid' => false, 'error' => 'Missing timestamp_ms in payload']);
        }

        if ($session === '' || ! Str::isUuid($session)) {
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => 'Invalid session UUID',
                'http_status' => 200,
            ]);

            return response()->json(['valid' => false, 'error' => 'Invalid session UUID']);
        }

        $existingSession = $this->sessions->find($session);
        if ($existingSession !== null) {
            if ($existingSession->status === BroadcastSessionService::STATUS_PAID) {
                $this->logVerifyAttempt($logBase, [
                    'valid' => false,
                    'error' => 'Session already paid',
                    'session_status' => BroadcastSessionService::STATUS_PAID,
                    'http_status' => 200,
                ]);

                return response()->json([
                    'valid' => false,
                    'error' => 'Session already paid',
                    'session_status' => BroadcastSessionService::STATUS_PAID,
                ]);
            }

            if ($existingSession->status === BroadcastSessionService::STATUS_CANCELLED) {
                $this->logVerifyAttempt($logBase, [
                    'valid' => false,
                    'error' => 'Session cancelled',
                    'session_status' => BroadcastSessionService::STATUS_CANCELLED,
                    'http_status' => 200,
                ]);

                return response()->json([
                    'valid' => false,
                    'error' => 'Session cancelled',
                    'session_status' => BroadcastSessionService::STATUS_CANCELLED,
                ]);
            }

            if ($existingSession->terminal_id !== $terminalId) {
                $this->logVerifyAttempt($logBase, [
                    'valid' => false,
                    'error' => 'Session terminal mismatch',
                    'http_status' => 200,
                ]);

                return response()->json(['valid' => false, 'error' => 'Session terminal mismatch']);
            }
        }

        $sessionIsOpen = $existingSession !== null
            && in_array($existingSession->status, [
                BroadcastSessionService::STATUS_OPEN,
                BroadcastSessionPaymentMatcher::STATUS_PARTIAL,
                BroadcastSessionPaymentMatcher::STATUS_AWAITING_PAYMENT,
            ], true);

        if (! $sessionIsOpen && abs((int) (microtime(true) * 1000) - $timestampMs) > self::MAX_AGE_MS) {
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => 'Timestamp outside allowed window',
                'http_status' => 200,
            ]);

            return response()->json(['valid' => false, 'error' => 'Timestamp outside allowed window']);
        }

        $display = $payload['account_info_public_display'] ?? [];
        if (! is_array($display)) {
            $display = [];
        }

        $receivedBankNameHash = (string) ($display['bank_name_hash'] ?? '');
        $bankNameMatch = $this->bankNameHashes->evaluate($receivedBankNameHash, $terminal);

        // BLE bank_name_hash is optional — authoritative bank/account come from terminal registry after signature check.
        if ($receivedBankNameHash !== '' && ! $bankNameMatch['matched']) {
            $logBase['bank_name_hash_ignored'] = true;
            $logBase['received_bank_name_hash'] = $receivedBankNameHash;
            $logBase['settlement_bank_name'] = $terminal->bank_name;
        } elseif ($bankNameMatch['matched_bank_name'] !== null
            && BroadcastBankNameHashMatcher::hashBankName($bankNameMatch['matched_bank_name']) !== $terminal->bank_name_hash) {
            $logBase['bank_name_hash_matched_via'] = $bankNameMatch['matched_bank_name'];
        }

        $verified = $this->verifyPacketSignature(
            $payload,
            $signatureAlg,
            (string) ($packet['signature'] ?? ''),
            $terminal,
        );

        if (! $verified) {
            $this->logVerifyAttempt($logBase, [
                'valid' => false,
                'error' => $this->signatureFailureReason($terminal, $signatureAlg),
                'http_status' => 200,
                'verify_profile' => self::VERIFY_PROFILE,
            ]);

            return response()->json([
                'valid' => false,
                'error' => $this->signatureFailureReason($terminal, $signatureAlg),
            ]);
        }

        $sessionReplay = $existingSession !== null;

        $maskedSuffix = (string) $terminal->masked_account_suffix;
        $connectivity = (string) ($payload['connectivity'] ?? 'online');
        $offlineSettlement = is_array($payload['offline_settlement'] ?? null)
            ? $payload['offline_settlement']
            : null;

        $recipientAccount = (string) $terminal->account_number;
        $recipientBankCode = $terminal->recipient_bank_code;
        $bankName = (string) $terminal->bank_name;
        $recipientAccountName = (string) $terminal->merchant_name;

        if ($connectivity === 'offline' && is_array($offlineSettlement)) {
            $recipientAccount = (string) ($offlineSettlement['recipient_account'] ?? $recipientAccount);
            $recipientBankCode = (string) ($offlineSettlement['recipient_bank_code'] ?? $recipientBankCode);
            $bankName = (string) ($offlineSettlement['bank_name'] ?? $bankName);
            $recipientAccountName = (string) ($offlineSettlement['recipient_account_name'] ?? $recipientAccountName);
            $logBase['connectivity'] = 'offline';
        } else {
            $logBase['connectivity'] = 'online';
        }

        [$settlementMode, $settlementAccount] = $this->resolveSessionSettlement(
            $terminal,
            $recipientAccount,
        );

        if (! $isPresence) {
            $this->sessions->open($session, $terminalId, $sessionAmountKobo, $settlementMode, $settlementAccount);
            $this->recordSession($session, $terminalId);
        }

        $this->logVerifyAttempt($logBase, [
            'valid' => true,
            'http_status' => 200,
            'session_status' => BroadcastSessionService::STATUS_OPEN,
            'recipient_account_suffix' => $maskedSuffix,
            'idempotent_replay' => $sessionReplay,
        ]);

        return response()->json([
            'valid' => true,
            'merchant_name' => $terminal->merchant_name,
            'recipient_account_name' => $recipientAccountName,
            'amount_ngn' => $displayAmountNgn,
            'session_kind' => $sessionKind,
            'bank_name' => $bankName,
            'masked_account_suffix' => $maskedSuffix,
            'session_uuid' => $session,
            'session_status' => BroadcastSessionService::STATUS_OPEN,
            'terminal_id' => $terminalId,
            'terminal_label' => $this->terminalPickerLabel($terminalId),
            'connectivity' => $connectivity,
            'recipient_account' => $recipientAccount,
            'recipient_bank_code' => $recipientBankCode,
        ]);
    }

    public function sessionStatus(Request $request, string $sessionUuid): JsonResponse
    {
        if (! Str::isUuid($sessionUuid)) {
            return response()->json(['error' => 'Invalid session UUID'], 422);
        }

        $terminalId = (string) $request->query('terminal_id', '');
        $terminal = $this->terminalAuthorized($request, $terminalId);
        if ($terminal === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $session = $this->sessions->find($sessionUuid);
        if ($session === null || $session->terminal_id !== $terminalId) {
            return response()->json([
                'session_uuid' => $sessionUuid,
                'session_status' => 'awaiting_scan',
                'terminal_id' => $terminalId,
                'merchant_name' => $terminal->merchant_name,
            ]);
        }

        $payload = [
            'session_uuid' => $sessionUuid,
            'session_status' => $session->status,
            'amount_ngn' => (int) $session->amount_ngn,
            'amount_received_ngn' => (int) ($session->amount_received_ngn ?? 0),
            'amount_due_ngn' => max(0, (int) $session->amount_ngn - (int) ($session->amount_received_ngn ?? 0)),
            'settlement_mode' => $session->settlement_mode ?? 'permanent',
            'awaiting_payment' => ($session->settlement_mode ?? 'permanent') === 'permanent'
                && empty($session->expecting_payment_at),
            'terminal_id' => $terminalId,
            'merchant_name' => $terminal->merchant_name,
            'bank_name' => $terminal->bank_name,
            'masked_account_suffix' => $terminal->masked_account_suffix,
        ];

        if ($session->status === BroadcastSessionService::STATUS_PAID) {
            $payload['recipient_account'] = $terminal->account_number;
            $payload['recipient_account_name'] = $terminal->merchant_name;
            $payload['recipient_bank_code'] = $terminal->recipient_bank_code;
            if (! empty($session->closed_at)) {
                $payload['paid_at_ms'] = (int) $session->closed_at;
            }
        }

        return response()->json($payload);
    }

    public function expectPayment(Request $request, string $sessionUuid): JsonResponse
    {
        if (! Str::isUuid($sessionUuid)) {
            return response()->json(['error' => 'Invalid session UUID'], 422);
        }

        $data = $request->validate([
            'terminal_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $terminalId = (string) $data['terminal_id'];
        $terminal = $this->terminalAuthorized($request, $terminalId);
        if ($terminal === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $session = $this->sessions->find($sessionUuid);
        if ($session === null || $session->terminal_id !== $terminalId) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        if ($session->status === BroadcastSessionService::STATUS_PAID) {
            return response()->json([
                'ok' => true,
                'session_status' => BroadcastSessionService::STATUS_PAID,
            ]);
        }

        if ($session->status === BroadcastSessionService::STATUS_CANCELLED) {
            return response()->json(['error' => 'Session cancelled'], 409);
        }

        $this->paymentMatcher->markExpectingPayment($sessionUuid, $terminalId);
        $updated = $this->sessions->find($sessionUuid);

        return response()->json([
            'ok' => true,
            'session_uuid' => $sessionUuid,
            'session_status' => $updated->status ?? BroadcastSessionPaymentMatcher::STATUS_AWAITING_PAYMENT,
            'amount_ngn' => (int) ($updated->amount_ngn ?? 0),
        ]);
    }

    public function cancelSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_uuid_v4' => 'required|uuid',
            'terminal_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
        ]);

        $sessionUuid = (string) $data['session_uuid_v4'];
        $terminalId = (string) $data['terminal_id'];

        $existing = $this->sessions->find($sessionUuid);
        if ($existing === null) {
            return response()->json([
                'ok' => true,
                'session_status' => BroadcastSessionService::STATUS_CANCELLED,
            ]);
        }

        if ($existing->terminal_id !== $terminalId) {
            return response()->json(['error' => 'Session terminal mismatch'], 422);
        }

        if ($existing->status === BroadcastSessionService::STATUS_PAID) {
            return response()->json([
                'error' => 'Session already paid',
                'session_status' => BroadcastSessionService::STATUS_PAID,
            ], 409);
        }

        if (! in_array($existing->status, [
            BroadcastSessionService::STATUS_OPEN,
            BroadcastSessionPaymentMatcher::STATUS_PARTIAL,
            BroadcastSessionPaymentMatcher::STATUS_AWAITING_PAYMENT,
        ], true)) {
            return response()->json([
                'ok' => true,
                'session_status' => $existing->status,
            ]);
        }

        $this->sessions->markCancelled($sessionUuid, $terminalId);

        return response()->json([
            'ok' => true,
            'session_status' => BroadcastSessionService::STATUS_CANCELLED,
        ]);
    }

    /**
     * POS / Cheko: register the Ed25519 seed from this machine on CheckoutPay.
     * Auth: X-Terminal-Api-Key for the terminal_id in the body.
     */
    public function syncSigningKey(Request $request): JsonResponse
    {
        $data = $request->validate([
            'terminal_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'signing_key' => 'required|string|min:32|max:256',
        ]);

        $terminalId = (string) $data['terminal_id'];
        $terminal = $this->terminalAuthorized($request, $terminalId);
        if ($terminal === null) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->terminalProvisioner->syncSigningKeyFromPos(
                $terminalId,
                (string) $data['signing_key'],
            );
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'terminal_id' => $terminalId,
            'signature_alg' => 'ED25519',
            'public_key_prefix' => substr($result['public_key'], 0, 12),
            'message' => 'Signing key synced — CheckoutPay verify will accept packets signed on this POS.',
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
            if ($signingKey !== null && $signingKey !== '' && $publicKey === null) {
                $derived = $this->signatures->derivePublicKeyFromSigningKey($signingKey);
                if ($derived === null) {
                    return response()->json(['error' => 'Invalid Ed25519 signing_key'], 422);
                }
                $publicKey = $derived;
                $signingKey = Crypt::encryptString($signingKey);
            } elseif ($publicKey === null && ($data['generate_signing_key'] ?? true)) {
                $keypair = $this->signatures->generateEd25519Keypair();
                $publicKey = $keypair['public_key'];
                $generatedSigningKey = $keypair['signing_key'];
                $signingKey = Crypt::encryptString($keypair['signing_key']);
            } elseif ($publicKey === null) {
                return response()->json([
                    'error' => 'public_key or signing_key is required for ed25519 terminals',
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

    private function terminalPickerLabel(string $terminalId): string
    {
        $upper = strtoupper($terminalId);
        if (str_starts_with($upper, 'CP-')) {
            return $terminalId;
        }
        if (str_starts_with($upper, 'TERM-')) {
            $suffix = substr($terminalId, strrpos($terminalId, '-') + 1);
            if (ctype_digit($suffix)) {
                return str_pad(substr($suffix, -2), 2, '0', STR_PAD_LEFT);
            }
        }

        return substr($terminalId, 0, 8);
    }

    private function displayAmountNgn(int $packetAmount, string $signatureAlg): float
    {
        if (strtolower(trim($signatureAlg)) === 'ed25519') {
            return round($packetAmount / 100, 2);
        }

        return (float) $packetAmount;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verifyPacketSignature(array $payload, string $signatureAlg, string $signature, object $terminal): bool
    {
        $alg = strtoupper(trim($signatureAlg));

        if ($alg !== 'ED25519') {
            return $this->signatures->verify(
                $payload,
                $signatureAlg,
                $signature,
                (string) $terminal->signing_key,
                null,
            );
        }

        $storedPublic = trim((string) ($terminal->public_key ?? ''));
        $candidates = array_values(array_unique(array_filter([
            $storedPublic !== '' ? $storedPublic : null,
            $this->maybePublicKeyFromSecretField($storedPublic),
            ($secret = $this->terminalProvisioner->decryptSigningKey($terminal)) !== null
                ? $this->signatures->derivePublicKeyFromSigningKey($secret)
                : null,
        ])));

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== ''
                && $this->signatures->verify($payload, $signatureAlg, $signature, '', $candidate)) {
                if ($candidate !== $storedPublic) {
                    DB::table('broadcast_terminals')
                        ->where('terminal_id', $terminal->terminal_id)
                        ->update(['public_key' => $candidate, 'updated_at' => now()]);
                }

                return true;
            }
        }

        return false;
    }

    private function signatureFailureReason(object $terminal, string $signatureAlg): string
    {
        if (strtoupper(trim($signatureAlg)) !== 'ED25519') {
            return 'Invalid signature';
        }

        $storedPublic = trim((string) ($terminal->public_key ?? ''));
        if ($storedPublic === '' && $this->terminalProvisioner->decryptSigningKey($terminal) === null) {
            return 'Invalid signature — terminal has no Ed25519 public key; sync POS signing key on CheckoutPay';
        }

        return 'Invalid signature — POS signing key does not match CheckoutPay terminal registry (use sync-signing-key)';
    }

    /** Some rows accidentally store the 64-byte secret in public_key — derive the real public key. */
    private function maybePublicKeyFromSecretField(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return $this->signatures->derivePublicKeyFromSigningKey($value);
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

    /**
     * @return array{0: string, 1: ?string}
     */
    private function resolveSessionSettlement(object $terminal, string $recipientAccount): array
    {
        $settlementMode = 'permanent';
        if (! empty($terminal->business_id)) {
            $business = DB::table('businesses')
                ->where('id', $terminal->business_id)
                ->first(['broadcast_pay_at_shop_permanent_settlement']);
            if ($business !== null && ! (bool) ($business->broadcast_pay_at_shop_permanent_settlement ?? true)) {
                $settlementMode = 'temporary';
            }
        }

        $account = preg_replace('/\D/', '', $recipientAccount) ?: null;

        return [$settlementMode, $account !== '' ? $account : null];
    }

    private function terminalAuthorized(Request $request, string $terminalId): ?object
    {
        if ($terminalId === '') {
            return null;
        }

        $provided = (string) ($request->header('X-Terminal-Api-Key') ?? $request->header('X-Api-Key') ?? '');
        if ($provided === '') {
            return null;
        }

        $terminal = DB::table('broadcast_terminals')
            ->where('terminal_id', $terminalId)
            ->where('active', 1)
            ->first();

        if ($terminal === null || ! hash_equals((string) $terminal->api_key, $provided)) {
            return null;
        }

        return $terminal;
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveSessionKind(array $payload, int $packetAmountKobo): string
    {
        $kind = strtolower(trim((string) ($payload['session_kind'] ?? '')));
        if (in_array($kind, ['presence', 'idle', 'beacon'], true)) {
            return 'presence';
        }

        if ($packetAmountKobo <= 0) {
            return 'presence';
        }

        return 'pos_checkout';
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
     * @param  array<string, mixed>  $payload
     */
    private function parsePayloadTimestampMs(array $payload): ?int
    {
        if (! array_key_exists('timestamp_ms', $payload)) {
            return null;
        }

        $raw = $payload['timestamp_ms'];
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $timestampMs = (int) $raw;

        return $timestampMs > 0 ? $timestampMs : null;
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
