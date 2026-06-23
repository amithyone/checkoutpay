<?php

namespace App\Services\Consumer;

use App\Models\ConsumerDeviceStepupSession;
use App\Models\ConsumerPasskeyCredential;
use App\Models\ConsumerTrustedDevice;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;

class ConsumerDeviceTrustService
{
    public function isEnabled(): bool
    {
        return (bool) config('consumer_wallet.device_trust_enabled', true);
    }

    public function accountForPhone(string $phoneInput): ?ConsumerWalletApiAccount
    {
        $e164 = PhoneNormalizer::canonicalNgE164Digits($phoneInput);
        if ($e164 === null) {
            return null;
        }

        return ConsumerWalletApiAccount::query()
            ->where('phone_e164', $e164)
            ->first();
    }

    public function requiresStepUp(ConsumerWalletApiAccount $account): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $this->activePasskeyDevice($account) !== null;
    }

    /**
     * @return array{stepup_required: bool, stepup_session: string, other_device_label: string|null, channels: string[], push_approval_available: bool, push_approval_expires_at: string|null}
     */
    public function stepUpPayload(ConsumerDeviceStepupSession $session, WhatsappWallet $wallet): array
    {
        $pushMeta = app(ConsumerDeviceStepupPushService::class)->metaForSession($session);

        return array_merge([
            'stepup_required' => true,
            'stepup_session' => $session->session_token,
            'other_device_label' => $this->otherDeviceLabel($session->account),
            'channels' => $this->stepUpChannels($wallet),
        ], $pushMeta);
    }

    public function otherDeviceLabel(?ConsumerWalletApiAccount $account): ?string
    {
        $device = $account ? $this->activePasskeyDevice($account) : null;

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

    public function activePasskeyDevice(ConsumerWalletApiAccount $account): ?ConsumerTrustedDevice
    {
        $account->loadMissing('trustedDevices.passkey');

        return $account->trustedDevices
            ->first(fn (ConsumerTrustedDevice $device) => $device->passkey !== null);
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     * @return array{ok: bool, message?: string, token?: string, wallet_id?: int, devices_revoked?: int, transfer_lock_until?: string|null}
     */
    public function bindDevice(
        ConsumerDeviceStepupSession $session,
        string $stepupToken,
        bool $revokeOthers,
        array $credentialPayload,
        string $platform,
        ?string $deviceName = null,
    ): array {
        if (! $session->isStepupTokenValid($stepupToken)) {
            return ['ok' => false, 'message' => 'Invalid or expired step-up token.'];
        }

        $account = $session->account;
        if (! $account) {
            return ['ok' => false, 'message' => 'Account not found.'];
        }

        return DB::transaction(function () use ($session, $revokeOthers, $credentialPayload, $platform, $deviceName, $account) {
            $revoked = 0;
            if ($revokeOthers) {
                $revoked = $this->revokeOtherDevices($account, null);
                $this->applyTransferLock($account);
            }

            $verify = $this->webauthn()->registerVerify($account, $credentialPayload, $platform, $deviceName);
            if (! $verify['ok']) {
                return $verify;
            }

            $session->delete();

            $account->tokens()->delete();
            $tokenName = (string) config('consumer_wallet.token_name', 'consumer_mobile');
            $plain = $account->createToken($tokenName)->plainTextToken;

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
        $tokenName = (string) config('consumer_wallet.token_name', 'consumer_mobile');
        $plain = $account->createToken($tokenName)->plainTextToken;

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
        $hours = max(1, (int) config('consumer_wallet.transfer_lock_hours', 24));
        $account->transfer_lock_until = now()->addHours($hours);
        $account->save();
    }

    public function highValueCap(): int
    {
        return max(1, (int) config('consumer_wallet.high_value_single_transfer_cap', 10000));
    }

    public function isHighValueTransferBlocked(ConsumerWalletApiAccount $account, float $amount): bool
    {
        if (! $account->isTransferLocked()) {
            return false;
        }

        return $amount > $this->highValueCap();
    }

    public function transferLockMeta(ConsumerWalletApiAccount $account): array
    {
        $locked = $account->isTransferLocked();

        return [
            'transfer_lock_until' => $account->transfer_lock_until?->toIso8601String(),
            'high_value_single_transfer_cap' => $this->highValueCap(),
            'high_value_transfer_blocked' => $locked,
        ];
    }

    public function transferLockJsonResponse(ConsumerWalletApiAccount $account, float $amount): ?JsonResponse
    {
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
     * @return array<int, array{id: int, label: string|null, platform: string|null, last_active_at: string|null, is_current: bool}>
     */
    public function listDevices(ConsumerWalletApiAccount $account, ?ConsumerTrustedDevice $currentDevice = null): array
    {
        $account->loadMissing('trustedDevices.passkey');

        return $account->trustedDevices
            ->filter(fn (ConsumerTrustedDevice $device) => $device->passkey !== null)
            ->map(function (ConsumerTrustedDevice $device) use ($currentDevice) {
                return [
                    'id' => $device->id,
                    'label' => $device->label,
                    'platform' => $device->platform,
                    'last_active_at' => $device->last_active_at?->toIso8601String(),
                    'is_current' => $currentDevice !== null && $currentDevice->id === $device->id,
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

    public function deviceForCredential(ConsumerWalletApiAccount $account, string $credentialIdEncoded): ?ConsumerTrustedDevice
    {
        $passkey = ConsumerPasskeyCredential::query()
            ->where('credential_id', $credentialIdEncoded)
            ->whereHas('device', fn ($q) => $q->where('consumer_wallet_api_account_id', $account->id))
            ->with('device')
            ->first();

        return $passkey?->device;
    }

    private function webauthn(): ConsumerWebAuthnService
    {
        if (! class_exists(AttestationStatementSupportManager::class)) {
            throw new \RuntimeException(
                'WebAuthn library missing. Run composer install on the server (web-auth/webauthn-lib).'
            );
        }

        return app(ConsumerWebAuthnService::class);
    }
}
