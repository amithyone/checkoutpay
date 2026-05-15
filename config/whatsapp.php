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
    | Customer-facing name in WhatsApp bot copy (not internal payment gateway names).
    */
    'bot_brand_name' => (string) env('WHATSAPP_BOT_BRAND_NAME', 'CheckoutNow'),

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
    */
    'wallet_app_url' => rtrim((string) env('WHATSAPP_WALLET_APP_URL', 'https://app.check-outnow.com'), '/'),

    /*
    | WhatsApp Wallet tiers: Tier 1 = WhatsApp identity only (caps). Tier 2 = Mevon Rubies VA + full KYC.
    */
    'wallet' => [
        'tier1_max_balance' => (float) env('WHATSAPP_WALLET_TIER1_MAX_BALANCE', 50000),
        'tier1_daily_transfer_limit' => (float) env('WHATSAPP_WALLET_TIER1_DAILY_TRANSFER', 50000),
        /** TTL for partner pay intent (WhatsApp + PIN link). */
        'partner_pay_intent_ttl_minutes' => max(5, min(120, (int) env('WHATSAPP_WALLET_PARTNER_PAY_INTENT_TTL_MINUTES', 30))),
        /** MevonPay createtempva: display name parts (last name gets phone suffix for uniqueness). */
        'tier1_temp_va_fname' => (string) env('WHATSAPP_WALLET_TIER1_TEMP_VA_FNAME', 'WhatsApp'),
        'tier1_temp_va_lname' => (string) env('WHATSAPP_WALLET_TIER1_TEMP_VA_LNAME', 'User'),
        /** Hours until an unused Tier 1 top-up VA stops accepting webhook matches. */
        'tier1_temp_va_ttl_hours' => (int) env('WHATSAPP_WALLET_TIER1_TEMP_VA_TTL_HOURS', 48),
        /** Banks per page in *Transfer → bank* numbered picker (reply 1–N, MORE/PREV). */
        'bank_picker_page_size' => max(4, min(12, (int) env('WHATSAPP_WALLET_BANK_PICKER_PAGE_SIZE', 8))),
        /** Secure web link + cache TTL for confirming transfers (wallet PIN on web only). Tier 2 may enable email OTP via *7* SETTINGS (default off). */
        'transfer_confirm_ttl_minutes' => max(5, min(60, (int) env('WHATSAPP_WALLET_TRANSFER_CONFIRM_TTL_MINUTES', 15))),
        /** One-time web link TTL for *REGISTER* wallet PIN setup (defaults to transfer_confirm TTL if unset). */
        'pin_setup_web_ttl_minutes' => max(5, min(60, (int) env('WHATSAPP_WALLET_PIN_SETUP_WEB_TTL_MINUTES', 15))),
        /** Legacy: only rows created before no-expiry P2P used this TTL. New pending P2P credits use no auto-expiry. */
        'p2p_pending_claim_minutes' => max(5, min(120, (int) env('WHATSAPP_WALLET_P2P_PENDING_CLAIM_MINUTES', 30))),
        /** After bank / instant P2P success, send a small PNG receipt (requires GD). Safe to forward — no balance. */
        'send_transfer_receipt_image' => filter_var(env('WHATSAPP_SEND_TRANSFER_RECEIPT_IMAGE', true), FILTER_VALIDATE_BOOL),
        /** Optional TTF for receipt PNG text (UTF-8 names). Falls back to built-in font + ASCII fold if missing. */
        'receipt_font_path' => (string) env('WHATSAPP_RECEIPT_FONT_PATH', ''),
    ],

    'evolution' => [
        'base_url' => rtrim((string) env('WHATSAPP_EVOLUTION_BASE_URL', ''), '/'),
        'api_key' => (string) env('WHATSAPP_EVOLUTION_API_KEY', ''),
        /** Default instance name if the webhook payload omits it */
        'instance' => (string) env('WHATSAPP_EVOLUTION_INSTANCE', ''),
        /** Wallet/default operational instance for wallet sends. */
        'wallet_instance' => (string) env('WHATSAPP_EVOLUTION_INSTANCE_WALLET', ''),
        /** Dedicated rentals-only inbound instance (optional). */
        'rentals_instance' => (string) env('WHATSAPP_EVOLUTION_INSTANCE_RENTALS', ''),
    ],

    'otp' => [
        'ttl_minutes' => (int) env('WHATSAPP_OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) env('WHATSAPP_OTP_MAX_ATTEMPTS', 5),
    ],
];
