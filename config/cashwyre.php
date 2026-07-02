<?php

return [
    'base_url' => rtrim((string) env('CASHWYRE_BASE_URL', 'https://business.cashwyre.com/api'), '/'),
    'api_key' => (string) env('CASHWYRE_API_KEY', ''),
    'secret_key' => (string) env('CASHWYRE_SECRET_KEY', ''),
    'webhook_secret' => (string) env('CASHWYRE_WEBHOOK_SECRET', ''),
    'timeout_seconds' => (int) env('CASHWYRE_TIMEOUT_SECONDS', 30),
    'connect_timeout_seconds' => (int) env('CASHWYRE_CONNECT_TIMEOUT_SECONDS', 5),
    'default_card_brand' => strtoupper((string) env('CASHWYRE_DEFAULT_CARD_BRAND', 'VISA')),
    'paths' => [
        'create_customer' => (string) env('CASHWYRE_PATH_CREATE_CUSTOMER', '/create_customer'),
        'create_card' => (string) env('CASHWYRE_PATH_CREATE_CARD', '/create_card'),
        'topup_card' => (string) env('CASHWYRE_PATH_TOPUP_CARD', '/topup_card'),
        'withdraw_card' => (string) env('CASHWYRE_PATH_WITHDRAW_CARD', '/withdraw_card'),
        'card_status' => (string) env('CASHWYRE_PATH_CARD_STATUS', '/card_status'),
        'card_balance' => (string) env('CASHWYRE_PATH_CARD_BALANCE', '/card_balance'),
        'card_details' => (string) env('CASHWYRE_PATH_CARD_DETAILS', '/card_details'),
        'card_transactions' => (string) env('CASHWYRE_PATH_CARD_TRANSACTIONS', '/card_transactions'),
    ],
];
