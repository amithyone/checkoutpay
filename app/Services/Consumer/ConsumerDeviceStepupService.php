<?php

namespace App\Services\Consumer;

use App\Models\ConsumerDeviceStepupSession;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappWalletPinResetService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ConsumerDeviceStepupService
{
    /** @var array{email_sent: bool|null, email_message: string|null}|null */
    private ?array $lastCreateEmailOtp = null;

    public function __construct(
        private ConsumerWalletOtpService $otp,
        private ConsumerWalletPinVerifier $pinVerifier,
        private WhatsappWalletPinResetService $pinReset,
        private ConsumerDeviceTrustService $trust,
        private ConsumerDeviceStepupPushService $stepupPush,
    ) {}

    /**
     * Email OTP outcome from the most recent createSession() call (first-device flow).
     *
     * @return array{email_sent: bool|null, email_message: string|null}|null
     */
    public function lastCreateEmailOtp(): ?array
    {
        return $this->lastCreateEmailOtp;
    }

    /**
     * @return array{ok: bool, message?: string, stepup_required?: bool, stepup_session?: string, stepup_mode?: string, other_device_label?: string|null, channels?: string[], pin_reset_required?: bool, next_step?: string, email_masked?: string|null}
     */
    public function start(
        string $phoneInput,
        ?string $pin = null,
        ?string $otpCode = null,
        ?string $deviceId = null,
        ?string $platform = null,
        ?string $deviceLabel = null,
    ): array {
        if (! $this->trust->isEnabled()) {
            return ['ok' => false, 'message' => 'Device trust is disabled.'];
        }

        $e164 = PhoneNormalizer::canonicalAuthE164Digits($phoneInput);
        if ($e164 === null) {
            return ['ok' => false, 'message' => 'Invalid mobile number for a supported country.'];
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

        if (! $this->trust->requiresStepUp($account, $deviceId)) {
            return [
                'ok' => true,
                'stepup_required' => false,
                'message' => 'No step-up required.',
            ];
        }

        $session = $this->createSession($account, $wallet, $deviceId, $platform, $deviceLabel);

        return array_merge(
            ['ok' => true],
            $this->trust->stepUpPayload($session, $wallet, $this->lastCreateEmailOtp),
        );
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

        if ($this->trust->isEmailOnlyStepUpSession($session)) {
            return ['ok' => false, 'message' => 'BVN is not required. Enter the email OTP we sent.'];
        }

        $wallet = $session->wallet;
        if (! $wallet || ! $this->pinReset->verifyBvn($wallet, $bvn)) {
            return ['ok' => false, 'message' => 'BVN/NIN does not match our records.'];
        }

        $session->bvn_verified_at = now();
        $session->save();

        return ['ok' => true, 'bvn_verified' => true];
    }

    /**
     * @return array{ok: bool, message?: string, sent?: bool, channel?: string, email_masked?: string|null}
     */
    public function requestOtp(string $sessionToken, string $channel): array
    {
        $session = $this->findActiveSession($sessionToken);
        if ($session === null) {
            return ['ok' => false, 'message' => 'Step-up session expired.'];
        }

        $firstDevice = $this->trust->isEmailOnlyStepUpSession($session);
        if ($firstDevice) {
            $channel = 'email';
        } elseif ($session->bvn_verified_at === null) {
            return ['ok' => false, 'message' => 'Verify BVN/NIN first.'];
        }

        $result = $this->otp->requestOtp(
            (string) $session->phone_e164,
            $channel,
            null,
            null,
            forDeviceTrust: $firstDevice,
        );
        if (! $result['ok']) {
            return ['ok' => false, 'message' => $result['message']];
        }

        return [
            'ok' => true,
            'sent' => true,
            'channel' => $result['channel'] ?? $channel,
            'email_masked' => $result['email_masked'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, message?: string, stepup_token?: string, pin_reset_required?: bool, next_step?: string, token?: string, token_type?: string, phone_e164?: string, wallet_id?: int, trusted_device_id?: int, device_id?: string|null, stepup_mode?: string}
     */
    public function verifyOtp(
        string $sessionToken,
        string $code,
        ?string $deviceId = null,
        ?string $platform = null,
        ?string $deviceLabel = null,
    ): array {
        $session = $this->findActiveSession($sessionToken);
        if ($session === null) {
            return ['ok' => false, 'message' => 'Step-up session expired.'];
        }

        $firstDevice = $this->trust->isEmailOnlyStepUpSession($session);
        if (! $firstDevice && $session->bvn_verified_at === null) {
            return ['ok' => false, 'message' => 'Verify BVN/NIN first.'];
        }

        $verified = $this->otp->verifyOtp((string) $session->phone_e164, $code);
        if (! $verified['ok']) {
            return ['ok' => false, 'message' => $verified['message']];
        }

        $incomingDeviceId = $this->trust->normalizeDeviceId($deviceId);
        if ($incomingDeviceId !== null) {
            $session->pending_device_id = $incomingDeviceId;
        }
        if ($platform !== null && $platform !== '') {
            $session->pending_platform = $platform;
        }
        if ($deviceLabel !== null && $deviceLabel !== '') {
            $session->pending_device_label = $deviceLabel;
        }

        $session->otp_verified_at = now();
        $session->save();

        if ($firstDevice) {
            $bound = $this->trust->bindFirstDeviceAfterEmailOtp(
                $session,
                $session->pending_device_id,
                $session->pending_platform,
                $session->pending_device_label,
            );
            if (! ($bound['ok'] ?? false)) {
                return ['ok' => false, 'message' => $bound['message'] ?? 'Could not trust this device.'];
            }

            return [
                'ok' => true,
                'stepup_mode' => 'first_device_email',
                'token' => $bound['token'],
                'token_type' => 'Bearer',
                'phone_e164' => $bound['phone_e164'] ?? null,
                'wallet_id' => $bound['wallet_id'] ?? null,
                'trusted_device_id' => $bound['trusted_device_id'] ?? null,
                'device_id' => $bound['device_id'] ?? $session->pending_device_id,
                'pin_reset_required' => false,
                'next_step' => 'done',
            ];
        }

        $token = 'bind_'.Str::random(48);
        $session->stepup_token = $token;
        $session->stepup_token_expires_at = now()->addMinutes(15);
        $session->save();

        return [
            'ok' => true,
            'stepup_mode' => 'device_mismatch',
            'stepup_token' => $token,
            'pin_reset_required' => true,
            'next_step' => 'set_new_pin_and_bind',
        ];
    }

    /**
     * Still-photo step-up is disabled — guided liveness is required.
     *
     * @return array{ok: bool, message?: string, http?: int, error_code?: string, stepup_session?: string, face_challenge?: string, next_step?: string}
     */
    public function verifyFace(
        string $sessionToken,
        ?UploadedFile $photo = null,
        ?string $deviceId = null,
        ?string $platform = null,
        ?string $deviceLabel = null,
    ): array {
        $session = $this->resolveFaceStepupSession($sessionToken);
        if (! ($session['ok'] ?? false)) {
            return $session;
        }

        /** @var ConsumerDeviceStepupSession $row */
        $row = $session['session'];

        return [
            'ok' => false,
            'message' => 'Liveness video required',
            'http' => 422,
            'error_code' => 'face_liveness_required',
            'stepup_session' => $row->session_token,
            'face_challenge' => 'liveness',
            'next_step' => 'liveness_session',
        ];
    }

    /**
     * Start CheckFace guided liveness for device_mismatch step-up (no Sanctum).
     *
     * @return array{ok: bool, message?: string, http?: int, error_code?: string, stepup_session?: string, session_id?: string, challenges?: mixed, expires_in?: mixed, capture?: string, seconds_per_challenge?: mixed}
     */
    public function startFaceLiveness(string $sessionToken): array
    {
        $resolved = $this->resolveFaceStepupSession($sessionToken);
        if (! ($resolved['ok'] ?? false)) {
            return $resolved;
        }

        /** @var ConsumerDeviceStepupSession $session */
        $session = $resolved['session'];
        $wallet = $session->wallet;
        if (! $wallet) {
            return ['ok' => false, 'message' => 'Wallet not found.', 'http' => 422];
        }

        $face = app(WalletFaceCheckService::class);
        if (! $face->isAvailableForStepUp($wallet)) {
            return [
                'ok' => false,
                'message' => 'Face is not enrolled for this wallet. Use BVN instead.',
                'http' => 422,
                'error_code' => 'face_not_available',
                'stepup_session' => $session->session_token,
            ];
        }

        $started = $face->startLiveness($wallet);
        if (! ($started['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $started['message'] ?? 'Could not start liveness.',
                'http' => (int) ($started['http'] ?? 422),
                'error_code' => $started['data']['error_code'] ?? 'liveness_start_failed',
                'stepup_session' => $session->session_token,
            ];
        }

        $livenessId = (string) ($started['data']['session_id'] ?? '');
        $expiresIn = isset($started['data']['expires_in']) ? (int) $started['data']['expires_in'] : 180;
        $secondsPer = isset($started['data']['seconds_per_challenge'])
            ? (float) $started['data']['seconds_per_challenge']
            : 2.4;
        if ($livenessId !== '') {
            \Illuminate\Support\Facades\Cache::put(
                $this->faceLivenessCacheKey($session->session_token),
                $livenessId,
                now()->addSeconds(max(60, $expiresIn)),
            );
        }

        $challenges = $this->formatFaceLivenessChallenges(
            $started['data']['challenges'] ?? [],
            $secondsPer,
        );

        return [
            'ok' => true,
            'stepup_session' => $session->session_token,
            'session_id' => $livenessId,
            'challenges' => $challenges,
            'expires_in' => $expiresIn,
            'expires_at' => now()->addSeconds(max(1, $expiresIn))->toIso8601String(),
            'instructions' => 'Follow the on-screen prompts',
            'capture' => $started['data']['capture'] ?? 'video',
            'seconds_per_challenge' => $secondsPer,
            'face_challenge' => 'liveness',
        ];
    }

    /**
     * Map CheckFace challenge ids into app UX objects (prompts are on-device; clip is one continuous video).
     *
     * @param  list<string|array<string, mixed>>  $raw
     * @return list<array{id: string, prompt: string, duration_ms: int}>
     */
    private function formatFaceLivenessChallenges(array $raw, float $secondsPerChallenge): array
    {
        $defaultMs = (int) round(max($secondsPerChallenge, 2.4) * 1000);
        $centerMs = max($defaultMs, 2800);

        $prompts = [
            'center' => 'Position your face in the circle',
            'left' => 'Look left',
            'right' => 'Look right',
            'turn_left' => 'Look left',
            'turn_right' => 'Look right',
            'smile' => 'Smile',
            'blink' => 'Blink',
            'mouth' => 'Open your mouth',
        ];

        $idAlias = [
            'turn_left' => 'left',
            'turn_right' => 'right',
        ];

        $out = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $rawId = (string) ($item['id'] ?? $item['name'] ?? '');
                $prompt = (string) ($item['prompt'] ?? '');
                $duration = isset($item['duration_ms']) ? (int) $item['duration_ms'] : null;
            } else {
                $rawId = (string) $item;
                $prompt = '';
                $duration = null;
            }
            if ($rawId === '') {
                continue;
            }
            $id = $idAlias[$rawId] ?? $rawId;
            if ($prompt === '') {
                $prompt = $prompts[$rawId] ?? $prompts[$id] ?? ucfirst(str_replace('_', ' ', $id));
            }
            $out[] = [
                'id' => $id,
                'prompt' => $prompt,
                'duration_ms' => $duration ?? ($id === 'center' ? $centerMs : $defaultMs),
            ];
        }

        return $out;
    }

    /**
     * Complete guided liveness and mint stepup_token for bind (substitutes BVN+OTP).
     *
     * @return array{ok: bool, message?: string, http?: int, error_code?: string, stepup_session?: string, stepup_token?: string, pin_reset_required?: bool, next_step?: string, stepup_mode?: string, score?: float|null, liveness_passed?: bool, liveness_score?: mixed}
     */
    public function completeFaceLiveness(
        string $sessionToken,
        string $livenessSessionId,
        \Illuminate\Http\UploadedFile $clip,
        ?string $deviceId = null,
        ?string $platform = null,
        ?string $deviceLabel = null,
    ): array {
        $resolved = $this->resolveFaceStepupSession($sessionToken);
        if (! ($resolved['ok'] ?? false)) {
            return $resolved;
        }

        /** @var ConsumerDeviceStepupSession $session */
        $session = $resolved['session'];
        $wallet = $session->wallet;
        if (! $wallet) {
            return ['ok' => false, 'message' => 'Wallet not found.', 'http' => 422];
        }

        $expected = \Illuminate\Support\Facades\Cache::get($this->faceLivenessCacheKey($session->session_token));
        if (! is_string($expected) || $expected === '' || ! hash_equals($expected, $livenessSessionId)) {
            return [
                'ok' => false,
                'message' => 'Start a new face liveness session for this step-up first.',
                'http' => 422,
                'error_code' => 'liveness_session_mismatch',
                'stepup_session' => $session->session_token,
            ];
        }

        $face = app(WalletFaceCheckService::class);
        $matched = $face->completeLivenessForStepUp($wallet, $livenessSessionId, $clip);
        if (! ($matched['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => $matched['message'] ?? 'Liveness check failed. Try again or use BVN.',
                'http' => (int) ($matched['http'] ?? 422),
                'error_code' => $matched['data']['error_code'] ?? 'liveness_failed',
                'stepup_session' => $session->session_token,
            ];
        }

        \Illuminate\Support\Facades\Cache::forget($this->faceLivenessCacheKey($session->session_token));

        $incomingDeviceId = $this->trust->normalizeDeviceId($deviceId);
        if ($incomingDeviceId !== null) {
            $session->pending_device_id = $incomingDeviceId;
        }
        if ($platform !== null && $platform !== '') {
            $session->pending_platform = $platform;
        }
        if ($deviceLabel !== null && $deviceLabel !== '') {
            $session->pending_device_label = $deviceLabel;
        }

        // Live face proof substitutes for BVN + OTP on this step-up session.
        $session->bvn_verified_at = $session->bvn_verified_at ?? now();
        $session->otp_verified_at = now();
        $token = 'bind_'.Str::random(48);
        $session->stepup_token = $token;
        $session->stepup_token_expires_at = now()->addMinutes(15);
        $session->save();

        return [
            'ok' => true,
            'stepup_mode' => 'device_mismatch',
            'stepup_token' => $token,
            'pin_reset_required' => true,
            'next_step' => 'bind',
            'score' => $matched['data']['score'] ?? null,
            'liveness_score' => $matched['data']['liveness_score'] ?? null,
            'liveness_passed' => true,
            'matched_via' => $matched['data']['matched_via'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, message?: string, http?: int, error_code?: string, stepup_session?: string, session?: ConsumerDeviceStepupSession}
     */
    private function resolveFaceStepupSession(string $sessionToken): array
    {
        $session = ConsumerDeviceStepupSession::query()
            ->where('session_token', $sessionToken)
            ->first();

        if ($session === null) {
            return [
                'ok' => false,
                'message' => 'Step-up session not found.',
                'http' => 410,
                'error_code' => 'stepup_session_invalid',
            ];
        }

        if ($session->isExpired()) {
            return [
                'ok' => false,
                'message' => 'Step-up session expired.',
                'http' => 410,
                'error_code' => 'stepup_session_expired',
                'stepup_session' => $session->session_token,
            ];
        }

        if ($session->stepup_mode === 'first_device_email' || $this->trust->isEmailOnlyStepUpSession($session)) {
            return [
                'ok' => false,
                'message' => 'CheckFace is not used for first-device email trust. Enter the email code.',
                'http' => 422,
                'error_code' => 'face_not_available',
                'stepup_session' => $session->session_token,
            ];
        }

        return ['ok' => true, 'session' => $session];
    }

    private function faceLivenessCacheKey(string $sessionToken): string
    {
        return 'consumer_stepup_face_liveness:'.$sessionToken;
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

    public function createSession(
        ConsumerWalletApiAccount $account,
        WhatsappWallet $wallet,
        ?string $pendingDeviceId = null,
        ?string $pendingPlatform = null,
        ?string $pendingDeviceLabel = null,
    ): ConsumerDeviceStepupSession {
        $this->lastCreateEmailOtp = null;

        ConsumerDeviceStepupSession::query()
            ->where('consumer_wallet_api_account_id', $account->id)
            ->where('expires_at', '>', now())
            ->delete();

        // Decide mode from the client-supplied device id BEFORE minting a server id.
        $clientDeviceId = $this->trust->normalizeDeviceId($pendingDeviceId);
        $mode = $this->trust->stepUpMode($account, $clientDeviceId) ?? 'device_mismatch';
        $emailOnly = $mode === 'first_device_email';
        if (! $emailOnly) {
            $account->forceFill(['pin_reset_required' => true])->save();
        }

        // Native apps often omit X-Device-Id; mint a stable id so email trust can complete.
        // Clients should persist data.device_id from the step-up / verify response and send it back.
        $normalizedPending = $clientDeviceId ?? ('dev_'.Str::lower(Str::random(40)));

        $session = ConsumerDeviceStepupSession::query()->create([
            'session_token' => 'sess_'.Str::random(40),
            'consumer_wallet_api_account_id' => $account->id,
            'phone_e164' => (string) $account->phone_e164,
            'whatsapp_wallet_id' => $wallet->id,
            'pending_device_id' => $normalizedPending,
            'pending_platform' => $pendingPlatform,
            'pending_device_label' => $pendingDeviceLabel,
            'stepup_mode' => $mode,
            'auth_verified_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        if ($emailOnly) {
            $otpResult = $this->otp->requestOtp(
                (string) $account->phone_e164,
                'email',
                null,
                null,
                forDeviceTrust: true,
            );
            $this->lastCreateEmailOtp = [
                'email_sent' => (bool) ($otpResult['ok'] ?? false),
                'email_message' => isset($otpResult['message']) ? (string) $otpResult['message'] : null,
            ];
        }

        return $session;
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
