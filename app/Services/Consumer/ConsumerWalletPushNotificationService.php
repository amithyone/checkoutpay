<?php

namespace App\Services\Consumer;

use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\PushNotificationService;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use App\Services\Whatsapp\WhatsappWalletMoneyFormatter;
use Illuminate\Support\Facades\Log;

/**
 * FCM push alerts for CheckoutNow app users (ConsumerWalletApiAccount).
 */
final class ConsumerWalletPushNotificationService
{
    public function __construct(
        private PushNotificationService $push,
        private WhatsappWalletCountryResolver $walletCountry,
    ) {}

    /**
     * Push alert for any wallet credit (top-up, P2P, refund, card withdraw, etc.).
     *
     * @param  array<string, string>  $extra
     */
    public function notifyMoneyReceived(
        WhatsappWallet $wallet,
        float $amount,
        float $balanceAfter,
        ?string $body = null,
        array $extra = [],
    ): void {
        if (! $this->enabled() || $amount <= 0) {
            return;
        }

        $token = $this->resolveToken($wallet);
        if ($token === null) {
            return;
        }

        $currency = strtoupper((string) ($extra['currency'] ?? ''));
        if ($currency === '') {
            $currency = $this->walletCountry->currencyForPhoneE164((string) $wallet->phone_e164);
        }
        unset($extra['currency']);

        $amountLabel = WhatsappWalletMoneyFormatter::format($amount, $currency);
        $balanceLabel = WhatsappWalletMoneyFormatter::format($balanceAfter, $currency);

        $title = (string) config('consumer_wallet.credit_push_title', 'Money received');
        $defaultBody = sprintf(
            '%s added to your wallet. New balance: %s.',
            $amountLabel,
            $balanceLabel,
        );

        $this->send($token, $title, $body ?? $defaultBody, $this->moneyReceivedData($wallet, array_merge([
            'amount' => (string) $amount,
            'balance_after' => (string) $balanceAfter,
            'currency' => $currency,
        ], $extra)));
    }

    public function notifyWalletCredited(WhatsappWallet $wallet, float $amount, float $balanceAfter): void
    {
        $this->notifyMoneyReceived($wallet, $amount, $balanceAfter, null, [
            'credit_source' => 'top_up',
        ]);
    }

    /**
     * @param  array<string, string>  $data
     */
    public function notifyGeneric(WhatsappWallet $wallet, string $title, string $body, array $data = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $token = $this->resolveToken($wallet);
        if ($token === null) {
            return;
        }

        $this->send($token, $title, $body, array_merge([
            'type' => 'generic',
            'screen' => 'saving',
            'wallet_id' => (string) $wallet->id,
        ], $data));
    }

