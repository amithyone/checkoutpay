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
        $resolver = app(WhatsappWalletCountryResolver::class);
        $cur = $resolver->currencyForPhoneE164((string) $wallet->phone_e164);
        $nigeriaRails = $resolver->isNigeriaPayInWallet((string) $wallet->phone_e164);
        $bal = WhatsappWalletMoneyFormatter::format((float) $wallet->balance, $cur);
        $footer = WhatsappMenuInputNormalizer::navigationHelpFooter().' · *STOP* — pause';

        $addMoneyLine = $nigeriaRails
            ? "*1* — Add / receive money\n"
            : "_Bank add-money is only for Nigeria numbers for now — you can still receive via *4* (WhatsApp send to you)._\n";

        if (! $wallet->hasPin()) {
            return "*Wallet*\n".
                "Balance: *{$bal}*\n\n".
                "Finish setup so you can use your money:\n".
                "1️⃣ *REGISTER* — open the *link* to set your PIN (not in chat)\n".
                "2️⃣ Then send *your name* (what people see when you pay them)\n\n".
                $addMoneyLine.
                "*MENU* — all services\n\n".
                $footer;
        }

        return "*Wallet*\n".
            "Balance: *{$bal}*\n\n".
            "Send *your name* in one message (e.g. *Ade Johnson*) — needed before you can send to others.\n\n".
            $addMoneyLine.
            "*MENU* — all services\n\n".
            $footer;
    }
}
