<?php

namespace App\Support;

/**
 * Public marketing copy and URLs for the WhatsApp Wallet product page.
 */
class WhatsappWalletMarketing
{
    public static function pageUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/whatsapp-wallet';
    }

    public static function appUrl(): string
    {
        return rtrim((string) config('whatsapp.wallet_app_url', 'https://app.check-outnow.com'), '/');
    }

    /**
     * Optional wa.me or WhatsApp deep link (WHATSAPP_WALLET_CONTACT_URL).
     */
    public static function contactUrl(): ?string
    {
        $url = trim((string) config('checkout.whatsapp_wallet.contact_url', ''));

        return $url !== '' ? $url : null;
    }

    public static function brandName(): string
    {
        return (string) config('whatsapp.bot_brand_name', 'CheckoutNow');
    }

    public static function tier1MaxBalance(): string
    {
        return number_format((float) config('whatsapp.wallet.tier1_max_balance', 50000), 0);
    }

    public static function tier1DailyLimit(): string
    {
        return number_format((float) config('whatsapp.wallet.tier1_daily_transfer_limit', 50000), 0);
    }
}
