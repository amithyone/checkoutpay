<?php

return [
    'inbound_threshold' => (float) env('MEVONPAY_INBOUND_FEE_THRESHOLD', 10000),
    'inbound_fee_below' => (int) env('MEVONPAY_INBOUND_FEE_BELOW', 30),
    'inbound_fee_at_or_above' => (int) env('MEVONPAY_INBOUND_FEE_AT_OR_ABOVE', 50),
    'outbound_api_fee' => (int) env('MEVONPAY_OUTBOUND_API_FEE', 10),
    'reconciliation_tolerance' => (float) env('MEVONPAY_RECONCILIATION_TOLERANCE', 0.01),
];
