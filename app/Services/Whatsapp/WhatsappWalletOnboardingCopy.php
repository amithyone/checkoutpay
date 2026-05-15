<?php

namespace App\Services\Whatsapp;

use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletPendingP2pCredit;

/**
 * Short WhatsApp copy for new wallets (PIN / display name not finished yet).
 * Until fully registered, users only see balance + register / name + optional pending refund — not the 1–7 menu.
 */
final class WhatsappWalletOnboardingCopy
{
    public static function compactWalletSubmenuBody(WhatsappWallet $wallet): string
    {
        $resolver = app(WhatsappWalletCountryResolver::class);
        $cur = $resolver->currencyForPhoneE164((string) $wallet->phone_e164);
        $bal = WhatsappWalletMoneyFormatter::format((float) $wallet->balance, $cur);
        $footer = WhatsappWalletAppLinkCopy::menuFooter().' · *STOP* — pause';

        $pendingLine = self::pendingIncomingCreditLine((string) $wallet->phone_e164);

        if (! $wallet->hasPin()) {
            return "*Wallet*\n".
                "Balance: *{$bal}*\n\n".
                "You need to *register* before *1*–*7* (top up, send, KYC, etc.) appears.\n\n".
                "*1* — *Register* → link to set your *PIN* (never type your PIN here)\n".
                "Then send *your full name* in one message (what people see when you pay them).\n\n".
                $pendingLine.
                "*MENU* — other services\n\n".
                $footer;
        }

        return "*Wallet*\n".
            "Balance: *{$bal}*\n\n".
            "Last step: send *your full name* once (e.g. *Ade Johnson*). Then *WALLET* shows *1*–*7*.\n\n".
            $pendingLine.
            "*MENU* — other services\n\n".
            $footer;
    }

    private static function pendingIncomingCreditLine(string $phoneE164): string
    {
        $n = WhatsappWalletPendingP2pCredit::query()
            ->where('recipient_phone_e164', $phoneE164)
            ->where('status', WhatsappWalletPendingP2pCredit::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        if ($n < 1) {
            return '';
        }

        $suffix = $n === 1 ? '' : " (*{$n}* waiting)";

        return "*CANCEL* — return pending money to the sender{$suffix}\n\n";
    }
}
