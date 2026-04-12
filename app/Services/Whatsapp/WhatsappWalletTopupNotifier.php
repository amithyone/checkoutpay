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
     */
    public function notifyP2pReceived(
        string $senderChatInstance,
        WhatsappWallet $recipientWallet,
        float $amount,
        string $senderPhoneE164,
        ?string $senderWalletDisplayName = null,
        ?Carbon $transferredAt = null,
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

        $amountStr = '₦'.number_format($amount, 2);
        $at = $transferredAt ?? now();
        $when = $at->copy()->timezone(config('app.timezone'))->format('M j, Y \a\t g:i A').
            ' ('.(string) config('app.timezone').')';

        $name = trim((string) $senderWalletDisplayName);
        $fromWho = $name !== '' ? $name : 'Wallet sender';
        $masked = $this->maskPhoneForNotify($senderPhoneE164);

        $text = "💸 *You received money* ✨\n\n".
            "*From:* {$fromWho}\n".
            "*Number:* {$masked}\n".
            "*Amount:* {$amountStr}\n".
            "*Time:* {$when}\n\n".
            "💡 Open *WALLET* here to see your balance.\n\n".
            'Send *WALLET* for the hub or *MENU* for all options.';

        $this->client->sendText($instance, $recipientWallet->phone_e164, $text);
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

        $text = "✅ *Wallet funded*\n\n".
            "We received *{$amountStr}*.\n".
            "💰 New balance: *{$balStr}*\n\n".
            'Send *WALLET* to open your wallet or *MENU* for all options.';

        $this->client->sendText($instance, $wallet->phone_e164, $text);
    }
}
