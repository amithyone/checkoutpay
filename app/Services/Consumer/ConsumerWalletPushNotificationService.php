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

    public function notifyWalletCredited(WhatsappWallet $wallet, float $amount, float $balanceAfter): void
    {
        if (! $this->enabled() || $amount <= 0) {
            return;
        }

        $token = $this->resolveToken($wallet);
        if ($token === null) {
            return;
        }

        $currency = $this->walletCountry->currencyForPhoneE164((string) $wallet->phone_e164);
        $amountLabel = WhatsappWalletMoneyFormatter::format($amount, $currency);
        $balanceLabel = WhatsappWalletMoneyFormatter::format($balanceAfter, $currency);

        $title = (string) config('consumer_wallet.credit_push_title', 'Money received');
        $body = sprintf(
            '%s added to your wallet. New balance: %s.',
            $amountLabel,
            $balanceLabel,
        );

        $this->send($token, $title, $body, [
            'type' => 'wallet_credit',
            'wallet_id' => (string) $wallet->id,
            'amount' => (string) $amount,
            'balance_after' => (string) $balanceAfter,
            'currency' => $currency,
        ]);
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

        $token = $this->resolveToken($recipientWallet);
        if ($token === null) {
            return;
        }

        $creditCur = strtoupper($creditCurrency);
        $amountLabel = WhatsappWalletMoneyFormatter::format($amount, $creditCur);
        $fromWho = trim($senderDisplayName) !== '' ? trim($senderDisplayName) : 'Someone';

        $title = (string) config('consumer_wallet.p2p_push_title', 'Money received');
        $body = $this->p2pBody($fromWho, $amountLabel, $crossBorderFx, $amount, $creditCur);

        $this->send($token, $title, $body, [
            'type' => 'wallet_p2p_received',
            'wallet_id' => (string) $recipientWallet->id,
            'amount' => (string) $amount,
            'currency' => $creditCur,
            'sender_name' => $fromWho,
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
     * @param  array<string, string>  $data
     */
    private function send(string $token, string $title, string $body, array $data): void
    {
        if (! $this->push->isConfigured()) {
            return;
        }

        try {
            $this->push->sendToTokens(
                [$token],
                $title,
                $body,
                $data,
                (string) config('consumer_wallet.credit_push_channel', 'wallet_alerts'),
            );
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

    private function resolveToken(WhatsappWallet $wallet): ?string
    {
        $account = ConsumerWalletApiAccount::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->where('fcm_platform', '!=', 'web')
            ->first();

        if (! $account) {
            return null;
        }

        return (string) $account->fcm_token;
    }
}
