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
    /** VTU.ng async electricity statuses (token usually arrives after requery/webhook). */
    'electricity_processing_statuses' => array_values(array_filter(array_map('trim', explode(',', (string) env('VTU_NG_ELECTRICITY_PROCESSING_STATUSES', 'processing-api,queued-api,processing,pending,queued'))))),
    'electricity_completed_statuses' => array_values(array_filter(array_map('trim', explode(',', (string) env('VTU_NG_ELECTRICITY_COMPLETED_STATUSES', 'completed-api,completed,success,successful'))))),
    'electricity_reconcile_hours' => max(1, (int) env('VTU_NG_ELECTRICITY_RECONCILE_HOURS', 48)),
    'electricity_reconcile_batch_size' => max(1, (int) env('VTU_NG_ELECTRICITY_RECONCILE_BATCH', 20)),
    'electricity_reconcile_max_per_wallet' => max(1, (int) env('VTU_NG_ELECTRICITY_RECONCILE_MAX_PER_WALLET', 3)),
    'electricity_reconcile_min_age_minutes' => max(0, (int) env('VTU_NG_ELECTRICITY_RECONCILE_MIN_AGE', 2)),
    'electricity_reconcile_min_interval_minutes' => max(1, (int) env('VTU_NG_ELECTRICITY_RECONCILE_INTERVAL', 3)),
    /** How long to cache VTU.ng JWT before re-login (VTU may invalidate sooner on new sessions). */
    'jwt_cache_minutes' => max(5, min(720, (int) env('VTU_NG_JWT_CACHE_MINUTES', 50))),

    /** VTU.ng `network_id` / data `service_id` values */
    'networks' => [
        ['id' => 'mtn', 'label' => 'MTN'],
        ['id' => 'glo', 'label' => 'Glo'],
        ['id' => 'airtel', 'label' => 'Airtel'],
        ['id' => '9mobile', 'label' => '9mobile'],
    ],

    /** Cable TV `service_id` for verify-customer + /tv purchase */
    'cable_tv_services' => [
        ['id' => 'dstv', 'label' => 'DStv'],
        ['id' => 'gotv', 'label' => 'GOtv'],
        ['id' => 'startimes', 'label' => 'StarTimes'],
        ['id' => 'showmax', 'label' => 'Showmax'],
    ],

    /** Betting `service_id` for verify-customer + /betting (must match provider API spelling). */
    'betting_services' => [
        ['id' => 'Bet9ja', 'label' => 'Bet9ja'],
        ['id' => 'BetKing', 'label' => 'BetKing'],
        ['id' => '1xBet', 'label' => '1xBet'],
        ['id' => 'BangBet', 'label' => 'BangBet'],
        ['id' => 'BetWay', 'label' => 'BetWay'],
        ['id' => 'MerryBet', 'label' => 'MerryBet'],
        ['id' => 'NairaBet', 'label' => 'NairaBet'],
        ['id' => 'NaijaBet', 'label' => 'NaijaBet'],
        ['id' => 'SupaBet', 'label' => 'SupaBet'],
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
