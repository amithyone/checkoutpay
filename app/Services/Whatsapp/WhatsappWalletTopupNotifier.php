<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
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
     */
    public function notifyP2pReceived(
        string $senderChatInstance,
        WhatsappWallet $recipientWallet,
        float $amount,
        float $balanceAfter,
        string $senderPhoneE164,
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
        $balStr = '₦'.number_format($balanceAfter, 2);
        $tail = strlen($senderPhoneE164) >= 4 ? substr($senderPhoneE164, -4) : $senderPhoneE164;

        $text = "*Money received*\n\n".
            "You got {$amountStr} in your WhatsApp wallet from a number ending *{$tail}*.\n".
            "New balance: {$balStr}.\n\n".
            'Send *WALLET* to open your wallet or *MENU* for options.';

        $this->client->sendText($instance, $recipientWallet->phone_e164, $text);
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

        $text = "*Wallet funded*\n\n".
            "We received {$amountStr}. Your new balance is {$balStr}.\n\n".
            'Send *WALLET* to open your wallet or *MENU* for all options.';

        $this->client->sendText($instance, $wallet->phone_e164, $text);
    }
}
