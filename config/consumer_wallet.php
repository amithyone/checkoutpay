<?php

return [
    /** OTP sent to wallet phone (Evolution WhatsApp) for mobile app login. */
    'otp_ttl_seconds' => (int) env('CONSUMER_WALLET_OTP_TTL_SECONDS', 600),
    'otp_length' => 6,
    'otp_max_attempts' => (int) env('CONSUMER_WALLET_OTP_MAX_ATTEMPTS', 5),

    /** Consecutive OTP sends without signing in — then PIN / recovery required. */
    'otp_max_unused_sends' => (int) env('CONSUMER_WALLET_OTP_MAX_UNUSED_SENDS', 3),

    'pin_recovery_ttl_minutes' => (int) env('CONSUMER_WALLET_PIN_RECOVERY_TTL_MINUTES', 15),
    'pin_recovery_max_failures' => (int) env('CONSUMER_WALLET_PIN_RECOVERY_MAX_FAILURES', 5),
    'pin_recovery_lockout_minutes' => (int) env('CONSUMER_WALLET_PIN_RECOVERY_LOCKOUT_MINUTES', 15),

    /** Plain token prefix shown in logs only (never log full token). */
    'token_name' => 'consumer_mobile',

    /** Base URL encoded in receive / pay QR codes (path: /pay/{token}). */
    'pay_qr_base_url' => rtrim((string) env('CONSUMER_PAY_QR_BASE_URL', 'https://app.check-outnow.com'), '/'),

    /** HMAC secret for pay QR tokens; falls back to APP_KEY when empty. */
    'pay_qr_secret' => (string) env('CONSUMER_PAY_QR_SECRET', ''),

    /** Twice-daily nudge for wallets with balance and no activity today. */
    'inactive_reminders_enabled' => (bool) env('CONSUMER_WALLET_INACTIVE_REMINDERS_ENABLED', true),
    'inactive_reminder_timezone' => (string) env('CONSUMER_WALLET_INACTIVE_REMINDER_TZ', 'Africa/Lagos'),
    'inactive_reminder_min_balance' => (float) env('CONSUMER_WALLET_INACTIVE_REMINDER_MIN_BALANCE', 1),
    'inactive_reminder_push_title' => (string) env('CONSUMER_WALLET_INACTIVE_REMINDER_PUSH_TITLE', 'Hope your day is going well'),
    'inactive_reminder_push_channel' => 'wallet_alerts',

    /** FCM when wallet is credited (bank top-up or P2P) for CheckoutNow app users. */
    'credit_push_enabled' => (bool) env('CONSUMER_WALLET_CREDIT_PUSH_ENABLED', true),
    'credit_push_title' => (string) env('CONSUMER_WALLET_CREDIT_PUSH_TITLE', 'Money received'),
    'p2p_push_title' => (string) env('CONSUMER_WALLET_P2P_PUSH_TITLE', 'Money received'),
    'credit_push_channel' => 'wallet_alerts',
];
