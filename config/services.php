<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'python_extractor' => [
        'url' => env('PYTHON_EXTRACTOR_URL', 'http://localhost:8000'),
        'timeout' => env('PYTHON_EXTRACTOR_TIMEOUT', 10),
        'min_confidence' => env('PYTHON_EXTRACTOR_MIN_CONFIDENCE', 0.7),
        'enabled' => env('PYTHON_EXTRACTOR_ENABLED', false), // Disabled by default - PHP extraction is more reliable
        'mode' => env('PYTHON_EXTRACTOR_MODE', 'http'), // 'http' for FastAPI, 'script' for shared hosting
        'script_path' => env('PYTHON_EXTRACTOR_SCRIPT_PATH', base_path('python-extractor/extract_simple.py')),
        'python_command' => env('PYTHON_EXTRACTOR_COMMAND', 'python3'), // 'python3' or 'python'
    ],

    'match_attempts' => [
        'retention_days' => (int) env('MATCH_ATTEMPTS_RETENTION_DAYS', 30),
    ],

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY', ''),
        'secret_key' => env('RECAPTCHA_SECRET_KEY', ''),
        'enabled' => env('RECAPTCHA_ENABLED', true),
        'version' => env('RECAPTCHA_VERSION', 'v3'), // 'v2' or 'v3'
        'score_threshold' => (float) env('RECAPTCHA_SCORE_THRESHOLD', 0.5), // v3 only: 0.0-1.0, allow if score >= this
    ],

    'mevonpay' => [
        'base_url' => env('MEVONPAY_BASE_URL', ''),
        'secret_key' => env('MEVONPAY_SECRET_KEY', ''),
        'debit_account_name' => env('MEVONPAY_DEBIT_ACCOUNT_NAME', ''),
        'debit_account_number' => env('MEVONPAY_DEBIT_ACCOUNT_NUMBER', ''),
        'current_password' => env('MEVONPAY_CURRENT_PASSWORD', ''),
        'timeout_seconds' => (int) env('MEVONPAY_TIMEOUT_SECONDS', 20),
        'connect_timeout_seconds' => (int) env('MEVONPAY_CONNECT_TIMEOUT_SECONDS', 3),
        'temp_va_registration_number' => env('MEVONPAY_TEMP_VA_REGISTRATION_NUMBER', ''),
    ],

    'mevonrubies' => [
        // If you don't set these, the integration falls back to the MevonPay config.
        'base_url' => env('MEVONRUBIES_BASE_URL', ''),
        'secret_key' => env('MEVONRUBIES_SECRET_KEY', ''),
        'timeout_seconds' => (int) env('MEVONRUBIES_TIMEOUT_SECONDS', 20),
    ],

    /*
    | NUBAN (app.nuban.com.ng) – account validation / possible banks
    */
    'nuban' => [
        'api_key' => env('NUBAN_API_KEY', ''),
        'base_url' => rtrim(env('NUBAN_BASE_URL', 'https://app.nuban.com.ng/api'), '/'),
        'possible_banks_url' => rtrim(env('NUBAN_POSSIBLE_BANKS_URL', 'https://app.nuban.com.ng/possible-banks'), '/'),
        'timeout_seconds' => (int) env('NUBAN_TIMEOUT_SECONDS', 10),
        'connect_timeout_seconds' => (int) env('NUBAN_CONNECT_TIMEOUT_SECONDS', 3),
    ],

];
