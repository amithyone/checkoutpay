<?php

return [
    /** OTP sent to wallet phone (Evolution WhatsApp) for mobile app login. */
    'otp_ttl_seconds' => (int) env('CONSUMER_WALLET_OTP_TTL_SECONDS', 600),
    'otp_length' => 6,
    'otp_max_attempts' => (int) env('CONSUMER_WALLET_OTP_MAX_ATTEMPTS', 5),

    /** Plain token prefix shown in logs only (never log full token). */
    'token_name' => 'consumer_mobile',

    /** Base URL encoded in receive / pay QR codes (path: /pay/{token}). */
    'pay_qr_base_url' => rtrim((string) env('CONSUMER_PAY_QR_BASE_URL', 'https://app.check-outnow.com'), '/'),

    /** HMAC secret for pay QR tokens; falls back to APP_KEY when empty. */
    'pay_qr_secret' => (string) env('CONSUMER_PAY_QR_SECRET', ''),
];
