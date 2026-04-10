<?php

return [
    'enabled' => (bool) env('VTU_NG_ENABLED', false),
    'base_url' => rtrim((string) env('VTU_NG_API_BASE', 'https://vtu.ng/wp-json/api/v2'), '/'),
    'username' => (string) env('VTU_NG_USERNAME', ''),
    'password' => (string) env('VTU_NG_PASSWORD', ''),
    'timeout' => max(10, min(120, (int) env('VTU_NG_TIMEOUT', 60))),
    'airtime_min' => (float) env('VTU_NG_AIRTIME_MIN', 50),
    'airtime_max' => (float) env('VTU_NG_AIRTIME_MAX', 50000),
    'electricity_min' => (float) env('VTU_NG_ELECTRICITY_MIN', 500),
    'data_plans_page_size' => max(3, min(12, (int) env('VTU_NG_DATA_PAGE_SIZE', 6))),
    'prefer_reseller_price' => (bool) env('VTU_NG_PREFER_RESELLER_PRICE', false),

    /** VTU.ng `network_id` / data `service_id` values */
    'networks' => [
        ['id' => 'mtn', 'label' => 'MTN'],
        ['id' => 'glo', 'label' => 'Glo'],
        ['id' => 'airtel', 'label' => 'Airtel'],
        ['id' => '9mobile', 'label' => '9mobile'],
    ],

    /** Disco `service_id` for verify-customer + electricity purchase; `variation_id` is prepaid|postpaid */
    'electricity_discos' => [
        ['id' => 'ikeja-electric', 'label' => 'Ikeja (IKEDC)'],
        ['id' => 'eko-electric', 'label' => 'Eko (EKEDC)'],
        ['id' => 'abuja-electric', 'label' => 'Abuja (AEDC)'],
        ['id' => 'ibadan-electric', 'label' => 'Ibadan (IBEDC)'],
        ['id' => 'jos-electric', 'label' => 'Jos (JED)'],
        ['id' => 'kaduna-electric', 'label' => 'Kaduna (KAEDCO)'],
        ['id' => 'kano-electric', 'label' => 'Kano (KEDCO)'],
        ['id' => 'portharcourt-electric', 'label' => 'Port Harcourt (PHED)'],
        ['id' => 'enugu-electric', 'label' => 'Enugu (EEDC)'],
        ['id' => 'benin-electric', 'label' => 'Benin (BEDC)'],
        ['id' => 'aba-electric', 'label' => 'Aba Power (APL)'],
        ['id' => 'yola-electric', 'label' => 'Yola (YEDC)'],
    ],
];
