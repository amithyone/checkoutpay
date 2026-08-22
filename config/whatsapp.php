<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout ↔ WhatsApp
    |--------------------------------------------------------------------------
    |
    | Provider: evolution (Evolution API) or cloud (Meta WhatsApp Cloud API).
    |
    | Evolution webhook:
    |   POST {WHATSAPP_APP_URL or APP_URL}/api/v1/whatsapp/webhook
    |   Header: X-Checkout-WhatsApp-Secret or ?secret=
    |
    | Meta Cloud webhook (same URL):
    |   GET  …/api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=…&hub.challenge=…
    |   POST …/api/v1/whatsapp/webhook  (X-Hub-Signature-256)
    |
    | See reference/WHATSAPP_META_CLOUD_API.md for Facebook Developer setup.
    |
    */

    'provider' => strtolower((string) env('WHATSAPP_PROVIDER', 'evolution')),

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
    /** Full URL to CheckoutNow Android APK; defaults to {wallet_app_url}/checkoutnow-android.apk */
    'wallet_android_apk_url' => rtrim((string) env('WHATSAPP_WALLET_ANDROID_APK_URL', ''), '/'),

    /** Server path to the APK file served by GET /download/checkoutnow-android.apk */
    'wallet_android_apk_path' => (string) env('WHATSAPP_WALLET_ANDROID_APK_PATH', '/var/www/checkoutnow/dist/checkoutnow-android.apk'),

    /** CheckoutNow consumer app — Google Play listing. */
    'checkoutnow_play_store_url' => rtrim((string) env(
        'CHECKOUTNOW_PLAY_STORE_URL',
        'https://play.google.com/store/apps/details?id=com.checkoutnow.app'
    ), '/'),

    /** CheckoutNow consumer app — Apple App Store listing (falls back to web app URL in marketing). */
    'checkoutnow_app_store_url' => rtrim((string) env('CHECKOUTNOW_APP_STORE_URL', ''), '/'),

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
        /**
         * Wallet KYC / signup emails must use one of these popular consumer domains
         * (comma-separated via WHATSAPP_WALLET_ALLOWED_EMAIL_DOMAINS to override).
         */
        'allowed_email_domains' => array_values(array_filter(array_map(
            'strtolower',
            array_map('trim', explode(',', (string) env(
                'WHATSAPP_WALLET_ALLOWED_EMAIL_DOMAINS',
                implode(',', [
                    'gmail.com',
                    'googlemail.com',
                    'yahoo.com',
                    'yahoo.co.uk',
                    'yahoo.co.in',
                    'ymail.com',
                    'rocketmail.com',
                    'hotmail.com',
                    'hotmail.co.uk',
                    'outlook.com',
                    'outlook.co.uk',
                    'live.com',
                    'live.co.uk',
                    'msn.com',
                    'icloud.com',
                    'me.com',
                    'mac.com',
                    'aol.com',
                    'protonmail.com',
                    'proton.me',
                    'zoho.com',
                    'zohomail.com',
                    'gmx.com',
                    'mail.com',
                ])
            )))
        ))),
        /** Banks per page in *Transfer → bank* numbered picker (reply 1–N, MORE/PREV). */
        'bank_picker_page_size' => max(4, min(12, (int) env('WHATSAPP_WALLET_BANK_PICKER_PAGE_SIZE', 8))),
        /** Secure web link + cache TTL for confirming transfers (wallet PIN on web only). Tier 2 may enable email OTP via *7* SETTINGS (default off). */
        'transfer_confirm_ttl_minutes' => max(5, min(60, (int) env('WHATSAPP_WALLET_TRANSFER_CONFIRM_TTL_MINUTES', 15))),
        /** One-time web link TTL for *REGISTER* wallet PIN setup (defaults to transfer_confirm TTL if unset). */
        'pin_setup_web_ttl_minutes' => max(5, min(60, (int) env('WHATSAPP_WALLET_PIN_SETUP_WEB_TTL_MINUTES', 15))),
        /** Minimum name match score (0–100) for PIN reset after Tier 2 upgrade (profile vs bank account name). */
        'pin_reset_name_min_score' => max(50, min(100, (int) env('WHATSAPP_WALLET_PIN_RESET_NAME_MIN_SCORE', 60))),
        /** Failed BVN/name/CAC attempts before PIN reset is blocked temporarily. */
        'pin_reset_max_failures' => max(3, min(20, (int) env('WHATSAPP_WALLET_PIN_RESET_MAX_FAILURES', 5))),
        /** Minutes to block further PIN reset attempts after too many failures. */
        'pin_reset_lockout_minutes' => max(5, min(120, (int) env('WHATSAPP_WALLET_PIN_RESET_LOCKOUT_MINUTES', 15))),
        /** Lazy MevonPay TSQ for pending bank payouts when user opens wallet (hours window). */
        'payout_reconcile_hours' => max(1, (int) env('WHATSAPP_WALLET_PAYOUT_RECONCILE_HOURS', 48)),
        /** Minimum minutes between TSQ calls per pending payout (lazy reconcile only). */
        'payout_reconcile_min_interval_minutes' => max(1, (int) env('WHATSAPP_WALLET_PAYOUT_RECONCILE_MIN_INTERVAL', 5)),
        /** Max pending payouts to check per wallet menu / balance refresh. */
        'payout_reconcile_max_per_trigger' => max(1, (int) env('WHATSAPP_WALLET_PAYOUT_RECONCILE_MAX', 3)),
        /**
         * Consecutive failed MevonPay TSQ results required before auto-refunding a bank payout.
         * Immediate payout-time "failed" responses are held as pending until this many confirmations.
         */
        'payout_failed_confirmations_required' => max(2, (int) env('WHATSAPP_WALLET_PAYOUT_FAILED_CONFIRMATIONS', 2)),
        /** Legacy: only rows created before no-expiry P2P used this TTL. New pending P2P credits use no auto-expiry. */
        'p2p_pending_claim_minutes' => max(5, min(120, (int) env('WHATSAPP_WALLET_P2P_PENDING_CLAIM_MINUTES', 30))),
        /** After bank / instant P2P success, send a small PNG receipt (requires GD). Safe to forward — no balance. */
        'send_transfer_receipt_image' => filter_var(env('WHATSAPP_SEND_TRANSFER_RECEIPT_IMAGE', true), FILTER_VALIDATE_BOOL),
        /** Optional TTF for receipt PNG text (UTF-8 names). Falls back to built-in font + ASCII fold if missing. */
        'receipt_font_path' => (string) env('WHATSAPP_RECEIPT_FONT_PATH', ''),
    ],

    /*
    | Self bank transfer fee: when user sends to their own account (name match or fintech acct = WhatsApp phone).
    | Admin overrides via /admin/whatsapp-wallet (settings group whatsapp). P2P and other people's accounts stay free.
    | Defaults are 0 (no charge) until ops raises percent/fixed in admin.
    */
    'self_bank_transfer_fee_enabled' => filter_var(env('WHATSAPP_SELF_BANK_TRANSFER_FEE_ENABLED', true), FILTER_VALIDATE_BOOL),
    'self_bank_transfer_fee_percent' => (float) env('WHATSAPP_SELF_BANK_TRANSFER_FEE_PERCENT', 0),
    'self_bank_transfer_fixed_fee' => max(0.0, (float) env('WHATSAPP_SELF_BANK_TRANSFER_FIXED_FEE', 0)),
    'self_bank_transfer_max_fee' => max(0.0, (float) env('WHATSAPP_SELF_BANK_TRANSFER_MAX_FEE', 500)),
    'self_bank_transfer_name_min_score' => max(50, min(100, (int) env('WHATSAPP_SELF_BANK_TRANSFER_NAME_MIN_SCORE', 68))),
    'self_bank_transfer_fintech_bank_codes' => [
        '100004', // Opay
        '100033', // PalmPay
        '090405', // Moniepoint MFB
        '090267', // Kuda
    ],

    /*
    | Proactive outbound WhatsApp (top-up/P2P/card/money-request alerts, inactive reminders).
    | Keep false when the primary line is blocked — OTP + interactive bot replies still send.
    | Admin Setting whatsapp_proactive_notifications_enabled overrides when set.
    */
    'proactive_notifications_enabled' => filter_var(
        env('WHATSAPP_PROACTIVE_NOTIFICATIONS_ENABLED', false),
        FILTER_VALIDATE_BOOL
    ),

    'evolution' => [
        'base_url' => rtrim((string) env('WHATSAPP_EVOLUTION_BASE_URL', ''), '/'),
        'api_key' => (string) env('WHATSAPP_EVOLUTION_API_KEY', ''),
        /** Default instance name if the webhook payload omits it */
        'instance' => (string) env('WHATSAPP_EVOLUTION_INSTANCE', ''),
        /** Wallet/default operational instance for wallet sends. */
        'wallet_instance' => (string) env('WHATSAPP_EVOLUTION_INSTANCE_WALLET', ''),
        /** Dedicated rentals-only inbound instance (optional). */
        'rentals_instance' => (string) env('WHATSAPP_EVOLUTION_INSTANCE_RENTALS', ''),
        /**
         * When true, rentals_instance rejects WALLET commands (rentals-only line).
         * Set false when Checkout + Rentals share one WhatsApp number.
         */
        'rentals_dedicated_only' => filter_var(
            env('WHATSAPP_RENTALS_DEDICATED_ONLY', true),
            FILTER_VALIDATE_BOOL
        ),
    ],

    /*
    | Meta WhatsApp Cloud API (official). Set WHATSAPP_PROVIDER=cloud.
    */
    'cloud' => [
        'graph_version' => (string) env('WHATSAPP_CLOUD_GRAPH_VERSION', 'v21.0'),
        'access_token' => (string) env('WHATSAPP_CLOUD_ACCESS_TOKEN', ''),
        'app_secret' => (string) env('WHATSAPP_CLOUD_APP_SECRET', ''),
        /** Must match the Verify Token entered in Meta Developer → WhatsApp → Configuration */
        'verify_token' => (string) env('WHATSAPP_CLOUD_VERIFY_TOKEN', ''),
        /** Primary phone_number_id from Meta (WhatsApp → API Setup) */
        'phone_number_id' => (string) env('WHATSAPP_CLOUD_PHONE_NUMBER_ID', ''),
        'phone_number_id_wallet' => (string) env('WHATSAPP_CLOUD_PHONE_NUMBER_ID_WALLET', ''),
        'phone_number_id_rentals' => (string) env('WHATSAPP_CLOUD_PHONE_NUMBER_ID_RENTALS', ''),
        'waba_id' => (string) env('WHATSAPP_CLOUD_WABA_ID', ''),
    ],

    'otp' => [
        'ttl_minutes' => (int) env('WHATSAPP_OTP_TTL_MINUTES', 10),
        'max_attempts' => (int) env('WHATSAPP_OTP_MAX_ATTEMPTS', 5),
    ],
];
