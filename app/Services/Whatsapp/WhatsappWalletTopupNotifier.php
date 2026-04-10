<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappSession;
use App\Models\WhatsappWallet;
use Illuminate\Support\Facades\Log;

/**
 * Sends a WhatsApp chat message when MevonPay funding credits a WhatsApp wallet (webhook path).
 */
class WhatsappWalletTopupNotifier
{
    public function __construct(
        private EvolutionWhatsAppClient $client,
    ) {}

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
