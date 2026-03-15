<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the email account that receives bank transfer
    | notifications.
    |
    */

    'email_host' => env('EMAIL_HOST', 'imap.gmail.com'),
    'email_port' => env('EMAIL_PORT', 993),
    'email_encryption' => env('EMAIL_ENCRYPTION', 'ssl'),
    'email_validate_cert' => env('EMAIL_VALIDATE_CERT', false), // Set to false for Gmail compatibility
    'email_user' => env('EMAIL_USER'),
    'email_password' => env('EMAIL_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Payment Matching
    |--------------------------------------------------------------------------
    |
    | Configuration for payment matching logic.
    |
    */

    'amount_tolerance' => env('PAYMENT_AMOUNT_TOLERANCE', 0.01),
    'expiration_hours' => env('PAYMENT_EXPIRATION_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Match batch sizes (memory management)
    |--------------------------------------------------------------------------
    | Limit rows per match run to avoid loading 10k+ emails/payments into RAM.
    */
    'match_batch_size_payments' => (int) env('MATCH_BATCH_SIZE_PAYMENTS', 200),
    'match_batch_size_emails' => (int) env('MATCH_BATCH_SIZE_EMAILS', 300),
    'match_per_payment_email_limit' => (int) env('MATCH_PER_PAYMENT_EMAIL_LIMIT', 100),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook notifications.
    |
    */

    'webhook_timeout' => env('WEBHOOK_TIMEOUT', 30),
    'webhook_retry_attempts' => env('WEBHOOK_RETRY_ATTEMPTS', 3),
];
