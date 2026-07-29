<?php

namespace App\Services\Consumer;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Wallet payment authorization via PIN or short-lived passkey payment_token.
 */
final class ConsumerPaymentAuthService
{
    private const CACHE_PREFIX = 'consumer_payment_token:';

    /**
     * @return array<string, array<int, string>>
     */
    public function validationRules(): array
    {
        return [
            'pin' => ['nullable', 'regex:/^\d{4}$/', 'required_without:payment_token'],
            'payment_token' => ['nullable', 'string', 'max:128', 'required_without:pin'],
        ];
    }

    /**
     * @return array{ok: true, via_payment_token: bool}|array{ok: false, response: JsonResponse}
     */
    public function authorize(WhatsappWallet $wallet, ConsumerWalletApiAccount $account, Request $request): array
    {
        if ($wallet->isPinLocked()) {
            return [
                'ok' => false,
                'response' => response()->json(['success' => false, 'message' => 'PIN locked. Try later.'], 423),
            ];
        }

        $paymentToken = trim((string) ($request->input('payment_token') ?? ''));
        if ($paymentToken !== '') {
            if ($this->consumePaymentToken($paymentToken, (int) $account->id, (int) $wallet->id)) {
                return ['ok' => true, 'via_payment_token' => true];
            }

            return [
                'ok' => false,
                'response' => response()->json(['success' => false, 'message' => 'Invalid or expired payment authorization.'], 422),
            ];
        }

        $pin = (string) ($request->input('pin') ?? '');
        if ($pin === '' || ! app(ConsumerWalletPinVerifier::class)->verify($wallet, $pin)) {
            return [
                'ok' => false,
                'response' => response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422),
            ];
        }

        return ['ok' => true, 'via_payment_token' => false];
    }

    /**
     * @param  array<string, mixed>  $intent
     * @return array{payment_token: string, expires_at: string}
     */
    public function issuePaymentToken(int $accountId, int $walletId, array $intent): array
    {
        $token = 'ptok_'.Str::random(48);
        $ttlMinutes = max(1, (int) config('consumer_wallet.payment_token_ttl_minutes', 5));
        $expiresAt = now()->addMinutes($ttlMinutes);

        Cache::put(self::CACHE_PREFIX.$token, [
            'account_id' => $accountId,
            'wallet_id' => $walletId,
            'intent_hash' => ConsumerWebAuthnService::intentHash($intent),
            'action' => (string) ($intent['action'] ?? ''),
        ], $expiresAt);

        return [
            'payment_token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function consumePaymentToken(string $token, int $accountId, int $walletId): bool
    {
        $key = self::CACHE_PREFIX.$token;
        $cached = Cache::get($key);
        if (! is_array($cached)) {
            return false;
        }

        if ((int) ($cached['account_id'] ?? 0) !== $accountId
            || (int) ($cached['wallet_id'] ?? 0) !== $walletId) {
            return false;
        }

        Cache::forget($key);

        return true;
    }
}
