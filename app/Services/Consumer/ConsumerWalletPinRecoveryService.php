<?php

namespace App\Services\Consumer;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappWalletNameMatcher;
use App\Services\Whatsapp\WhatsappWalletPinResetService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Unauthenticated app PIN recovery: Tier 1 via first P2P credit quiz, Tier 2 via BVN or bank name match.
 */
class ConsumerWalletPinRecoveryService
{
    private const CACHE_RECOVERY = 'consumer_wallet_pin_recovery:';

    private const CACHE_FAIL = 'consumer_wallet_pin_recovery_fail:';

    public function __construct(
        private WhatsappWalletPinResetService $pinReset,
        private ConsumerWalletOtpService $otp,
    ) {}

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function options(string $phoneInput): array
    {
        $wallet = $this->walletForPhone($phoneInput);
        if ($wallet === null) {
            return ['ok' => false, 'message' => 'No wallet found for this number.'];
        }

        if (! $wallet->hasPin()) {
            return ['ok' => false, 'message' => 'No PIN is set on this wallet. Sign in with OTP to set one.'];
        }

        if ($this->isRateLimited($wallet)) {
            return ['ok' => false, 'message' => 'Too many failed recovery attempts. Try again in about 15 minutes.'];
        }

        $firstP2p = $this->firstP2pCredit($wallet);
        $methods = [];

        if ($firstP2p !== null) {
            $methods[] = 'p2p_quiz';
        }

        if ($wallet->isTier2()) {
            if ($this->pinReset->shouldPromptBvn($wallet) && preg_replace('/\D/', '', (string) $wallet->kyc_bvn) !== '') {
                $methods[] = 'tier2_bvn';
            }
            if ($this->pinReset->bankNameForMatch($wallet) !== '') {
                $methods[] = 'tier2_name';
            }
        }

        if ($methods === []) {
            return [
                'ok' => false,
                'message' => 'PIN recovery is not available yet. Complete Tier 2 KYC on WhatsApp (*UPGRADE*) or contact support.',
            ];
        }

        $e164 = (string) $wallet->phone_e164;

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => [
                'phone_e164' => $e164,
                'tier' => (int) $wallet->tier,
                'methods' => $methods,
                'otp_blocked' => $this->otp->isOtpBlocked($e164),
                'p2p_quiz' => $firstP2p !== null ? $this->p2pQuizHints($firstP2p) : null,
                'tier2_bvn_suffix' => $this->bvnSuffix($wallet),
                'tier2_name_choices' => in_array('tier2_name', $methods, true)
                    ? $this->tier2NameChoices($wallet)
                    : null,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, recovery_token?: string}
     */
    public function verifyP2pQuiz(string $phoneInput, string $senderHint, ?string $amountNaira = null): array
    {
        $wallet = $this->walletForPhone($phoneInput);
        if ($wallet === null || ! $wallet->hasPin()) {
            return ['ok' => false, 'message' => 'Wallet not found or PIN not set.'];
        }

        if ($this->isRateLimited($wallet)) {
            return ['ok' => false, 'message' => 'Too many failed recovery attempts. Try again later.'];
        }

        $firstP2p = $this->firstP2pCredit($wallet);
        if ($firstP2p === null) {
            return ['ok' => false, 'message' => 'P2P quiz recovery is not available for this wallet.'];
        }

        $hint = trim($senderHint);
        if ($hint === '') {
            return ['ok' => false, 'message' => 'Enter the phone number or first name of who first sent you money.'];
        }

        if (! $this->senderHintMatches($firstP2p, $hint)) {
            $this->recordFailure($wallet, 'p2p_hint_failed');

            return ['ok' => false, 'message' => 'That does not match our records for your first transfer.'];
        }

        if ($amountNaira !== null && trim($amountNaira) !== '') {
            if (! $this->amountMatches($firstP2p, $amountNaira)) {
                $this->recordFailure($wallet, 'p2p_amount_failed');

                return ['ok' => false, 'message' => 'The amount does not match your first received transfer.'];
            }
        }

        return $this->issueRecoveryToken($wallet, 'p2p_quiz');
    }

    /**
     * @return array{ok: bool, message: string, recovery_token?: string}
     */
    public function verifyBvn(string $phoneInput, string $bvnInput): array
    {
        $wallet = $this->walletForPhone($phoneInput);
        if ($wallet === null || ! $wallet->hasPin()) {
            return ['ok' => false, 'message' => 'Wallet not found or PIN not set.'];
        }

        if (! $wallet->isTier2()) {
            return ['ok' => false, 'message' => 'BVN recovery requires a Tier 2 wallet.'];
        }

        if ($this->isRateLimited($wallet)) {
            return ['ok' => false, 'message' => 'Too many failed recovery attempts. Try again later.'];
        }

        if (! $this->pinReset->verifyBvn($wallet, $bvnInput)) {
            $this->recordFailure($wallet, 'bvn_failed');

            return ['ok' => false, 'message' => 'BVN does not match our records.'];
        }

        return $this->issueRecoveryToken($wallet, 'tier2_bvn');
    }

    /**
     * Tier 2: typed name must match bank account name and appear among prior P2P sender names.
     *
     * @return array{ok: bool, message: string, recovery_token?: string}
     */
    public function verifyBankName(string $phoneInput, string $nameInput): array
    {
        $wallet = $this->walletForPhone($phoneInput);
        if ($wallet === null || ! $wallet->hasPin()) {
            return ['ok' => false, 'message' => 'Wallet not found or PIN not set.'];
        }

        if (! $wallet->isTier2() || $this->pinReset->bankNameForMatch($wallet) === '') {
            return ['ok' => false, 'message' => 'Bank name recovery requires a verified Tier 2 account.'];
        }

        if ($this->isRateLimited($wallet)) {
            return ['ok' => false, 'message' => 'Too many failed recovery attempts. Try again later.'];
        }

        if (! $this->pinReset->verifyProvidedName($wallet, $nameInput)) {
            $this->recordFailure($wallet, 'bank_name_failed');

            return ['ok' => false, 'message' => 'Name does not match your bank account on file.'];
        }

        return $this->issueRecoveryToken($wallet, 'tier2_name');
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function completeReset(string $recoveryToken, string $pin, string $pinConfirm): array
    {
        if (! preg_match('/^\d{4}$/', $pin) || ! preg_match('/^\d{4}$/', $pinConfirm)) {
            return ['ok' => false, 'message' => 'Enter exactly 4 digits in both PIN fields.'];
        }

        if ($pin !== $pinConfirm) {
            return ['ok' => false, 'message' => 'PINs do not match.'];
        }

        $payload = Cache::get($this->recoveryCacheKey($recoveryToken));
        if (! is_array($payload)) {
            return ['ok' => false, 'message' => 'Recovery session expired. Start again.'];
        }

        $walletId = (int) ($payload['wallet_id'] ?? 0);
        $wallet = WhatsappWallet::query()->find($walletId);
        if (! $wallet || ! $wallet->hasPin()) {
            Cache::forget($this->recoveryCacheKey($recoveryToken));

            return ['ok' => false, 'message' => 'Recovery session is no longer valid.'];
        }

        $wallet->pin_hash = Hash::make($pin);
        $wallet->pin_set_at = now();
        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

        Cache::forget($this->recoveryCacheKey($recoveryToken));
        $this->clearFailures($wallet);
        $this->otp->clearUnusedOtpSends((string) $wallet->phone_e164);

        Log::info('consumer_wallet.pin_recovery.completed', [
            'wallet_id' => $wallet->id,
            'method' => (string) ($payload['method'] ?? ''),
        ]);

        return ['ok' => true, 'message' => 'PIN reset. Sign in with your new PIN.'];
    }

    private function walletForPhone(string $phoneInput): ?WhatsappWallet
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return null;
        }

        return WhatsappWallet::query()->where('phone_e164', $e164)->first();
    }

