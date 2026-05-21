<?php

return [
    'enabled' => (bool) env('VIRTUAL_CARD_ENABLED', true),
    'request_fee_usd' => max(0.0, (float) env('VIRTUAL_CARD_REQUEST_FEE_USD', 5)),
    'fee_currency_from' => 'USD',
    'fee_currency_to' => 'NGN',
];
