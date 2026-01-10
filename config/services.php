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
        'enabled' => env('PYTHON_EXTRACTOR_ENABLED', true),
    ],

];
