<?php

namespace App\Services\Consumer;

use App\Models\ConsumerDeviceLoginApproval;
use App\Models\ConsumerDeviceStepupSession;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\PushNotificationService;
use Illuminate\Support\Str;

class ConsumerDeviceStepupPushService
{
    public function __construct(
        private ConsumerDeviceTrustService $trust,
        private ConsumerWalletPinVerifier $pinVerifier,
        private PushNotificationService $push,
    ) {}

    /**
     * @return array{push_approval_available: bool, push_approval_expires_at: string|null}
     */
    public function metaForSession(ConsumerDeviceStepupSession $session): array
    {
        if (! $this->isEnabled()) {
            return [
                'push_approval_available' => false,
                'push_approval_expires_at' => null,
            ];
        }

        $account = $session->account;
        if ($account === null || ! $this->hasTrustedDevicePushToken($account)) {
            return [
                'push_approval_available' => false,
                'push_approval_expires_at' => null,
            ];
        }

        return [
            'push_approval_available' => true,
            'push_approval_expires_at' => $session->expires_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{ok: bool, message?: string, sent?: bool, approval_id?: string, expires_at?: string, polling_interval_seconds?: int}
     */
    public function requestApproval(string $sessionToken): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'Push approval is disabled.'];
        }

        $session = $this->findActiveSession($sessionToken);
        if ($session === null) {
            return ['ok' => false, 'message' => 'Step-up session expired.'];
        }

        $account = $session->account;
        if ($account === null) {
            return ['ok' => false, 'message' => 'Account not found.'];
        }

        if (! $this->hasTrustedDevicePushToken($account)) {
            return ['ok' => false, 'message' => 'Your trusted device is not available for push approval. Use BVN and OTP instead.'];
        }

        if (! $this->push->isConfigured(PushNotificationService::PROFILE_CHECKOUTNOW)) {
            return ['ok' => false, 'message' => 'Push notifications are not configured on the server.'];
        }

        ConsumerDeviceLoginApproval::query()
            ->where('consumer_device_stepup_session_id', $session->id)
            ->where('status', ConsumerDeviceLoginApproval::STATUS_PENDING)
            ->update([
                'status' => ConsumerDeviceLoginApproval::STATUS_EXPIRED,
                'resolved_at' => now(),
            ]);

