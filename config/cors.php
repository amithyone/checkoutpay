<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://abjrentals.ng',
        'https://www.abjrentals.ng',
        'https://camrentals.ng',
        'https://www.camrentals.ng',
        '*', // keep flexible during initial development; tighten later if needed
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];

