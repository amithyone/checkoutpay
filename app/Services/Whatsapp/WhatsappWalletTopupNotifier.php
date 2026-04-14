<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Outbound WhatsApp alerts for WhatsApp wallet credits (Mevon webhook, P2P).
 */
class WhatsappWalletTopupNotifier
{
    public function __construct(
        private EvolutionWhatsAppClient $client,
    ) {}

    /**
     * Notify the recipient after another wallet sends them money (P2P). Runs after DB commit.
     * Message is forward-friendly and does not include their wallet balance.
     *
     * @param  array{debit_amount: float, debit_currency: string, credit_amount: float, credit_currency: string}|null  $crossBorderFx
     *        When set and debit/recipient currencies differ, shows what the sender paid and an approximate rate.
     */
    public function notifyP2pReceived(
        string $senderChatInstance,
        WhatsappWallet $recipientWallet,
        float $amount,
        string $senderPhoneE164,
        ?string $senderWalletDisplayName = null,
        ?Carbon $transferredAt = null,
        string $creditCurrency = 'NGN',
        ?array $crossBorderFx = null,
    ): void {
        if ($amount <= 0) {
            return;
        }

        $instance = $senderChatInstance !== ''
            ? $senderChatInstance
            : (string) (WhatsappSession::query()
                ->where('phone_e164', $recipientWallet->phone_e164)
                ->value('evolution_instance') ?? '');

        if ($instance === '') {
            $instance = (string) config('whatsapp.evolution.instance', '');
        }

        if ($instance === '') {
            Log::debug('whatsapp.wallet.p2p_notify: no evolution instance', [
                'recipient_wallet_id' => $recipientWallet->id,
            ]);

            return;
        }

        $creditCur = strtoupper($creditCurrency);
        $amountStr = WhatsappWalletMoneyFormatter::format($amount, $creditCur);
        $at = $transferredAt ?? now();
        $when = $at->copy()->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A').
            ' ('.(string) config('app.timezone').')';

        $name = trim((string) $senderWalletDisplayName);
        $fromWho = $name !== '' ? $name : 'Someone';
        $masked = $this->maskPhoneForNotify($senderPhoneE164);

        $isFx = $this->isCrossBorderNotify($crossBorderFx, $creditCur);

        if ($recipientWallet->needsQuickWalletSetup()) {
            $pinLine = $recipientWallet->hasPin()
                ? ''
                : "• *REGISTER* — PIN (secure link)\n";
            $nameLine = $recipientWallet->hasPin()
                ? '• Send *your name* (what people see when you send)'
                : '• Then *your name* (what people see when you send)';
            $head = $isFx
                ? $this->p2pReceivedHeadCrossBorder($fromWho, $crossBorderFx, $amountStr, $amount, $creditCur)
                : $this->p2pReceivedHeadDomestic($fromWho, $amountStr);
            $text = $head.
                "*Time:* {$when}\n".
                "*From number:* {$masked}\n\n".
                "Finish on WhatsApp (takes a minute):\n".
                "• Send *WALLET*\n".
                $pinLine.
                $nameLine."\n\n".
                '*MENU* — other services';
        } else {
            $head = $isFx
                ? $this->p2pReceivedHeadCrossBorder($fromWho, $crossBorderFx, $amountStr, $amount, $creditCur)
                : $this->p2pReceivedHeadDomestic($fromWho, $amountStr);
            $text = $head.
                "*Time:* {$when}\n".
                "*From number:* {$masked}\n\n".
                'Send *WALLET* for balance & options · *MENU* for all services.';
        }

        $this->client->sendText($instance, $recipientWallet->phone_e164, $text);
    }

    /**
     * @param  array{debit_amount: float, debit_currency: string, credit_amount: float, credit_currency: string}|null  $fx
     */
    private function isCrossBorderNotify(?array $fx, string $creditCurrencyUpper): bool
    {
        if ($fx === null) {
            return false;
        }
        $dCur = strtoupper((string) ($fx['debit_currency'] ?? ''));
        $cCur = strtoupper((string) ($fx['credit_currency'] ?? $creditCurrencyUpper));

        return $dCur !== '' && $cCur !== '' && $dCur !== $cCur;
    }

    private function p2pReceivedHeadDomestic(string $fromWho, string $amountStr): string
    {
        return "💸 *{$fromWho}* sent you *{$amountStr}*\n\n".
            "That amount is now in your wallet.\n\n";
    }

    /**
     * @param  array{debit_amount: float, debit_currency: string, credit_amount: float, credit_currency: string}  $fx
     */
    private function p2pReceivedHeadCrossBorder(string $fromWho, array $fx, string $creditFmt, float $creditAmount, string $creditCurrencyUpper): string
    {
        $debit = (float) ($fx['debit_amount'] ?? 0);
        $dCur = strtoupper((string) ($fx['debit_currency'] ?? ''));
        $cCur = strtoupper((string) ($fx['credit_currency'] ?? $creditCurrencyUpper));
        $debitFmt = WhatsappWalletMoneyFormatter::format($debit, $dCur);
        $rate = WhatsappWalletMoneyFormatter::crossRateLine($debit, $dCur, $creditAmount, $cCur);

        return "🌍 *International wallet send*\n\n".
            "*{$fromWho}* paid *{$debitFmt}*\n".
            "*You received:* {$creditFmt}\n".
            ($rate !== '' ? "*Approx. rate:* {$rate}\n" : '').
            "\n";
    }

    private function maskPhoneForNotify(string $e164Digits): string
    {
        $d = preg_replace('/\D/', '', $e164Digits) ?? '';
        if (strlen($d) < 9) {
            return '••••';
        }

        return substr($d, 0, 5).' •••• '.substr($d, -4);
    }

    /**
     * Notify the wallet owner after a successful credit. Runs outside DB transactions.
     */
    public function notifyCredited(WhatsappWallet $wallet, float $credited, float $balanceAfter): void
    {
        if ($credited <= 0) {
            return;
        }

        $instance = WhatsappSession::query()
            ->where('phone_e164', $wallet->phone_e164)
            ->value('evolution_instance');

        if ($instance === null || $instance === '') {
            $instance = (string) config('whatsapp.evolution.instance', '');
        }

        if ($instance === '') {
            Log::debug('whatsapp.wallet.topup_notify: no evolution instance', [
                'wallet_id' => $wallet->id,
            ]);

            return;
        }

        $amountStr = '₦'.number_format($credited, 2);
        $balStr = '₦'.number_format($balanceAfter, 2);

        if ($wallet->needsQuickWalletSetup()) {
            $pinLine = $wallet->hasPin()
                ? ''
                : "• *REGISTER* — PIN (link)\n";
            $text = "✅ *{$amountStr}* received\n".
                "Balance: *{$balStr}*\n\n".
                "Quick setup:\n".
                "• Send *WALLET*\n".
                $pinLine.
                "• Your *display name*\n\n".
                '*MENU* — other services';
        } else {
            $text = "✅ *{$amountStr}* received\n".
                "Balance: *{$balStr}*\n\n".
                'Send *WALLET* for your wallet · *MENU* for all services.';
        }

        $this->client->sendText($instance, $wallet->phone_e164, $text);
    }
}
