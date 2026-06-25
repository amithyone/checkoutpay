<?php

return [
    /** Enable passkey device trust, step-up, and transfer lock enforcement. */
    'device_trust_enabled' => filter_var(env('CONSUMER_DEVICE_TRUST_ENABLED', true), FILTER_VALIDATE_BOOL),

    /** WebAuthn relying party ID (must match associated domains / asset links). */
    'webauthn_rp_id' => env('CONSUMER_WEBAUTHN_RP_ID', 'check-outpay.com'),

    /** WebAuthn relying party display name. */
    'webauthn_rp_name' => env('CONSUMER_WEBAUTHN_RP_NAME', 'CheckoutNow'),

    /**
     * Allowed clientDataJSON origins for native passkeys (comma-separated).
     * iOS: https://check-outpay.com
     * Android: android:apk-key-hash:BASE64URL_SHA256_OF_SIGNING_CERT
     */
    'webauthn_allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', (string) env(
            'CONSUMER_WEBAUTHN_ALLOWED_ORIGINS',
            'https://check-outpay.com'
        ))
    ))),

    /** Max single transfer amount (NGN) while transfer lock is active. */
    'high_value_single_transfer_cap' => (int) env('CONSUMER_HIGH_VALUE_SINGLE_TRANSFER_CAP', 10000),

    /** Hours to lock high-value transfers after binding a new trusted device. */
    'transfer_lock_hours' => (int) env('CONSUMER_TRANSFER_LOCK_HOURS', 24),

    /** Sanctum token name for consumer mobile sessions. */
    'token_name' => env('CONSUMER_WALLET_TOKEN_NAME', 'consumer_mobile'),

    /** Per-wallet API budget for authenticated consumer routes (history/utility paginate in bursts). */
    'rate_limit_per_minute' => (int) env('CONSUMER_WALLET_RATE_LIMIT_PER_MINUTE', 240),

    /** Server cache for merged merchant business activity (Utility full view). */
    'business_activity_cache_ttl_full' => (int) env('CONSUMER_BUSINESS_ACTIVITY_CACHE_TTL_FULL', 1800),

    /** Server cache for business account history (pay-ins + withdrawals). */
    'business_activity_cache_ttl_account' => (int) env('CONSUMER_BUSINESS_ACTIVITY_CACHE_TTL_ACCOUNT', 600),

    /** FCM push approval for new-device step-up (trusted device approves sign-in). */
    'device_stepup_push_enabled' => filter_var(env('CONSUMER_DEVICE_STEPUP_PUSH_ENABLED', true), FILTER_VALIDATE_BOOL),
    'device_stepup_push_ttl_minutes' => (int) env('CONSUMER_DEVICE_STEPUP_PUSH_TTL_MINUTES', 5),
    'device_stepup_push_poll_seconds' => (int) env('CONSUMER_DEVICE_STEPUP_PUSH_POLL_SECONDS', 3),
    'device_stepup_push_title' => env('CONSUMER_DEVICE_STEPUP_PUSH_TITLE', 'New sign-in attempt'),
    'device_stepup_push_channel' => env('CONSUMER_DEVICE_STEPUP_PUSH_CHANNEL', 'wallet_alerts'),

    /** CAC business name registration + business receive account (CheckoutNow Receive Funds). */
    'business_name_registration' => [
        'enabled' => filter_var(env('CONSUMER_BUSINESS_NAME_REGISTRATION_ENABLED', false), FILTER_VALIDATE_BOOL),
        'fee_amount' => (float) env('CONSUMER_BUSINESS_NAME_REGISTRATION_FEE', 15000),
        'fee_currency' => env('CONSUMER_BUSINESS_NAME_REGISTRATION_FEE_CURRENCY', 'NGN'),
        'coming_soon_message' => env(
            'CONSUMER_BUSINESS_NAME_REGISTRATION_COMING_SOON',
            'Business name registration coming soon.'
        ),
        'estimated_completion_hours_min' => (int) env('CONSUMER_BUSINESS_NAME_REGISTRATION_HOURS_MIN', 12),
        'estimated_completion_hours_max' => (int) env('CONSUMER_BUSINESS_NAME_REGISTRATION_HOURS_MAX', 24),
        'requirements' => [
            'Two business name options in order of preference',
            'Owner/director full legal name',
            'Government ID upload — NIN preferred (passport or driver\'s licence also accepted)',
            'Registered business address in Nigeria',
            'Short description of what the business does',
            'Registration fee debited from your wallet balance',
        ],
    ],

    /** CheckoutPay merchant business account onboarding from CheckoutNow app. */
    'business_account_onboarding' => [
        'enabled' => filter_var(env('CONSUMER_BUSINESS_ACCOUNT_ONBOARDING_ENABLED', false), FILTER_VALIDATE_BOOL),
        'fee_amount' => (float) env('CONSUMER_BUSINESS_ACCOUNT_ONBOARDING_FEE', 0),
        'fee_currency' => env('CONSUMER_BUSINESS_ACCOUNT_ONBOARDING_FEE_CURRENCY', 'NGN'),
        'coming_soon_message' => env(
            'CONSUMER_BUSINESS_ACCOUNT_ONBOARDING_COMING_SOON',
            'Business account onboarding coming soon.'
        ),
        'dashboard_login_url' => env('CONSUMER_BUSINESS_ACCOUNT_DASHBOARD_LOGIN_URL', '/dashboard/login'),
        'service_categories' => [
            ['id' => 'payments', 'label' => 'Payments & checkout'],
            ['id' => 'rentals', 'label' => 'Rentals'],
            ['id' => 'memberships', 'label' => 'Memberships'],
            ['id' => 'tickets', 'label' => 'Event tickets'],
            ['id' => 'charity', 'label' => 'Charity & donations'],
            ['id' => 'invoices', 'label' => 'Invoices'],
        ],
    ],
];