    /**
     * Manual push from admin (not gated by credit_push_enabled).
     *
     * @return array{ok: bool, message: string}
     */
    public function sendAdminMessage(
        WhatsappWallet $wallet,
        string $title,
        string $body,
        ?string $screen = null,
    ): array {
        if (! $this->push->isConfigured(PushNotificationService::PROFILE_CHECKOUTNOW)) {
            return [
                'ok' => false,
                'message' => 'CheckoutNow Firebase is not configured. Set CHECKOUTNOW_FCM_PROJECT_ID and CHECKOUTNOW_FCM_SERVICE_ACCOUNT_JSON on the server.',
            ];
        }

        $token = $this->resolveToken($wallet);
        if ($token === null) {
            return [
                'ok' => false,
                'message' => 'No mobile push token for this wallet. The user must open the CheckoutNow app and allow notifications while signed in.',
            ];
        }

        $data = [
            'type' => 'admin_message',
            'wallet_id' => (string) $wallet->id,
        ];
        if ($screen !== null && $screen !== '') {
            $data['screen'] = $screen;
        }

        try {
            $failed = $this->push->sendToTokens(
                [$token],
                $title,
                $body,
                $data,
                (string) config('consumer_wallet.credit_push_channel', 'money_received'),
                PushNotificationService::PROFILE_CHECKOUTNOW,
            );
            $this->clearTokenIfInvalid($token, $failed);
            if (in_array($token, $failed, true)) {
                return [
                    'ok' => false,
                    'message' => 'FCM rejected the device token (expired or unregistered). Ask the user to open the app again.',
                ];
            }

            return ['ok' => true, 'message' => 'Push notification sent. Check laravel.log for "FCM push accepted" with an fcm_message id.'];
        } catch (\Throwable $e) {
            Log::warning('consumer_wallet.admin_push_failed', [
                'wallet_id' => $wallet->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Could not send push: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{configured: bool, has_token: bool, platform: ?string, updated_at: ?string}
     */
    public function tokenStatus(WhatsappWallet $wallet): array
    {
        $account = $this->resolveAccount($wallet);

        return [
            'configured' => $this->push->isConfigured(PushNotificationService::PROFILE_CHECKOUTNOW),
            'has_token' => $account !== null,
            'platform' => $account?->fcm_platform,
            'updated_at' => $account?->fcm_token_updated_at?->toIso8601String(),
            'fcm_project_id' => (string) config('services.firebase.checkoutnow.project_id', ''),
            'service_account_project_id' => $this->serviceAccountProjectId(),
            'projects_match' => $this->firebaseProjectsMatch(),
        ];
    }

    private function serviceAccountProjectId(): ?string
    {
        $path = (string) config('services.firebase.checkoutnow.service_account_json', '');
        if ($path === '') {
            return null;
        }
        $resolved = is_file($path) ? $path : base_path($path);
        if (! is_file($resolved)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($resolved), true);

        return is_array($json) ? ($json['project_id'] ?? null) : null;
    }

    private function firebaseProjectsMatch(): bool
    {
        $env = (string) config('services.firebase.checkoutnow.project_id', '');
        $sa = (string) ($this->serviceAccountProjectId() ?? '');

        return $env !== '' && $sa !== '' && $env === $sa;
    }

    /**
     * @param  array{debit_amount: float, debit_currency: string, credit_amount: float, credit_currency: string}|null  $crossBorderFx
     */
    public function notifyP2pReceived(
        WhatsappWallet $recipientWallet,
        float $amount,
        string $senderDisplayName,
        string $creditCurrency = 'NGN',
        ?array $crossBorderFx = null,
    ): void {
        if (! $this->enabled() || $amount <= 0) {
            return;
        }

        $creditCur = strtoupper($creditCurrency);
        $amountLabel = WhatsappWalletMoneyFormatter::format($amount, $creditCur);
        $fromWho = trim($senderDisplayName) !== '' ? trim($senderDisplayName) : 'Someone';
        $body = $this->p2pBody($fromWho, $amountLabel, $crossBorderFx, $amount, $creditCur);
        $balanceAfter = (float) $recipientWallet->fresh()->balance;

        $this->notifyMoneyReceived($recipientWallet, $amount, $balanceAfter, $body, [
            'credit_source' => 'p2p_credit',
            'sender_name' => $fromWho,
            'currency' => $creditCur,
        ]);
    }

    /**
     * @param  array{debit_amount: float, debit_currency: string, credit_amount: float, credit_currency: string}|null  $crossBorderFx
     */
    private function p2pBody(string $fromWho, string $amountLabel, ?array $crossBorderFx, float $creditAmount, string $creditCur): string
    {
        if ($this->isCrossBorder($crossBorderFx, $creditCur)) {
            $debit = (float) ($crossBorderFx['debit_amount'] ?? 0);
            $dCur = strtoupper((string) ($crossBorderFx['debit_currency'] ?? ''));
            $debitLabel = WhatsappWalletMoneyFormatter::format($debit, $dCur);

            return sprintf('%s sent you %s (they paid %s).', $fromWho, $amountLabel, $debitLabel);
        }

        return sprintf('%s sent you %s.', $fromWho, $amountLabel);
    }

    /**
     * @param  array{debit_amount: float, debit_currency: string, credit_amount: float, credit_currency: string}|null  $fx
     */
    private function isCrossBorder(?array $fx, string $creditCurrencyUpper): bool
    {
        if ($fx === null) {
            return false;
        }
        $dCur = strtoupper((string) ($fx['debit_currency'] ?? ''));
        $cCur = strtoupper((string) ($fx['credit_currency'] ?? $creditCurrencyUpper));

        return $dCur !== '' && $cCur !== '' && $dCur !== $cCur;
    }

    /**
     * FCM `data` payload for money-received alerts (all values must be strings).
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function moneyReceivedData(WhatsappWallet $wallet, array $extra = []): array
    {
        return array_merge([
            'type' => 'money_received',
            'screen' => 'history',
            'wallet_id' => (string) $wallet->id,
        ], $extra);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function send(string $token, string $title, string $body, array $data): void
    {
        if (! $this->push->isConfigured(PushNotificationService::PROFILE_CHECKOUTNOW)) {
            return;
        }

        try {
            $failed = $this->push->sendToTokens(
                [$token],
                $title,
                $body,
                $data,
                (string) config('consumer_wallet.credit_push_channel', 'money_received'),
                PushNotificationService::PROFILE_CHECKOUTNOW,
            );
            $this->clearTokenIfInvalid($token, $failed);
        } catch (\Throwable $e) {
            Log::warning('consumer_wallet.push_failed', [
                'type' => $data['type'] ?? 'unknown',
                'wallet_id' => $data['wallet_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function enabled(): bool
    {
        return (bool) config('consumer_wallet.credit_push_enabled', true);
    }

    private function clearTokenIfInvalid(string $token, array $failedTokens): void
    {
        if (! in_array($token, $failedTokens, true)) {
            return;
        }

        ConsumerWalletApiAccount::query()
            ->where('fcm_token', $token)
            ->update([
                'fcm_token' => null,
                'fcm_platform' => null,
                'fcm_token_updated_at' => null,
            ]);
    }

    private function resolveToken(WhatsappWallet $wallet): ?string
    {
        $account = $this->resolveAccount($wallet);

        if (! $account) {
            return null;
        }

        return (string) $account->fcm_token;
    }

    private function resolveAccount(WhatsappWallet $wallet): ?ConsumerWalletApiAccount
    {
        return ConsumerWalletApiAccount::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('fcm_platform', '!=', 'web')
            ->orderByDesc('fcm_token_updated_at')
            ->first();
    }
}
