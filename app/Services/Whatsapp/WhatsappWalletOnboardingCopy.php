<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;

/**
 * Short WhatsApp copy for new wallets (PIN / display name not finished yet).
 */
final class WhatsappWalletOnboardingCopy
{
    public static function compactWalletSubmenuBody(WhatsappWallet $wallet): string
    {
        $bal = '₦'.number_format((float) $wallet->balance, 2);
        $footer = WhatsappMenuInputNormalizer::navigationHelpFooter().' · *STOP* — pause';

        if (! $wallet->hasPin()) {
            return "*Wallet*\n".
                "Balance: *{$bal}*\n\n".
                "Finish setup so you can use your money:\n".
                "1️⃣ *REGISTER* — set a *4-digit PIN*\n".
                "2️⃣ Then send *your name* (what people see when you pay them)\n\n".
                "*1* — Add / receive money\n".
                "*MENU* — all services\n\n".
                $footer;
        }

        return "*Wallet*\n".
            "Balance: *{$bal}*\n\n".
            "Send *your name* in one message (e.g. *Ade Johnson*) — needed before you can send to others.\n\n".
            "*1* — Add / receive money\n".
            "*MENU* — all services\n\n".
            $footer;
    }
}
