<?php

return [
    'issuer_enabled' => (bool) env('PARTNER_LICENSE_ISSUER_ENABLED', false),

    'enforced' => (bool) env('PARTNER_LICENSE_ENFORCED', false),

    'license_key' => trim((string) env('PARTNER_LICENSE_KEY', '')),

    'issuer_url' => rtrim((string) env('PARTNER_LICENSE_ISSUER_URL', env('APP_URL', 'http://localhost')), '/'),

    'grace_hours' => max(1, (int) env('PARTNER_LICENSE_GRACE_HOURS', 72)),

    'ping_cache_minutes' => max(5, (int) env('PARTNER_LICENSE_PING_CACHE_MINUTES', 360)),

    'manifest_path' => base_path('partner-build-manifest.json'),

    'product_slug' => 'checkoutpay-partner',

    'latest_build_version' => trim((string) env('PARTNER_LICENSE_LATEST_BUILD_VERSION', '')),

    'encoded_paths' => [
        'app/Http/Controllers',
        'app/Http/Middleware',
        'app/Services',
        'app/Jobs',
        'app/Console/Commands',
        'app/Models',
        'app/Providers',
        'app/Support',
        'app/Exceptions',
        'app/Listeners',
        'app/Notifications',
    ],
];
