<?php

namespace App\Services\Whatsapp;

/**
 * CheckoutNow wallet app download link shown in WhatsApp wallet menus and receipts.
 */
final class WhatsappWalletAppLinkCopy
{
    public static function url(): string
    {
        $configured = rtrim((string) config('whatsapp.wallet_app_url', ''), '/');
        if ($configured !== '') {
            return $configured;
        }

        return 'https://app.check-outnow.com';
    }

    public static function downloadBlock(): string
    {
        return "\n\n📱 *Get our app:*\n".self::url();
    }

    public static function imageReceiptCaption(): string
    {
        return "✅ Receipt\n\n📱 Get our app:\n".self::url();
    }

    public static function menuFooter(): string
    {
        return WhatsappMenuInputNormalizer::navigationHelpFooter().self::downloadBlock();
    }

    public static function receiptFooter(): string
    {
        return "\n\n_Forward ok — no balance shown._".self::downloadBlock();
    }
}
