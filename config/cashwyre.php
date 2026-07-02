<?php

return [
    'base_url' => rtrim((string) env('CASHWYRE_BASE_URL', 'https://businessapi.cashwyre.com/api/v1.0'), '/'),
    'app_id' => (string) env('CASHWYRE_APP_ID', ''),
    'business_code' => (string) env('CASHWYRE_BUSINESS_CODE', ''),
    'public_key' => (string) env('CASHWYRE_PUBLIC_KEY', ''),
    'secret_key' => (string) env('CASHWYRE_SECRET_KEY', ''),
    'webhook_secret' => (string) env('CASHWYRE_WEBHOOK_SECRET', ''),
    'timeout_seconds' => (int) env('CASHWYRE_TIMEOUT_SECONDS', 30),
    'connect_timeout_seconds' => (int) env('CASHWYRE_CONNECT_TIMEOUT_SECONDS', 5),
    'default_card_brand' => (string) env('CASHWYRE_DEFAULT_CARD_BRAND', 'Visa'),
    'default_phone_code' => (string) env('CASHWYRE_DEFAULT_PHONE_CODE', '+234'),
    'fx_rate_cache_seconds' => (int) env('CASHWYRE_FX_RATE_CACHE_SECONDS', 600),
    'paths' => [
        'get_fx_rates' => (string) env('CASHWYRE_PATH_GET_FX_RATES', '/businessRate/getFxRates'),
        'rate_info' => (string) env('CASHWYRE_PATH_RATE_INFO', '/businessRate/rateInfo'),
        'create_customer' => (string) env('CASHWYRE_PATH_CREATE_CUSTOMER', '/Customer/createCustomer'),
        'create_card' => (string) env('CASHWYRE_PATH_CREATE_CARD', '/CustomerCard/createCard'),
        'topup_card' => (string) env('CASHWYRE_PATH_TOPUP_CARD', '/CustomerCard/topup'),
        'withdraw_card' => (string) env('CASHWYRE_PATH_WITHDRAW_CARD', '/CustomerCard/cardwithdrawal'),
        'freeze_card' => (string) env('CASHWYRE_PATH_FREEZE_CARD', '/customerCard/freezeCard'),
        'unfreeze_card' => (string) env('CASHWYRE_PATH_UNFREEZE_CARD', '/customerCard/unfreezeCard'),
        'card_details' => (string) env('CASHWYRE_PATH_CARD_DETAILS', '/CustomerCard/getCard'),
        'card_transactions' => (string) env('CASHWYRE_PATH_CARD_TRANSACTIONS', '/CustomerCard/getCardTransactions'),
    ],
];
