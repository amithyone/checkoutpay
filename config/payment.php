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
