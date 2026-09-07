<?php

namespace App\Services\Consumer;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappWalletNameMatcher;
use App\Services\Whatsapp\WhatsappWalletPinResetService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Stolen-phone lockdown: anyone with phone + PIN + DOB can freeze the wallet.
 * Only the owner can unlock (email code, frequent recipients, Tier 2 BVN/NIN, PIN).
 */
class ConsumerWalletLockdownService
{
    private const CACHE_UNLOCK = 'consumer_wallet_lockdown_unlock:';

    private const CACHE_EMAIL = 'consumer_wallet_lockdown_email:';

    private const CACHE_FAIL = 'consumer_wallet_lockdown_fail:';

    public function __construct(
        private ConsumerWalletPinVerifier $pinVerifier,
        private WhatsappWalletPinResetService $pinReset,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function lockDown(string $phoneInput, string $pin, string $dob, ?string $countryIso = null): array
    {
        $wallet = $this->walletForPhone($phoneInput, $countryIso);
        if ($wallet === null || ! $wallet->hasPin()) {
            return ['ok' => false, 'message' => 'No wallet found for this number.'];
        }

        if ($this->isRateLimited($wallet)) {
            return ['ok' => false, 'message' => 'Too many lockdown attempts. Try again in about 15 minutes.'];
        }

        if ($wallet->isLockedDown()) {
            return ['ok' => true, 'message' => 'This wallet is already locked down.'];
        }

        if (! $this->pinVerifier->verify($wallet, $pin)) {
            $this->recordFailure($wallet, 'lock_pin_failed');

            return ['ok' => false, 'message' => 'Wallet PIN or date of birth does not match.'];
        }

        if (! $this->dobMatches($wallet, $dob)) {
            $this->recordFailure($wallet, 'lock_dob_failed');

            return ['ok' => false, 'message' => 'Wallet PIN or date of birth does not match.'];
        }

        $wallet->locked_down_at = now();
        $wallet->save();
        $this->revokeSessions($wallet);
        $this->clearFailures($wallet);

        Log::info('consumer_wallet.lockdown.locked', ['wallet_id' => $wallet->id]);

        return ['ok' => true, 'message' => 'Wallet locked down. Login and transfers are blocked until the owner unlocks it.'];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function unlockStart(string $phoneInput, ?string $countryIso = null): array
    {
        $wallet = $this->walletForPhone($phoneInput, $countryIso);
        if ($wallet === null || ! $wallet->hasPin()) {
            return ['ok' => false, 'message' => 'No wallet found for this number.'];
        }

        if (! $wallet->isLockedDown()) {
            return ['ok' => false, 'message' => 'This wallet is not locked down.'];
        }

        if ($this->isRateLimited($wallet)) {
            return ['ok' => false, 'message' => 'Too many unlock attempts. Try again in about 15 minutes.'];
        }

        $email = $wallet->resolveOtpEmail();
        $peopleRequired = $this->peopleRequired($wallet);
        $needsIdentity = $wallet->isTier2() && $this->pinReset->storedIdentityDigits($wallet) !== '';
        $token = bin2hex(random_bytes(32));
        Cache::put($this->unlockCacheKey($token), [
            'wallet_id' => $wallet->id,
            'phone_e164' => (string) $wallet->phone_e164,
            'email_verified' => $email === null,
        ], now()->addMinutes($this->sessionTtlMinutes()));

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => [
                'unlock_token' => $token,
                'phone_e164' => (string) $wallet->phone_e164,
                'tier' => (int) $wallet->tier,
                'needs_email' => $email !== null,
                'email_masked' => $email !== null ? $this->maskEmail($email) : null,
                'needs_people' => $peopleRequired > 0,
                'people_required' => $peopleRequired,
                'people_prompt' => $peopleRequired > 0
                    ? 'Enter the phone number or name of '.$peopleRequired.' '.($peopleRequired === 1 ? 'person' : 'people').' you often send money to.'
                    : null,
                'needs_identity' => $needsIdentity,
                'identity_suffix' => $needsIdentity ? substr($this->pinReset->storedIdentityDigits($wallet), -4) : null,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function requestUnlockEmail(string $unlockToken, string $emailInput): array
    {
        $session = $this->unlockSession($unlockToken);
        if ($session === null) {
            return ['ok' => false, 'message' => 'Unlock session expired. Start again.'];
        }

        $wallet = WhatsappWallet::query()->find((int) $session['wallet_id']);
        if (! $wallet || ! $wallet->isLockedDown()) {
            return ['ok' => false, 'message' => 'Unlock session is no longer valid.'];
        }

        $stored = $wallet->resolveOtpEmail();
        if ($stored === null) {
            return ['ok' => true, 'message' => 'No email on this wallet. Continue.'];
        }

        $typed = strtolower(trim($emailInput));
        if ($typed === '' || ! hash_equals($stored, $typed)) {
            $this->recordFailure($wallet, 'unlock_email_mismatch');

            return ['ok' => false, 'message' => 'That email does not match this wallet.'];
        }

        $ttl = max(60, (int) config('consumer_wallet.otp_ttl_seconds', 600));
        $len = max(4, min(8, (int) config('consumer_wallet.otp_length', 6)));
        $code = str_pad((string) random_int(0, (10 ** $len) - 1), $len, '0', STR_PAD_LEFT);
        Cache::put($this->emailCacheKey((int) $wallet->id), [
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addSeconds($ttl)->timestamp,
        ], $ttl);

        try {
            $brand = (string) config('whatsapp.bot_brand_name', 'Checkout');
            Mail::send('emails.login-otp-code', [
                'code' => $code,
                'ttlMinutes' => max(1, (int) round($ttl / 60)),
            ], function ($message) use ($stored, $brand) {
                $message->to($stored)->subject("Your {$brand} unlock code");
            });
        } catch (\Throwable $e) {
            Cache::forget($this->emailCacheKey((int) $wallet->id));
            Log::warning('consumer_wallet.lockdown.email_failed', ['error' => $e->getMessage(), 'wallet_id' => $wallet->id]);

            return ['ok' => false, 'message' => 'Could not send the email code. Try again later.'];
        }

        return [
            'ok' => true,
            'message' => 'We emailed a code to '.$this->maskEmail($stored).'.',
            'data' => ['email_masked' => $this->maskEmail($stored)],
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function verifyUnlockEmail(string $unlockToken, string $code): array
    {
        $session = $this->unlockSession($unlockToken);
        if ($session === null) {
            return ['ok' => false, 'message' => 'Unlock session expired. Start again.'];
        }

        $wallet = WhatsappWallet::query()->find((int) $session['wallet_id']);
        if (! $wallet || ! $wallet->isLockedDown()) {
            return ['ok' => false, 'message' => 'Unlock session is no longer valid.'];
        }

        if ($wallet->resolveOtpEmail() === null) {
            return ['ok' => true, 'message' => 'No email check needed.'];
        }

        $payload = Cache::get($this->emailCacheKey((int) $wallet->id));
        if (! is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) {
            return ['ok' => false, 'message' => 'Email code expired. Request a new one.'];
        }

        $given = preg_replace('/\D/', '', $code) ?? '';
        if ($given === '' || ! hash_equals((string) ($payload['code_hash'] ?? ''), hash('sha256', $given))) {
            $this->recordFailure($wallet, 'unlock_email_code_failed');

            return ['ok' => false, 'message' => 'That email code is incorrect.'];
        }

        $session['email_verified'] = true;
        Cache::put($this->unlockCacheKey($unlockToken), $session, now()->addMinutes($this->sessionTtlMinutes()));
        Cache::forget($this->emailCacheKey((int) $wallet->id));

        return ['ok' => true, 'message' => 'Email confirmed.'];
    }

    /**
     * @param  list<string>  $people
     * @return array{ok: bool, message: string}
     */
    public function unlock(string $unlockToken, string $pin, array $people, ?string $identity = null): array
    {
        $session = $this->unlockSession($unlockToken);
        if ($session === null) {
            return ['ok' => false, 'message' => 'Unlock session expired. Start again.'];
        }

        $wallet = WhatsappWallet::query()->find((int) $session['wallet_id']);
        if (! $wallet || ! $wallet->isLockedDown() || ! $wallet->hasPin()) {
            return ['ok' => false, 'message' => 'Unlock session is no longer valid.'];
        }

        if ($this->isRateLimited($wallet)) {
            return ['ok' => false, 'message' => 'Too many unlock attempts. Try again later.'];
        }

        if ($wallet->resolveOtpEmail() !== null && empty($session['email_verified'])) {
            return ['ok' => false, 'message' => 'Confirm the email code first.'];
        }

        $peopleRequired = $this->peopleRequired($wallet);
        if ($peopleRequired > 0 && ! $this->peopleMatch($wallet, $people, $peopleRequired)) {
            $this->recordFailure($wallet, 'unlock_people_failed');

            return ['ok' => false, 'message' => 'Those frequent recipients do not match this wallet.'];
        }

        if ($wallet->isTier2() && $this->pinReset->storedIdentityDigits($wallet) !== '') {
            if (! $this->pinReset->verifyBvn($wallet, (string) $identity)) {
                $this->recordFailure($wallet, 'unlock_identity_failed');

                return ['ok' => false, 'message' => 'BVN or NIN does not match our records.'];
            }
        }

        if (! $this->pinVerifier->verify($wallet, $pin)) {
            $this->recordFailure($wallet, 'unlock_pin_failed');

            return ['ok' => false, 'message' => 'Wallet PIN is incorrect.'];
        }

        $wallet->locked_down_at = null;
        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();
        Cache::forget($this->unlockCacheKey($unlockToken));
        $this->clearFailures($wallet);

        Log::info('consumer_wallet.lockdown.unlocked', ['wallet_id' => $wallet->id]);

        return ['ok' => true, 'message' => 'Wallet unlocked. You can sign in again.'];
    }

    private function walletForPhone(string $phoneInput, ?string $countryIso = null): ?WhatsappWallet
    {
        $e164 = WhatsappWallet::resolveAuthE164($phoneInput, $countryIso);
        if ($e164 === null) {
            return null;
        }

        return WhatsappWallet::findByPhoneE164($e164);
    }

    private function dobMatches(WhatsappWallet $wallet, string $dob): bool
    {
        if ($wallet->kyc_dob === null) {
            return true;
        }

        try {
            $given = Carbon::createFromFormat('Y-m-d', trim($dob))?->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        if ($given === null) {
            return false;
        }

        return $wallet->kyc_dob->copy()->startOfDay()->equalTo($given);
    }

    /**
     * @return list<array{phone: string, name: string}>
     */
    private function frequentRecipients(WhatsappWallet $wallet): array
    {
        $rows = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('type', WhatsappWalletTransaction::TYPE_P2P_DEBIT)
            ->whereNotNull('counterparty_phone_e164')
            ->orderByDesc('id')
            ->limit(80)
            ->get(['counterparty_phone_e164', 'counterparty_account_name', 'sender_name']);

        $counts = [];
        foreach ($rows as $row) {
            $phone = trim((string) $row->counterparty_phone_e164);
            if ($phone === '') {
                continue;
            }
            if (! isset($counts[$phone])) {
                $counts[$phone] = [
                    'phone' => $phone,
                    'name' => trim((string) ($row->counterparty_account_name ?: $row->sender_name)),
                    'n' => 0,
                ];
            }
            $counts[$phone]['n']++;
        }

        usort($counts, static fn (array $a, array $b): int => $b['n'] <=> $a['n']);

        return array_values(array_map(static fn (array $row): array => [
            'phone' => $row['phone'],
            'name' => $row['name'],
        ], array_slice($counts, 0, 8)));
    }

    private function peopleRequired(WhatsappWallet $wallet): int
    {
        $n = count($this->frequentRecipients($wallet));
        if ($n === 0) {
            return 0;
        }

        return $n === 1 ? 1 : 2;
    }

    /**
     * @param  list<string>  $hints
     */
    private function peopleMatch(WhatsappWallet $wallet, array $hints, int $required): bool
    {
        $recipients = $this->frequentRecipients($wallet);
        $matched = [];
        foreach ($hints as $hint) {
            $hint = trim((string) $hint);
            if ($hint === '') {
                continue;
            }
            foreach ($recipients as $i => $recipient) {
                if (isset($matched[$i])) {
                    continue;
                }
                if ($this->recipientHintMatches($recipient, $hint)) {
                    $matched[$i] = true;
                    break;
                }
            }
        }

        return count($matched) >= $required;
    }

    /**
     * @param  array{phone: string, name: string}  $recipient
     */
    private function recipientHintMatches(array $recipient, string $hint): bool
    {
        $digits = PhoneNormalizer::digitsOnly($hint);
        if ($digits !== null && strlen($digits) >= 10) {
            $canonical = PhoneNormalizer::canonicalNgE164Digits($hint)
                ?? PhoneNormalizer::canonicalAuthE164Digits($hint, null)
                ?? $digits;
            $phone = $recipient['phone'];
            if ($canonical === $phone || str_ends_with($phone, substr($canonical, -10))) {
                return true;
            }
        }

        $name = trim($recipient['name']);
        if ($name === '') {
            return false;
        }
        if (WhatsappWalletNameMatcher::passes($hint, $name)) {
            return true;
        }
        $hintNorm = WhatsappWalletNameMatcher::normalizePersonName($hint);
        $first = explode(' ', WhatsappWalletNameMatcher::normalizePersonName($name))[0] ?? '';

        return $first !== '' && $hintNorm !== '' && ($first === $hintNorm || str_starts_with($first, $hintNorm) || str_starts_with($hintNorm, $first));
    }

    private function revokeSessions(WhatsappWallet $wallet): void
    {
        $accounts = ConsumerWalletApiAccount::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->orWhere('phone_e164', (string) $wallet->phone_e164)
            ->get();
        foreach ($accounts as $account) {
            $account->tokens()->delete();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function unlockSession(string $token): ?array
    {
        $payload = Cache::get($this->unlockCacheKey($token));

        return is_array($payload) ? $payload : null;
    }

    private function unlockCacheKey(string $token): string
    {
        return self::CACHE_UNLOCK.$token;
    }

    private function emailCacheKey(int $walletId): string
    {
        return self::CACHE_EMAIL.$walletId;
    }

    private function failCacheKey(int $walletId): string
    {
        return self::CACHE_FAIL.$walletId;
    }

    private function isRateLimited(WhatsappWallet $wallet): bool
    {
        return (int) Cache::get($this->failCacheKey($wallet->id), 0) >= 5;
    }

    private function recordFailure(WhatsappWallet $wallet, string $reason): void
    {
        $key = $this->failCacheKey($wallet->id);
        $n = (int) Cache::get($key, 0);
        Cache::put($key, $n + 1, now()->addMinutes(15));
        Log::info('consumer_wallet.lockdown.'.$reason, [
            'wallet_id' => $wallet->id,
            'fail_count' => $n + 1,
        ]);
    }

    private function clearFailures(WhatsappWallet $wallet): void
    {
        Cache::forget($this->failCacheKey($wallet->id));
    }

    private function sessionTtlMinutes(): int
    {
        return max(5, min(30, (int) config('consumer_wallet.pin_recovery_ttl_minutes', 15)));
    }

    private function maskEmail(string $email): string
    {
        $email = strtolower(trim($email));
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2 || $parts[0] === '') {
            return '***';
        }
        $local = $parts[0];
        $visible = substr($local, 0, 1);
        $maskedLocal = strlen($local) <= 2 ? $visible.'*' : $visible.str_repeat('*', min(6, strlen($local) - 1));

        return $maskedLocal.'@'.$parts[1];
    }
}
