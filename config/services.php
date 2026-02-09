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

    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY', ''),
        'secret_key' => env('RECAPTCHA_SECRET_KEY', ''),
        'enabled' => env('RECAPTCHA_ENABLED', true),
        'version' => env('RECAPTCHA_VERSION', 'v3'), // 'v2' or 'v3'
        'score_threshold' => (float) env('RECAPTCHA_SCORE_THRESHOLD', 0.5), // v3 only: 0.0-1.0, allow if score >= this
    ],

];
