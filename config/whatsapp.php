<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout ↔ WhatsApp (Evolution API)
    |--------------------------------------------------------------------------
    |
    | Webhook URL (Evolution Manager → Webhook, or):
    |   php artisan whatsapp:configure-webhook
    | Target: POST {WHATSAPP_APP_URL or APP_URL}/api/v1/whatsapp/webhook
    |
    | Recommended header: X-Checkout-WhatsApp-Secret: {WHATSAPP_WEBHOOK_SECRET}
    | Or query: ?secret={WHATSAPP_WEBHOOK_SECRET}
    |
    */

    'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET', ''),

    /*
    | Public HTTPS base for WhatsApp (magic links in email, default webhook URL).
    | When APP_URL is http://localhost, set WHATSAPP_APP_URL to your live site.
    */
    'public_url' => rtrim((string) env('WHATSAPP_APP_URL', env('APP_URL', '')), '/'),

    /*
    | Public “product” sites (shown in WhatsApp menus; override per environment).
    | Business = merchant dashboard & payouts; Rentals = renter catalog; Tax = NigTax.
    */
    'portals' => [
        'business' => rtrim((string) env('PORTAL_URL_BUSINESS', 'https://check-outnow.com'), '/'),
        'rentals' => rtrim((string) env('PORTAL_URL_RENTALS', 'https://abjrentals.ng'), '/'),
        'tax' => rtrim((string) env('PORTAL_URL_TAX', 'https://nigtax.com'), '/'),
    ],

    /*
    | Web app where users create the WhatsApp Wallet, complete KYC, and view tx history.
    | Falls back to public_url when empty.
    */
    'wallet_app_url' => rtrim((string) env('WHATSAPP_WALLET_APP_URL', ''), '/'),

    /*
    | WhatsApp Wallet tiers: Tier 1 = WhatsApp identity only (caps). Tier 2 = Mevon Rubies VA + full KYC.
    */
    'wallet' => [
        'tier1_max_balance' => (float) env('WHATSAPP_WALLET_TIER1_MAX_BALANCE', 50000),
        'tier1_daily_transfer_limit' => (float) env('WHATSAPP_WALLET_TIER1_DAILY_TRANSFER', 50000),
        /** MevonPay createtempva: display name parts (last name gets phone suffix for uniqueness). */
        'tier1_temp_va_fname' => (string) env('WHATSAPP_WALLET_TIER1_TEMP_VA_FNAME', 'WhatsApp'),
        'tier1_temp_va_lname' => (string) env('WHATSAPP_WALLET_TIER1_TEMP_VA_LNAME', 'User'),
        /** Hours until an unused Tier 1 top-up VA stops accepting webhook matches. */
        'tier1_temp_va_ttl_hours' => (int) env('WHATSAPP_WALLET_TIER1_TEMP_VA_TTL_HOURS', 48),
    ],

    'evolution' => [
        'base_url' => rtrim((string) env('WHATSAPP_EVOLUTION_BASE_URL', ''), '/'),
        'api_key' => (string) env('WHATSAPP_EVOLUTION_API_KEY', ''),
        /** Default instance name if the webhook payload omits it */
        'instance' => (string) env('WHATSAPP_EVOLUTION_INSTANCE', ''),
    ],

    'otp' => [
        'ttl_minutes' => (int) env('WHATSAPP_OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) env('WHATSAPP_OTP_MAX_ATTEMPTS', 5),
    ],
];