        $ttlMinutes = max(1, (int) config('consumer_wallet.device_stepup_push_ttl_minutes', 5));
        $approval = ConsumerDeviceLoginApproval::query()->create([
            'approval_id' => 'appr_'.Str::random(32),
            'consumer_device_stepup_session_id' => $session->id,
            'consumer_wallet_api_account_id' => $account->id,
            'status' => ConsumerDeviceLoginApproval::STATUS_PENDING,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        $wallet = $session->wallet;
        $title = (string) config('consumer_wallet.device_stepup_push_title', 'New sign-in attempt');
        $body = sprintf(
            'Someone is trying to sign in to CheckoutNow on a new device. Open the app to approve or deny.',
        );

        $token = (string) $account->fcm_token;

        $failed = $this->push->sendToTokens(
            [$token],
            $title,
            $body,
            [
                'type' => 'device_login_approval',
                'screen' => 'device_approval',
                'approval_id' => $approval->approval_id,
                'wallet_id' => (string) ($wallet?->id ?? $account->whatsapp_wallet_id),
            ],
            (string) config('consumer_wallet.device_stepup_push_channel', 'wallet_alerts'),
            PushNotificationService::PROFILE_CHECKOUTNOW,
        );
        ConsumerWalletApiAccount::clearFcmTokenIfInvalid($token, $failed);

        return [
            'ok' => true,
            'sent' => true,
            'approval_id' => $approval->approval_id,
            'expires_at' => $approval->expires_at->toIso8601String(),
            'polling_interval_seconds' => max(2, (int) config('consumer_wallet.device_stepup_push_poll_seconds', 3)),
        ];
    }

    /**
     * @return array{status: string, stepup_token?: string, message?: string}
     */
    public function approvalStatus(string $sessionToken): array
    {
        $session = $this->findActiveSession($sessionToken);
        if ($session === null) {
            return [
                'status' => ConsumerDeviceLoginApproval::STATUS_EXPIRED,
                'message' => 'Step-up session expired.',
            ];
        }

        $approval = ConsumerDeviceLoginApproval::query()
            ->where('consumer_device_stepup_session_id', $session->id)
            ->orderByDesc('id')
            ->first();

        if ($approval === null) {
            return [
                'status' => 'not_requested',
                'message' => 'No push approval requested yet.',
            ];
        }

        $approval->markExpiredIfNeeded();
        $approval->refresh();

        if ($approval->status === ConsumerDeviceLoginApproval::STATUS_APPROVED) {
            $token = (string) $session->stepup_token;
            if ($token === '' || ! $session->isStepupTokenValid($token)) {
                return [
                    'status' => ConsumerDeviceLoginApproval::STATUS_EXPIRED,
                    'message' => 'Approval expired. Request a new push or use BVN and OTP.',
                ];
            }

            return [
                'status' => ConsumerDeviceLoginApproval::STATUS_APPROVED,
                'stepup_token' => $token,
            ];
        }

        if ($approval->status === ConsumerDeviceLoginApproval::STATUS_DENIED) {
            return [
                'status' => ConsumerDeviceLoginApproval::STATUS_DENIED,
                'message' => 'Sign-in was denied on your trusted device.',
            ];
        }

        if ($approval->status === ConsumerDeviceLoginApproval::STATUS_EXPIRED) {
            return [
                'status' => ConsumerDeviceLoginApproval::STATUS_EXPIRED,
                'message' => 'Push approval expired. Request again or use BVN and OTP.',
            ];
        }

        return ['status' => ConsumerDeviceLoginApproval::STATUS_PENDING];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function approve(ConsumerWalletApiAccount $account, string $approvalId, string $pin): array
    {
        $approval = $this->findPendingApprovalForAccount($account, $approvalId);
        if ($approval === null) {
            return ['ok' => false, 'message' => 'Approval request not found or expired.'];
        }

        $wallet = $approval->stepupSession?->wallet;
        if (! $wallet instanceof WhatsappWallet) {
            return ['ok' => false, 'message' => 'Wallet not found.'];
        }

        $pinCheck = $this->verifyWalletPin($wallet, $pin);
        if (! $pinCheck['ok']) {
            return $pinCheck;
        }

        $session = $approval->stepupSession;
        if ($session === null || $session->isExpired()) {
            return ['ok' => false, 'message' => 'Step-up session expired.'];
        }

        $token = 'bind_'.Str::random(48);
        $session->otp_verified_at = now();
        $session->stepup_token = $token;
        $session->stepup_token_expires_at = now()->addMinutes(15);
        $session->save();

        $approval->status = ConsumerDeviceLoginApproval::STATUS_APPROVED;
        $approval->resolved_at = now();
        $approval->save();

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    public function deny(ConsumerWalletApiAccount $account, string $approvalId): array
    {
        $approval = $this->findPendingApprovalForAccount($account, $approvalId);
        if ($approval === null) {
            return ['ok' => false, 'message' => 'Approval request not found or expired.'];
        }

        $approval->status = ConsumerDeviceLoginApproval::STATUS_DENIED;
        $approval->resolved_at = now();
        $approval->save();

        return ['ok' => true];
    }

    private function isEnabled(): bool
    {
        return (bool) config('consumer_wallet.device_stepup_push_enabled', true);
    }

    private function hasTrustedDevicePushToken(ConsumerWalletApiAccount $account): bool
    {
        if ($this->trust->activePasskeyDevice($account) === null) {
            return false;
        }

        $token = trim((string) $account->fcm_token);
        $platform = strtolower((string) $account->fcm_platform);

        return $token !== '' && $platform !== '' && $platform !== 'web';
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

    private function findPendingApprovalForAccount(
        ConsumerWalletApiAccount $account,
        string $approvalId,
    ): ?ConsumerDeviceLoginApproval {
        $approval = ConsumerDeviceLoginApproval::query()
            ->where('approval_id', $approvalId)
            ->where('consumer_wallet_api_account_id', $account->id)
            ->with('stepupSession')
            ->first();

        if ($approval === null) {
            return null;
        }

        $approval->markExpiredIfNeeded();
        if (! $approval->isPending()) {
            return null;
        }

        return $approval;
    }

    /**
     * @return array{ok: bool, message?: string}
     */
    private function verifyWalletPin(WhatsappWallet $wallet, string $pin): array
    {
        if ($wallet->isPinLocked()) {
            return ['ok' => false, 'message' => 'Wallet PIN is locked. Try again later.'];
        }

        if (! $wallet->hasPin()) {
            return ['ok' => false, 'message' => 'Wallet PIN is not set.'];
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
}
