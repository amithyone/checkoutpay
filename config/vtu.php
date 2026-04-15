<?php

return [
    'enabled' => (bool) env('VTU_NG_ENABLED', false),
    'base_url' => rtrim((string) env('VTU_NG_API_BASE', 'https://vtu.ng/wp-json/api/v2'), '/'),
    /** Override JWT login URL if VTU.ng changes it; empty = derived from `base_url` (…/wp-json/jwt-auth/v1/token). */
    'jwt_token_url' => ($jwt = (string) env('VTU_NG_JWT_URL', '')) !== '' ? rtrim($jwt, '/') : null,
    'username' => (string) env('VTU_NG_USERNAME', ''),
    'password' => (string) env('VTU_NG_PASSWORD', ''),
    /** Optional API / transaction PIN if VTU.ng expects it alongside username & password (see their docs). */
    'pin' => (string) env('VTU_NG_PIN', ''),
    'timeout' => max(10, min(120, (int) env('VTU_NG_TIMEOUT', 60))),
    /** Max characters of raw HTTP body to include in `vtu.ng.response` logs (0 = omit body). */
    'log_response_body_max_chars' => max(0, (int) env('VTU_NG_LOG_RESPONSE_MAX_CHARS', 12000)),
    'airtime_min' => (float) env('VTU_NG_AIRTIME_MIN', 50),
    'airtime_max' => (float) env('VTU_NG_AIRTIME_MAX', 50000),
    'electricity_min' => (float) env('VTU_NG_ELECTRICITY_MIN', 500),
    'data_plans_page_size' => max(3, min(12, (int) env('VTU_NG_DATA_PAGE_SIZE', 6))),
    'prefer_reseller_price' => (bool) env('VTU_NG_PREFER_RESELLER_PRICE', false),
    'webhook_secret' => (string) env('VTU_NG_WEBHOOK_SECRET', ''),
    'webhook_allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('VTU_NG_WEBHOOK_ALLOWED_IPS', ''))))),
    'refund_statuses' => array_values(array_filter(array_map('trim', explode(',', (string) env('VTU_NG_REFUND_STATUSES', 'refund,refunded,reversed,reversal,failed'))))),

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
