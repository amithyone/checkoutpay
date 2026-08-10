<?php

return [
    'enabled' => (bool) env('SQUAD_VTU_ENABLED', false),
    /** sandbox: https://sandbox-api-d.squadco.com — live: https://api-d.squadco.com */
    'base_url' => rtrim((string) env('SQUAD_VTU_BASE_URL', env('SQUAD_BASE_URL', 'https://sandbox-api-d.squadco.com')), '/'),
    /**
     * Squad dashboard → API and WEBHOOKS:
     * - secret_key: used as Authorization: Bearer for VTU (airtime/data) — required
     * - public_key: not used for vending calls (kept for future Squad products / webhooks)
     */
    'secret_key' => (string) env('SQUAD_VTU_SECRET_KEY', env('SQUAD_SECRET_KEY', '')),
    'public_key' => (string) env('SQUAD_VTU_PUBLIC_KEY', env('SQUAD_PUBLIC_KEY', '')),
    'timeout_seconds' => max(5, (int) env('SQUAD_VTU_TIMEOUT_SECONDS', 45)),
    'connect_timeout_seconds' => max(2, (int) env('SQUAD_VTU_CONNECT_TIMEOUT_SECONDS', 5)),
    'airtime_min' => max(50, (float) env('SQUAD_VTU_AIRTIME_MIN', 50)),
    'airtime_max' => max(50, (float) env('SQUAD_VTU_AIRTIME_MAX', 50000)),
    'data_plans_cache_seconds' => max(60, (int) env('SQUAD_VTU_DATA_PLANS_CACHE_SECONDS', 600)),

    /**
     * Our catalog ids → Squad network query values.
     * Airtime vend does not require network (detected from MSISDN).
     */
    'networks' => [
        ['id' => 'mtn', 'label' => 'MTN', 'squad' => 'MTN'],
        ['id' => 'glo', 'label' => 'Glo', 'squad' => 'GLO'],
        ['id' => 'airtel', 'label' => 'Airtel', 'squad' => 'AIRTEL'],
        ['id' => '9mobile', 'label' => '9mobile', 'squad' => '9MOBILE'],
    ],

    'paths' => [
        'airtime' => '/vending/purchase/airtime',
        'data' => '/vending/purchase/data',
        'data_bundles' => '/vending/data-bundles',
        'transactions' => '/vending/transactions',
    ],
];
