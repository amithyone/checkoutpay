<?php

return [
    /** OTP sent to wallet phone (Evolution WhatsApp) for mobile app login. */
    'otp_ttl_seconds' => (int) env('CONSUMER_WALLET_OTP_TTL_SECONDS', 600),
    'otp_length' => 6,
    'otp_max_attempts' => (int) env('CONSUMER_WALLET_OTP_MAX_ATTEMPTS', 5),

    /** Plain token prefix shown in logs only (never log full token). */
    'token_name' => 'consumer_mobile',
];
