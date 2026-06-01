<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * PIN reset: name match (Tier 1 after upgrade), BVN/CAC re-check (Tier 2), reset web tokens, rate limits.
 */
class WhatsappWalletPinResetService
{
    private const FAIL_CACHE_PREFIX = 'wa_wallet_pin_reset_fail:';

    public function __construct(
        private WhatsappWalletPinSetupWebService $pinSetupWeb,
    ) {}

    public function profileNameForMatch(WhatsappWallet $wallet): string
    {
        $fname = trim((string) $wallet->kyc_fname);
        $lname = trim((string) $wallet->kyc_lname);
        if ($fname !== '' && $lname !== '') {
            return trim($fname.' '.$lname);
        }

        $sender = trim((string) $wallet->sender_name);
        if ($sender !== '') {
            return $sender;
        }

        return (string) ($wallet->displayName() ?? '');
    }

    public function bankNameForMatch(WhatsappWallet $wallet): string
    {
        return trim((string) $wallet->mevon_account_name);
    }

    public function canRunNameMatch(WhatsappWallet $wallet): bool
    {
        return $wallet->isTier2()
            && $this->bankNameForMatch($wallet) !== '';
    }

    public function nameMatchPasses(WhatsappWallet $wallet): bool
    {
        if (! $this->canRunNameMatch($wallet)) {
            return false;
        }

        return WhatsappWalletNameMatcher::passes(
            $this->profileNameForMatch($wallet),
            $this->bankNameForMatch($wallet)
        );
    }

    public function verifyProvidedName(WhatsappWallet $wallet, string $typedName): bool
    {
        $bank = $this->bankNameForMatch($wallet);
        if ($bank === '' || trim($typedName) === '') {
            return false;
        }

        return WhatsappWalletNameMatcher::passes($typedName, $bank);
    }

    public function verifyBvn(WhatsappWallet $wallet, string $input): bool
    {
        $stored = preg_replace('/\D/', '', (string) $wallet->kyc_bvn) ?? '';
        $given = preg_replace('/\D/', '', $input) ?? '';
        if (strlen($stored) !== 11 || strlen($given) !== 11) {
            return false;
        }

        return hash_equals($stored, $given);
    }

    public function verifyCac(WhatsappWallet $wallet, string $input): bool
    {
        $stored = strtoupper(preg_replace('/\s+/', '', (string) $wallet->kyc_cac) ?? '');
        $given = strtoupper(preg_replace('/\s+/', '', $input) ?? '');
        if ($stored === '' || $given === '') {
            return false;
        }

        return hash_equals($stored, $given);
    }

    public function isBusinessAccount(WhatsappWallet $wallet): bool
    {
        return strtolower(trim((string) $wallet->rubies_account_type)) === 'business';
    }

    public function shouldPromptBvn(WhatsappWallet $wallet): bool
    {
        if ($this->isBusinessAccount($wallet)) {
            return preg_replace('/\D/', '', (string) $wallet->kyc_bvn) !== '';
        }

        return true;
    }

    public function shouldPromptCac(WhatsappWallet $wallet): bool
    {
        return $this->isBusinessAccount($wallet)
            && ! $this->shouldPromptBvn($wallet);
    }

    public function isRateLimited(WhatsappWallet $wallet): bool
    {
        $n = (int) Cache::get($this->failCacheKey($wallet->id), 0);

        return $n >= $this->maxFailures();
    }

    public function recordFailure(WhatsappWallet $wallet, string $reason): void
    {
        $key = $this->failCacheKey($wallet->id);
        $n = (int) Cache::get($key, 0);
        Cache::put($key, $n + 1, now()->addMinutes($this->lockoutMinutes()));

        Log::info('whatsapp.wallet.pin_reset.'.$reason, [
            'wallet_id' => $wallet->id,
            'fail_count' => $n + 1,
        ]);
    }

    public function clearFailures(WhatsappWallet $wallet): void
    {
        Cache::forget($this->failCacheKey($wallet->id));
    }

    /**
     * @return array{ok: bool, error?: string, token?: string, url?: string}
     */
    public function createResetToken(
        WhatsappSession $session,
        string $instance,
        string $phone,
        WhatsappWallet $wallet,
    ): array {
        if (! $wallet->hasPin()) {
            return ['ok' => false, 'error' => 'no_pin'];
        }

        if ($this->isRateLimited($wallet)) {
            return ['ok' => false, 'error' => 'rate_limited'];
        }

        $created = $this->pinSetupWeb->createResetToken($session, $instance, $phone, $wallet);
        if (! ($created['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($created['error'] ?? 'token_failed')];
        }

        $this->clearFailures($wallet);
        Log::info('whatsapp.wallet.pin_reset.link_issued', ['wallet_id' => $wallet->id]);

        return [
            'ok' => true,
            'token' => (string) ($created['token'] ?? ''),
            'url' => $this->pinSetupWeb->resetUrl((string) ($created['token'] ?? '')),
        ];
    }

    private function failCacheKey(int $walletId): string
    {
        return self::FAIL_CACHE_PREFIX.$walletId;
    }

    private function maxFailures(): int
    {
        return max(3, min(20, (int) config('whatsapp.wallet.pin_reset_max_failures', 5)));
    }

    private function lockoutMinutes(): int
    {
        return max(5, min(120, (int) config('whatsapp.wallet.pin_reset_lockout_minutes', 15)));
    }
}
