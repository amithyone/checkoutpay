<?php

return [
    'enabled' => (bool) env('MEVONPAY_VTU_ENABLED', true),

    'paths' => [
        'balance' => '/V1/balance',
        'exchange' => '/V1/exchange',
        'electricity' => '/V1/electricity',
        'cabletv' => '/V1/cabletv',
        'airtime' => '/V1/airtime',
        'data' => '/V1/data',
        'betting' => '/V1/betting',
    ],

    'catalog_cache_seconds' => max(60, (int) env('MEVONPAY_VTU_CATALOG_CACHE_SECONDS', 300)),

    /** Fallback networks when getInfo does not list airtime providers */
    'networks_fallback' => [
        ['id' => 'mtn', 'label' => 'MTN'],
        ['id' => 'glo', 'label' => 'Glo'],
        ['id' => 'airtel', 'label' => 'Airtel'],
        ['id' => '9mobile', 'label' => '9mobile'],
    ],

    'airtime_min' => (float) env('MEVONPAY_VTU_AIRTIME_MIN', 50),
    'airtime_max' => (float) env('MEVONPAY_VTU_AIRTIME_MAX', 50000),
    'electricity_min' => (float) env('MEVONPAY_VTU_ELECTRICITY_MIN', 500),

    'refund_statuses' => array_values(array_filter(array_map('trim', explode(',', (string) env('MEVONPAY_VTU_REFUND_STATUSES', 'refund,refunded,reversed,reversal,failed'))))),
];
