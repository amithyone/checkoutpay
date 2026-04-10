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
        'webhook_secret' => env('MEVONPAY_WEBHOOK_SECRET', env('SLA_WEBHOOK_SECRET', env('MAVONPAY_WEBHOOK_SECRET', ''))),
        'webhook_allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('MEVONPAY_WEBHOOK_ALLOWED_IPS', ''))))),
        'webhook_allowed_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('MEVONPAY_WEBHOOK_ALLOWED_DOMAINS', ''))))),
        'debit_account_name' => env('MEVONPAY_DEBIT_ACCOUNT_NAME', ''),
        'debit_account_number' => env('MEVONPAY_DEBIT_ACCOUNT_NUMBER', ''),
        'current_password' => env('MEVONPAY_CURRENT_PASSWORD', ''),
        'timeout_seconds' => (int) env('MEVONPAY_TIMEOUT_SECONDS', 20),
        'connect_timeout_seconds' => (int) env('MEVONPAY_CONNECT_TIMEOUT_SECONDS', 3),
        'temp_va_registration_number' => env('MEVONPAY_TEMP_VA_REGISTRATION_NUMBER', ''),
        'account_logs_enabled' => (bool) env('MEVONPAY_ACCOUNT_LOGS_ENABLED', false),
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

    'firebase' => [
        'project_id' => env('FCM_PROJECT_ID', ''),
        /*
         * Service account: single-line minified JSON, OR path to a .json file.
         * Do not put multi-line JSON in .env — it invalidates the entire .env for Laravel/dotenv.
         * Relative paths are resolved from the project base path (see PushNotificationService).
         */
        'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON', ''),
    ],

    /*
    | NigTax certified reports: virtual-account payments are created under this Business.
    | Must have at least one website (webhook domain). Optional override for webhook_url.
    */
    'nigtax' => [
        'payment_business_id' => (int) env('NIGTAX_PAYMENT_BUSINESS_ID', 0),
        'payment_webhook_url' => env('NIGTAX_PAYMENT_WEBHOOK_URL', ''),
        // Membership slug for ₦2,000/mo PRO (Taxcalculate: multi-statement + per-file PDF passwords)
        'pro_membership_slug' => env('NIGTAX_PRO_MEMBERSHIP_SLUG', 'nigtax-pro'),
        // After PRO password reset, redirect users here (e.g. https://yournigtax.com). Falls back to APP_URL.
        'calculator_url' => env('NIGTAX_CALCULATOR_URL', ''),
    ],

];
