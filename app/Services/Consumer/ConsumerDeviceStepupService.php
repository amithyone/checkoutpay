<?php

namespace App\Services\Consumer;

use App\Models\ConsumerDeviceStepupSession;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappWalletPinResetService;
use Illuminate\Support\Str;

class ConsumerDeviceStepupService
{
    public function __construct(
        private ConsumerWalletOtpService $otp,
        private ConsumerWalletPinVerifier $pinVerifier,
        private WhatsappWalletPinResetService $pinReset,
        private ConsumerDeviceTrustService $trust,
        private ConsumerDeviceStepupPushService $stepupPush,
    ) {}

    /**
     * @return array{ok: bool, message?: string, stepup_required?: bool, stepup_session?: string, other_device_label?: string|null, channels?: string[]}
     */
    public function start(string $phoneInput, ?string $pin = null, ?string $otpCode = null): array
    {
        if (! $this->trust->isEnabled()) {
            return ['ok' => false, 'message' => 'Device trust is disabled.'];
        }

        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid Nigerian mobile number.'];
        }

        $wallet = WhatsappWallet::query()->where('phone_e164', $e164)->first();
        if (! $wallet || $wallet->needsRegistrationProfile()) {
            return ['ok' => false, 'message' => 'Complete registration to create your wallet.'];
        }

        if ($pin === null && $otpCode === null) {
            return ['ok' => false, 'message' => 'Provide PIN or OTP code.'];
        }

        if ($pin !== null) {
            $auth = $this->verifyPin($wallet, $pin);
        } else {
            $auth = $this->verifyOtpCode($phoneInput, (string) $otpCode);
        }

        if (! $auth['ok']) {
            return $auth;
        }

        $account = ConsumerWalletApiAccount::query()->firstOrNew(['phone_e164' => $e164]);
        $account->whatsapp_wallet_id = $wallet->id;
        $account->phone_e164 = $e164;
        $account->save();

        if (! $this->trust->requiresStepUp($account)) {
            return [
                'ok' => true,
                'stepup_required' => false,
                'message' => 'No step-up required.',
            ];
        }

        $session = $this->createSession($account, $wallet);

        return array_merge([
            'ok' => true,
            'stepup_required' => true,
            'stepup_session' => $session->session_token,
            'other_device_label' => $this->trust->otherDeviceLabel($account),
            'channels' => $this->trust->stepUpChannels($wallet),
        ], $this->stepupPush->metaForSession($session));
    }

    /**
     * @return array{ok: bool, message?: string, bvn_verified?: bool}
     */
    public function verifyBvn(string $sessionToken, string $bvn): array
    {
        $session = $this->findActiveSession($sessionToken);
        if ($session === null) {
            return ['ok' => false, 'message' => 'Step-up session expired.'];
        }

        $wallet = $session->wallet;
        if (! $wallet || ! $this->pinReset->verifyBvn($wallet, $bvn)) {
            return ['ok' => false, 'message' => 'BVN does not match our records.'];
        }

        $session->bvn_verified_at = now();
        $session->save();

        return ['ok' => true, 'bvn_verified' => true];
    }

    /**
     * @return array{ok: bool, message?: string, sent?: bool}
     */
    public function requestOtp(string $sessionToken, string $channel): array
    {
        $session = $this->findActiveSession($sessionToken);
        if ($session === null) {
            return ['ok' => false, 'message' => 'Step-up session expired.'];
        }

        if ($session->bvn_verified_at === null) {
            return ['ok' => false, 'message' => 'Verify BVN first.'];
        }

        $result = $this->otp->requestOtp((string) $session->phone_e164, $channel);
        if (! $result['ok']) {
            return ['ok' => false, 'message' => $result['message']];
        }

        return ['ok' => true, 'sent' => true];
    }

    /**
     * @return array{ok: bool, message?: string, stepup_token?: string}
     */
    public function verifyOtp(string $sessionToken, string $code): array
    {
        $session = $this->findActiveSession($sessionToken);
        if ($session === null) {
            return ['ok' => false, 'message' => 'Step-up session expired.'];
        }

        if ($session->bvn_verified_at === null) {
            return ['ok' => false, 'message' => 'Verify BVN first.'];
        }

        $verified = $this->otp->verifyOtp((string) $session->phone_e164, $code);
        if (! $verified['ok']) {
            return ['ok' => false, 'message' => $verified['message']];
        }

        $token = 'bind_'.Str::random(48);
        $session->otp_verified_at = now();
        $session->stepup_token = $token;
        $session->stepup_token_expires_at = now()->addMinutes(15);
        $session->save();

        return ['ok' => true, 'stepup_token' => $token];
    }

    public function findSessionByStepupToken(string $token): ?ConsumerDeviceStepupSession
    {
        $session = ConsumerDeviceStepupSession::query()
            ->where('stepup_token', $token)
            ->first();

        if ($session === null || ! $session->isStepupTokenValid($token)) {
            return null;
        }

        return $session;
    }

    public function createSession(ConsumerWalletApiAccount $account, WhatsappWallet $wallet): ConsumerDeviceStepupSession
    {
        ConsumerDeviceStepupSession::query()
            ->where('consumer_wallet_api_account_id', $account->id)
            ->where('expires_at', '>', now())
            ->delete();

        return ConsumerDeviceStepupSession::query()->create([
            'session_token' => 'sess_'.Str::random(40),
            'consumer_wallet_api_account_id' => $account->id,
            'phone_e164' => (string) $account->phone_e164,
            'whatsapp_wallet_id' => $wallet->id,
            'auth_verified_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    private function findActiveSession(string $sessionToken): ?ConsumerDeviceStepupSession
    {
        $session = ConsumerDeviceStepupSession::query()
            ->where('session_token', $sessionToken)
            ->first();

        if ($session === null || $session->isExpired()) {
            return null;
        }

        return $session;
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function verifyPin(WhatsappWallet $wallet, string $pin): array
    {
        if ($wallet->isPinLocked()) {
            return ['ok' => false, 'message' => 'Wallet PIN is locked. Try again later or use WhatsApp OTP.'];
        }

        if (! $wallet->hasPin()) {
            return ['ok' => false, 'message' => 'PIN is not set yet. Sign in with OTP first.'];
        }

        if (! $this->pinVerifier->verify($wallet, $pin)) {
            $wallet->increment('pin_failed_attempts');
            $wallet->refresh();
            if ((int) $wallet->pin_failed_attempts >= 5) {
                $wallet->pin_locked_until = now()->addMinutes(15);
                $wallet->save();

                return ['ok' => false, 'message' => 'Too many wrong PIN attempts. Wallet PIN locked for 15 minutes.'];
            }

            return ['ok' => false, 'message' => 'Incorrect wallet PIN.'];
        }

        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function verifyOtpCode(string $phoneInput, string $code): array
    {
        $checked = $this->otp->checkOtp($phoneInput, $code);
        if (! $checked['ok']) {
            return ['ok' => false, 'message' => $checked['message']];
        }

        $verified = $this->otp->verifyOtp($phoneInput, $code);
        if (! $verified['ok']) {
            return ['ok' => false, 'message' => $verified['message']];
        }

        return ['ok' => true];
    }
}
