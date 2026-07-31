<?php

return [
    /** Admin key for terminal register / lookup (header X-Admin-Key). */
    'admin_key' => env('BROADCAST_ADMIN_KEY'),

    /** Max verify-broadcast attempts per IP per minute. */
    'rate_limit_verify' => (int) env('BROADCAST_RATE_LIMIT_VERIFY', 120),

    /**
     * Alternate POS bank_name slugs accepted per NIP bank code (hash must match one).
     * Lets merchants use CheckoutPay SDK defaults while settlement is Rubies, etc.
     */
    'bank_name_aliases' => [
        '090175' => [
            'RUBIES MFB',
            'Rubies MFB',
            'Rubies Microfinance Bank',
            'CheckoutPay',
            'checkoutpay',
            'kuda',
            'Kuda',
        ],
    ],

    /**
     * Open SDK demo defaults — accepted on CP-* terminals during POS rollout.
     */
    'pos_sdk_default_bank_names' => [
        'kuda',
        'Kuda',
        'CheckoutPay',
        'checkoutpay',
        'GTBank',
        'gtbank',
        'OPay',
        'opay',
    ],

    /** FCM nudge when a Pay at Shop till broadcasts idle/presence (native CheckoutNow). */
    'pay_at_shop_proximity_push_enabled' => filter_var(
        env('BROADCAST_PAY_AT_SHOP_PROXIMITY_PUSH_ENABLED', true),
        FILTER_VALIDATE_BOOL,
    ),
    'pay_at_shop_proximity_push_title' => env(
        'BROADCAST_PAY_AT_SHOP_PROXIMITY_PUSH_TITLE',
        'Checkout Nearby Available',
    ),
    'pay_at_shop_proximity_push_channel' => env(
        'BROADCAST_PAY_AT_SHOP_PROXIMITY_PUSH_CHANNEL',
        'wallet_alerts',
    ),
    'pay_at_shop_proximity_push_cooldown_seconds' => (int) env(
        'BROADCAST_PAY_AT_SHOP_PROXIMITY_PUSH_COOLDOWN',
        300,
    ),
];
