<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * One-time web link to set a new WhatsApp wallet PIN (private page; link sent standalone in chat).
 */
class WhatsappWalletPinSetupWebService
{
    private const CACHE_PREFIX = 'wa_wallet_pin_setup:';

    public function __construct(
        private EvolutionWhatsAppClient $client,
    ) {}

    private function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX.$token;
    }

    private function ttlSeconds(): int
    {
        $m = (int) config(
            'whatsapp.wallet.pin_setup_web_ttl_minutes',
            (int) config('whatsapp.wallet.transfer_confirm_ttl_minutes', 15)
        );

        return max(300, max(5, min(60, $m)) * 60);
    }

    public function publicBaseUrl(): string
    {
        $b = rtrim((string) config('whatsapp.public_url', ''), '/');
        if ($b === '') {
            $b = rtrim((string) config('app.url'), '/');
        }

        return $b;
    }

    public function setupUrl(string $token): string
    {
        return $this->publicBaseUrl().'/wallet/whatsapp/set-pin/'.$token;
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function createAndStoreToken(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet
    ): array {
        if ($wallet->hasPin()) {
            return ['ok' => false, 'error' => 'PIN already set'];
        }

        $token = bin2hex(random_bytes(32));
        Cache::put($this->cacheKey($token), [
            'whatsapp_session_id' => $session->id,
            'wallet_id' => $wallet->id,
            'phone_e164' => $phone,
            'evolution_instance' => $instance,
        ], now()->addSeconds($this->ttlSeconds()));

        return ['ok' => true, 'token' => $token];
    }

    public function forgetToken(?string $token): void
    {
        if ($token !== null && $token !== '') {
            Cache::forget($this->cacheKey($token));
        }
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function describeToken(string $token): array
    {
        $payload = Cache::get($this->cacheKey($token));
        if (! is_array($payload)) {
            return ['ok' => false, 'error' => 'This link has expired or was already used.'];
        }

        $walletId = (int) ($payload['wallet_id'] ?? 0);
        $wallet = WhatsappWallet::query()->find($walletId);
        if (! $wallet || $wallet->hasPin()) {
            return ['ok' => false, 'error' => 'This link is no longer valid.'];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function completeSetup(string $token, string $pin, string $pinConfirmation): array
    {
        if (! preg_match('/^\d{4}$/', $pin) || ! preg_match('/^\d{4}$/', $pinConfirmation)) {
            return ['ok' => false, 'error' => 'Enter exactly 4 digits in both fields.'];
        }
        if ($pin !== $pinConfirmation) {
            return ['ok' => false, 'error' => 'The two PINs do not match.'];
        }

        $payload = Cache::get($this->cacheKey($token));
        if (! is_array($payload)) {
            return ['ok' => false, 'error' => 'This link has expired or was already used.'];
        }

        $sessionId = (int) ($payload['whatsapp_session_id'] ?? 0);
        $walletId = (int) ($payload['wallet_id'] ?? 0);
        $phone = (string) ($payload['phone_e164'] ?? '');
        $instance = (string) ($payload['evolution_instance'] ?? '');

        if ($sessionId < 1 || $walletId < 1 || $phone === '') {
            return ['ok' => false, 'error' => 'Invalid setup data.'];
        }

        $session = WhatsappSession::query()->find($sessionId);
        $wallet = WhatsappWallet::query()->find($walletId);
        if (! $session || ! $wallet || (string) $session->phone_e164 !== $phone || (string) $wallet->phone_e164 !== $phone) {
            return ['ok' => false, 'error' => 'Session no longer valid.'];
        }

        if ($wallet->hasPin()) {
            Cache::forget($this->cacheKey($token));

            return ['ok' => false, 'error' => 'A PIN is already set on this wallet.'];
        }

        $wallet->pin_hash = Hash::make($pin);
        $wallet->pin_set_at = now();
        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

        Cache::forget($this->cacheKey($token));

        $session->update([
            'chat_context' => [
                'step' => 'pin_sender_name',
            ],
        ]);

        if ($instance !== '') {
            $this->client->sendText(
                $instance,
                $phone,
                "*PIN saved* (secure page)\n\n".
                "*Your send name*\n\n".
                'Reply here with the name you want shown on transfers (e.g. your full name). '.
                'Between *2* and *120* characters, or *BACK* to skip for now.'
            );
        }

        return ['ok' => true];
    }
}