    private function firstP2pCredit(WhatsappWallet $wallet): ?WhatsappWalletTransaction
    {
        return WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('type', WhatsappWalletTransaction::TYPE_P2P_CREDIT)
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function p2pQuizHints(WhatsappWalletTransaction $txn): array
    {
        $phone = trim((string) $txn->counterparty_phone_e164);
        $masked = $phone !== '' && strlen($phone) >= 4
            ? str_repeat('*', max(0, strlen($phone) - 4)).substr($phone, -4)
            : null;

        return [
            'sender_phone_masked' => $masked,
            'amount_optional' => true,
            'prompt' => 'Who first sent you money on WhatsApp? Enter their phone number or the first name shown on that transfer.',
        ];
    }

    private function bvnSuffix(WhatsappWallet $wallet): ?string
    {
        $bvn = preg_replace('/\D/', '', (string) $wallet->kyc_bvn) ?? '';
        if (strlen($bvn) !== 11) {
            return null;
        }

        return substr($bvn, -4);
    }

    /**
     * @return list<string>
     */
    private function tier2NameChoices(WhatsappWallet $wallet): array
    {
        $correct = $this->pinReset->bankNameForMatch($wallet);
        if ($correct === '') {
            return [];
        }

        $choices = [$correct];
        $credits = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('type', WhatsappWalletTransaction::TYPE_P2P_CREDIT)
            ->orderBy('id')
            ->limit(12)
            ->get();

        foreach ($credits as $txn) {
            foreach ([
                trim((string) $txn->counterparty_account_name),
                trim((string) $txn->sender_name),
            ] as $candidate) {
                if ($candidate === '' || WhatsappWalletNameMatcher::passes($candidate, $correct)) {
                    continue;
                }
                $choices[] = $candidate;
            }
        }

        $choices = array_values(array_unique($choices));
        if (count($choices) < 2) {
            return [];
        }

        shuffle($choices);

        return array_slice($choices, 0, min(6, count($choices)));
    }

    private function senderHintMatches(WhatsappWalletTransaction $txn, string $hint): bool
    {
        $digits = PhoneNormalizer::digitsOnly($hint);
        $counterparty = trim((string) $txn->counterparty_phone_e164);

        if ($digits !== null && strlen($digits) >= 10) {
            $canonical = PhoneNormalizer::canonicalNgE164Digits($hint)
                ?? PhoneNormalizer::canonicalNgE164Digits('+'.$digits)
                ?? $digits;

            if ($counterparty !== '' && ($canonical === $counterparty || str_ends_with($counterparty, substr($canonical, -10)))) {
                return true;
            }
        }

        $names = array_filter([
            trim((string) $txn->counterparty_account_name),
            trim((string) $txn->sender_name),
        ]);

        foreach ($names as $name) {
            if ($name === '') {
                continue;
            }
            if (WhatsappWalletNameMatcher::passes($hint, $name)) {
                return true;
            }
            $hintNorm = WhatsappWalletNameMatcher::normalizePersonName($hint);
            $first = explode(' ', WhatsappWalletNameMatcher::normalizePersonName($name))[0] ?? '';
            if ($first !== '' && $hintNorm !== '' && ($first === $hintNorm || str_starts_with($first, $hintNorm) || str_starts_with($hintNorm, $first))) {
                return true;
            }
        }

        return false;
    }

    private function amountMatches(WhatsappWalletTransaction $txn, string $amountNaira): bool
    {
        $given = preg_replace('/[^\d.]/', '', $amountNaira) ?? '';
        if ($given === '' || ! is_numeric($given)) {
            return false;
        }

        $expected = number_format((float) $txn->amount, 2, '.', '');

        return hash_equals($expected, number_format((float) $given, 2, '.', ''));
    }

    /**
     * @return array{ok: bool, message: string, recovery_token?: string}
     */
    private function issueRecoveryToken(WhatsappWallet $wallet, string $method): array
    {
        $token = bin2hex(random_bytes(32));
        Cache::put($this->recoveryCacheKey($token), [
            'wallet_id' => $wallet->id,
            'phone_e164' => (string) $wallet->phone_e164,
            'method' => $method,
        ], now()->addMinutes($this->recoveryTtlMinutes()));

        $this->clearFailures($wallet);

        return [
            'ok' => true,
            'message' => 'Identity verified. Choose a new PIN.',
            'recovery_token' => $token,
        ];
    }

    private function recoveryCacheKey(string $token): string
    {
        return self::CACHE_RECOVERY.$token;
    }

    private function failCacheKey(int $walletId): string
    {
        return self::CACHE_FAIL.$walletId;
    }

    private function recoveryTtlMinutes(): int
    {
        return max(5, min(30, (int) config('consumer_wallet.pin_recovery_ttl_minutes', 15)));
    }

    public function isRateLimited(WhatsappWallet $wallet): bool
    {
        return (int) Cache::get($this->failCacheKey($wallet->id), 0) >= $this->maxFailures();
    }

    private function recordFailure(WhatsappWallet $wallet, string $reason): void
    {
        $key = $this->failCacheKey($wallet->id);
        $n = (int) Cache::get($key, 0);
        Cache::put($key, $n + 1, now()->addMinutes($this->lockoutMinutes()));

        Log::info('consumer_wallet.pin_recovery.'.$reason, [
            'wallet_id' => $wallet->id,
            'fail_count' => $n + 1,
        ]);
    }

    private function clearFailures(WhatsappWallet $wallet): void
    {
        Cache::forget($this->failCacheKey($wallet->id));
    }

    private function maxFailures(): int
    {
        return max(3, min(10, (int) config('consumer_wallet.pin_recovery_max_failures', 5)));
    }

    private function lockoutMinutes(): int
    {
        return max(5, min(120, (int) config('consumer_wallet.pin_recovery_lockout_minutes', 15)));
    }
}
