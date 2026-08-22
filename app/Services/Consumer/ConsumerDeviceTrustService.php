<?php

namespace App\Services\Consumer;

use App\Models\ConsumerDeviceLoginApproval;
use App\Models\ConsumerDeviceStepupSession;
use App\Models\ConsumerPasskeyCredential;
use App\Models\ConsumerTrustedDevice;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ConsumerDeviceTrustService
{
    public function isEnabled(): bool
    {
        return (bool) config('consumer_wallet.device_trust_enabled', true);
    }

    public function accountForPhone(string $phoneInput): ?ConsumerWalletApiAccount
    {
        $e164 = PhoneNormalizer::canonicalAuthE164Digits($phoneInput);
        if ($e164 === null) {
            return null;
        }

        return ConsumerWalletApiAccount::query()
            ->where('phone_e164', $e164)
            ->first();
    }

    /**
     * Lock login only when the account already has a KYC-trusted device and this request
     * is from a different (or missing) device_id.
     */
    public function requiresStepUp(ConsumerWalletApiAccount $account, ?string $deviceId = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if (! (bool) config('consumer_wallet.device_stepup_required_on_login', true)) {
            return false;
        }

        $trusted = $this->activeTrustedDevice($account);
        if ($trusted === null) {
            return false;
        }

        $incoming = $this->normalizeDeviceId($deviceId);
        if ($incoming === null) {
            return true;
        }

        $trustedId = $this->normalizeDeviceId($trusted->device_id);

        return $trustedId === null || ! hash_equals($trustedId, $incoming);
    }

    /**
     * @return array{stepup_required: bool, stepup_session: string, other_device_label: string|null, channels: string[], push_approval_available: bool, push_approval_expires_at: string|null, pin_reset_required?: bool, next_step?: string}
     */
    public function stepUpPayload(ConsumerDeviceStepupSession $session, WhatsappWallet $wallet): array
    {
        $pushMeta = app(ConsumerDeviceStepupPushService::class)->metaForSession($session);

        return array_merge([
            'stepup_required' => true,
            'stepup_session' => $session->session_token,
            'other_device_label' => $this->otherDeviceLabel($session->account),
            'channels' => $this->stepUpChannels($wallet),
            'pin_reset_required' => true,
            'next_step' => 'verify_kyc',
        ], $pushMeta);
    }

    public function otherDeviceLabel(?ConsumerWalletApiAccount $account): ?string
    {
        $device = $account ? $this->activeTrustedDevice($account) : null;

        return $device?->label;
    }

    /**
     * @return string[]
     */
    public function stepUpChannels(WhatsappWallet $wallet): array
    {
        $channels = ['whatsapp'];
        if ($wallet->isTier2() && $wallet->resolveOtpEmail() !== null) {
            $channels[] = 'email';
        }

        return $channels;
    }

    /**
     * Preferred trusted device: KYC-confirmed device_id row, else legacy passkey device.
     */
    public function activeTrustedDevice(ConsumerWalletApiAccount $account): ?ConsumerTrustedDevice
    {
        $account->loadMissing('trustedDevices.passkey');

        $kycDevice = $account->trustedDevices
            ->filter(fn (ConsumerTrustedDevice $device) => $device->kyc_confirmed_at !== null || filled($device->device_id))
            ->sortByDesc(fn (ConsumerTrustedDevice $device) => $device->last_active_at?->getTimestamp() ?? 0)
            ->first();

        if ($kycDevice !== null) {
            return $kycDevice;
        }

        return $account->trustedDevices
            ->first(fn (ConsumerTrustedDevice $device) => $device->passkey !== null);
    }

    /** @deprecated use activeTrustedDevice */
    public function activePasskeyDevice(ConsumerWalletApiAccount $account): ?ConsumerTrustedDevice
    {
        return $this->activeTrustedDevice($account);
    }

    /**
     * First-time bootstrap: Tier-2 wallet with no trusted device yet → trust this device (no lock).
     */
    public function bootstrapTrustedDeviceIfEligible(
        ConsumerWalletApiAccount $account,
        WhatsappWallet $wallet,
        ?string $deviceId,
        ?string $platform = null,
        ?string $deviceLabel = null,
    ): ?ConsumerTrustedDevice {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($this->activeTrustedDevice($account) !== null) {
            return null;
        }

        $normalized = $this->normalizeDeviceId($deviceId);
        if ($normalized === null || ! $wallet->isTier2()) {
            return null;
        }

        return $this->upsertTrustedDevice(
            $account,
            $normalized,
            $platform,
            $deviceLabel,
            applyTransferLock: false,
            revokeOthers: false,
        );
    }

    /**
     * After KYC+OTP+new PIN: move trust to this device and apply temporary transfer lock.
     *
     * @return array{ok: bool, message?: string, token?: string, wallet_id?: int, devices_revoked?: int, transfer_lock_until?: string|null, high_value_single_transfer_cap?: int}
     */
    public function bindKycTrustedDevice(
        ConsumerDeviceStepupSession $session,
        string $stepupToken,
        string $newPin,
        ?string $deviceId = null,
        ?string $platform = null,
        ?string $deviceLabel = null,
    ): array {
        if (! $session->isStepupTokenValid($stepupToken)) {
            return ['ok' => false, 'message' => 'Invalid or expired step-up token.'];
        }

        if ($session->bvn_verified_at === null || $session->otp_verified_at === null) {
            return ['ok' => false, 'message' => 'Complete BVN/NIN and OTP verification first.'];
        }

        $account = $session->account;
        $wallet = $session->wallet;
        if (! $account || ! $wallet) {
            return ['ok' => false, 'message' => 'Account not found.'];
        }

        $normalized = $this->normalizeDeviceId($deviceId ?? $session->pending_device_id);
        if ($normalized === null) {
            return ['ok' => false, 'message' => 'device_id is required (send X-Device-Id).'];
        }

        if (! preg_match('/^\d{4}$/', $newPin)) {
            return ['ok' => false, 'message' => 'PIN must be 4 digits.'];
        }

        return DB::transaction(function () use ($session, $account, $wallet, $normalized, $platform, $deviceLabel, $newPin) {
            $wallet->pin_hash = Hash::make($newPin);
            $wallet->pin_set_at = now();
            $wallet->pin_failed_attempts = 0;
            $wallet->pin_locked_until = null;
            $wallet->save();

            $existingOther = ConsumerTrustedDevice::query()
                ->where('consumer_wallet_api_account_id', $account->id)
                ->where(function ($q) use ($normalized) {
                    $q->whereNull('device_id')->orWhere('device_id', '!=', $normalized);
                })
                ->count();

            $device = $this->upsertTrustedDevice(
                $account,
                $normalized,
                $platform ?? $session->pending_platform,
                $deviceLabel ?? $session->pending_device_label,
                applyTransferLock: true,
                revokeOthers: true,
            );

            $account->pin_reset_required = false;
            $account->save();

            $session->delete();

            $account->tokens()->delete();
            $plain = app(ConsumerAppSessionService::class)->createAccessToken($account)->plainTextToken;
            $account->refresh();

            return [
                'ok' => true,
                'token' => $plain,
                'wallet_id' => (int) $account->whatsapp_wallet_id,
                'devices_revoked' => $existingOther,
                'transfer_lock_until' => $account->transfer_lock_until?->toIso8601String(),
                'high_value_single_transfer_cap' => $this->highValueCap(),
                'trusted_device_id' => (int) $device->id,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     * @return array{ok: bool, message?: string, unavailable?: bool, token?: string, wallet_id?: int, devices_revoked?: int, transfer_lock_until?: string|null}
     */
    public function bindDevice(
        ConsumerDeviceStepupSession $session,
        string $stepupToken,
        bool $revokeOthers,
        array $credentialPayload,
        string $platform,
        ?string $deviceName = null,
        ?string $deviceId = null,
    ): array {
        if (! $session->isStepupTokenValid($stepupToken)) {
            return ['ok' => false, 'message' => 'Invalid or expired step-up token.'];
        }

        $account = $session->account;
        if (! $account) {
            return ['ok' => false, 'message' => 'Account not found.'];
        }

        return DB::transaction(function () use ($session, $revokeOthers, $credentialPayload, $platform, $deviceName, $account, $deviceId) {
            $revoked = 0;
            if ($revokeOthers) {
                $revoked = $this->revokeOtherDevices($account, null);
                $this->applyTransferLock($account);
            }

            $verify = $this->webauthn()->registerVerify($account, $credentialPayload, $platform, $deviceName);
            if (! $verify['ok']) {
                return $verify;
            }

            $normalized = $this->normalizeDeviceId($deviceId ?? $session->pending_device_id);
            if ($normalized !== null && isset($verify['device_id'])) {
                ConsumerTrustedDevice::query()
                    ->where('id', (int) $verify['device_id'])
                    ->update([
                        'device_id' => $normalized,
                        'kyc_confirmed_at' => now(),
                        'last_active_at' => now(),
                    ]);
            }

            $account->pin_reset_required = false;
            $account->save();

            $session->delete();

            $account->tokens()->delete();
            $plain = app(ConsumerAppSessionService::class)->createAccessToken($account)->plainTextToken;

            $account->refresh();

            return [
                'ok' => true,
                'token' => $plain,
                'wallet_id' => (int) $account->whatsapp_wallet_id,
                'devices_revoked' => $revoked,
                'transfer_lock_until' => $account->transfer_lock_until?->toIso8601String(),
            ];
        });
    }

    /**
     * @return array{ok: bool, message?: string, token?: string, wallet_id?: int, phone_e164?: string, transfer_lock_until?: string|null}
     */
    public function issueLoginToken(ConsumerWalletApiAccount $account, bool $resetTransferLock = false): array
    {
        if ($resetTransferLock) {
            $account->transfer_lock_until = null;
            $account->save();
        }

        $account->tokens()->delete();
        $plain = app(ConsumerAppSessionService::class)->createAccessToken($account)->plainTextToken;

        return [
            'ok' => true,
            'token' => $plain,
            'wallet_id' => (int) $account->whatsapp_wallet_id,
            'phone_e164' => (string) $account->phone_e164,
            'transfer_lock_until' => $account->transfer_lock_until?->toIso8601String(),
        ];
    }

    public function applyTransferLock(ConsumerWalletApiAccount $account): void
    {
        $hours = max(1, (int) config('consumer_wallet.transfer_lock_hours', 48));
        $account->transfer_lock_until = now()->addHours($hours);
        $account->save();
    }

    public function highValueCap(): int
    {
        return max(1, (int) config('consumer_wallet.high_value_single_transfer_cap', 20000));
    }

    public function isHighValueTransferBlocked(ConsumerWalletApiAccount $account, float $amount): bool
    {
        if (! $account->isTransferLocked()) {
            return false;
        }

        return $amount > $this->highValueCap();
    }

    public function pinResetJsonResponse(ConsumerWalletApiAccount $account): ?JsonResponse
    {
        if (! $account->requiresPinReset()) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Set a new wallet PIN after verifying this device before making transfers.',
            'data' => [
                'pin_reset_required' => true,
            ],
        ], 403);
    }

    public function transferLockMeta(ConsumerWalletApiAccount $account): array
    {
        $locked = $account->isTransferLocked();

        return [
            'transfer_lock_until' => $account->transfer_lock_until?->toIso8601String(),
            'high_value_single_transfer_cap' => $this->highValueCap(),
            'high_value_transfer_blocked' => $locked,
            'pin_reset_required' => $account->requiresPinReset(),
        ];
    }

    public function transferLockJsonResponse(ConsumerWalletApiAccount $account, float $amount): ?JsonResponse
    {
        if ($blocked = $this->pinResetJsonResponse($account)) {
            return $blocked;
        }

        if (! $this->isHighValueTransferBlocked($account, $amount)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Transfers above ₦'.number_format($this->highValueCap()).' are temporarily locked after registering a new device.',
            'data' => [
                'transfer_lock_until' => $account->transfer_lock_until?->toIso8601String(),
                'high_value_single_transfer_cap' => $this->highValueCap(),
            ],
        ], 403);
    }

    /**
     * @return array<int, array{id: int, label: string|null, platform: string|null, device_id: string|null, last_active_at: string|null, is_current: bool}>
     */
    public function listDevices(ConsumerWalletApiAccount $account, ?ConsumerTrustedDevice $currentDevice = null, ?string $currentDeviceId = null): array
    {
        $account->loadMissing('trustedDevices.passkey');
        $normalizedCurrent = $this->normalizeDeviceId($currentDeviceId);

        return $account->trustedDevices
            ->filter(fn (ConsumerTrustedDevice $device) => $device->passkey !== null || filled($device->device_id) || $device->kyc_confirmed_at !== null)
            ->map(function (ConsumerTrustedDevice $device) use ($currentDevice, $normalizedCurrent) {
                $isCurrent = ($currentDevice !== null && $currentDevice->id === $device->id)
                    || ($normalizedCurrent !== null && $this->normalizeDeviceId($device->device_id) === $normalizedCurrent);

                return [
                    'id' => $device->id,
                    'label' => $device->label,
                    'platform' => $device->platform,
                    'device_id' => $device->device_id,
                    'last_active_at' => $device->last_active_at?->toIso8601String(),
                    'is_current' => $isCurrent,
                ];
            })
            ->values()
            ->all();
    }

    public function revokeDevice(ConsumerWalletApiAccount $account, int $deviceId): bool
    {
        $device = ConsumerTrustedDevice::query()
            ->where('consumer_wallet_api_account_id', $account->id)
            ->where('id', $deviceId)
            ->first();

        if ($device === null) {
            return false;
        }

        $device->passkey?->delete();
        $device->delete();

        $account->tokens()->delete();
        $account->forceFill([
            'fcm_token' => null,
            'fcm_platform' => null,
            'fcm_token_updated_at' => null,
        ])->save();

        return true;
    }

    public function revokeOtherDevices(ConsumerWalletApiAccount $account, ?int $exceptDeviceId): int
    {
        $query = ConsumerTrustedDevice::query()
            ->where('consumer_wallet_api_account_id', $account->id);

        if ($exceptDeviceId !== null) {
            $query->where('id', '!=', $exceptDeviceId);
        }

        $devices = $query->with('passkey')->get();
        $count = $devices->count();

        foreach ($devices as $device) {
            $device->passkey?->delete();
            $device->delete();
        }

        $account->tokens()->delete();
        $account->forceFill([
            'fcm_token' => null,
            'fcm_platform' => null,
            'fcm_token_updated_at' => null,
        ])->save();

        return $count;
    }

    public function clearTransferLock(ConsumerWalletApiAccount $account): bool
    {
        if ($account->transfer_lock_until === null) {
            return false;
        }

        $account->forceFill(['transfer_lock_until' => null])->save();

        return true;
    }

    /**
     * @return array{sessions: int, approvals: int}
     */
    public function clearStepUpState(ConsumerWalletApiAccount $account): array
    {
        $sessionIds = ConsumerDeviceStepupSession::query()
            ->where('consumer_wallet_api_account_id', $account->id)
            ->pluck('id');

        if ($sessionIds->isNotEmpty()) {
            $approvals = ConsumerDeviceLoginApproval::query()
                ->whereIn('consumer_device_stepup_session_id', $sessionIds)
                ->delete();
        } else {
            $approvals = 0;
        }

        $sessions = ConsumerDeviceStepupSession::query()
            ->where('consumer_wallet_api_account_id', $account->id)
            ->delete();

        return [
            'sessions' => (int) $sessions,
            'approvals' => (int) $approvals,
        ];
    }

    /**
     * @return array{devices_revoked: int, sessions: int, approvals: int, transfer_lock_cleared: bool}
     */
    public function adminResetDeviceRequirement(ConsumerWalletApiAccount $account, bool $clearTransferLock = true): array
    {
        $devices = $this->revokeOtherDevices($account, null);
        $stepup = $this->clearStepUpState($account);
        $lockCleared = $clearTransferLock ? $this->clearTransferLock($account) : false;
        $account->forceFill(['pin_reset_required' => false])->save();

        return [
            'devices_revoked' => $devices,
            'sessions' => $stepup['sessions'],
            'approvals' => $stepup['approvals'],
            'transfer_lock_cleared' => $lockCleared,
        ];
    }

    public function deviceForCredential(ConsumerWalletApiAccount $account, string $credentialIdEncoded): ?ConsumerTrustedDevice
    {
        $passkey = ConsumerPasskeyCredential::query()
            ->where('credential_id', $credentialIdEncoded)
            ->whereHas('device', fn ($q) => $q->where('consumer_wallet_api_account_id', $account->id))
            ->with('device')
            ->first();

        return $passkey?->device;
    }

    public function normalizeDeviceId(?string $raw): ?string
    {
        $id = trim((string) $raw);
        if ($id === '' || strlen($id) > 128) {
            return null;
        }
        if (! preg_match('/^[A-Za-z0-9._:-]+$/', $id)) {
            return null;
        }

        return $id;
    }

    private function upsertTrustedDevice(
        ConsumerWalletApiAccount $account,
        string $deviceId,
        ?string $platform,
        ?string $deviceLabel,
        bool $applyTransferLock,
        bool $revokeOthers,
    ): ConsumerTrustedDevice {
        $device = ConsumerTrustedDevice::query()
            ->where('consumer_wallet_api_account_id', $account->id)
            ->where('device_id', $deviceId)
            ->first();

        if ($device === null) {
            $device = ConsumerTrustedDevice::query()->create([
                'consumer_wallet_api_account_id' => $account->id,
                'device_id' => $deviceId,
                'label' => $deviceLabel,
                'platform' => $platform,
                'last_active_at' => now(),
                'kyc_confirmed_at' => now(),
            ]);
        } else {
            $device->forceFill([
                'label' => $deviceLabel ?: $device->label,
                'platform' => $platform ?: $device->platform,
                'last_active_at' => now(),
                'kyc_confirmed_at' => $device->kyc_confirmed_at ?? now(),
            ])->save();
        }

        if ($revokeOthers) {
            $this->revokeOtherDevices($account, (int) $device->id);
        }

        if ($applyTransferLock) {
            $this->applyTransferLock($account);
        }

        return $device->fresh();
    }

    private function webauthn(): ConsumerWebAuthnService
    {
        return app(ConsumerWebAuthnService::class);
    }
}
