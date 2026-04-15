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
            $addMoneyShort = $nigeriaRails
                ? "*2* — Add / receive money (NG)\n"
                : "_NG bank pay-in only · you can still get paid with *4* (wallet send)._\n";

            return "*Wallet*\n".
                "Balance: *{$bal}*\n\n".
                "To *send* or *pay out*, finish setup (one short step at a time):\n\n".
                "*1* — *Register* → opens a *link* to set your *PIN* (never type your PIN here)\n".
                "Then reply here with *your full name* (what people see when you pay them)\n\n".
                $addMoneyShort.
                "*MENU* — all services\n\n".
                $footer;
        }

        return "*Wallet*\n".
            "Balance: *{$bal}*\n\n".
            "Last step: reply with *your full name* once (e.g. *Ade Johnson*) — required before you send to others.\n\n".
            $addMoneyLine.
            "*MENU* — all services\n\n".
            $footer;
    }
}
