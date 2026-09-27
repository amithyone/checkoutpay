<?php

return [
    'app_name' => trim((string) env('BRAND_APP_NAME', 'CheckoutNow')),
    'legal_name' => trim((string) env('BRAND_LEGAL_NAME', env('BRAND_APP_NAME', 'CheckoutNow'))),
    'support_email' => trim((string) env('BRAND_SUPPORT_EMAIL', '')),
    'support_phone' => trim((string) env('BRAND_SUPPORT_PHONE', '')),
    'primary_color' => trim((string) env('BRAND_PRIMARY_COLOR', '#2563eb')),
    'consumer_app_url' => rtrim((string) env('BRAND_CONSUMER_APP_URL', ''), '/'),
    'marketing_url' => rtrim((string) env('BRAND_MARKETING_URL', ''), '/'),
];
