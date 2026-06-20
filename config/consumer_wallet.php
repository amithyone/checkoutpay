<?php

return [
    /** Per-wallet API budget for authenticated consumer routes (history/utility paginate in bursts). */
    'rate_limit_per_minute' => (int) env('CONSUMER_WALLET_RATE_LIMIT_PER_MINUTE', 240),

    /** Server cache for merged merchant business activity (Utility full view). */
    'business_activity_cache_ttl_full' => (int) env('CONSUMER_BUSINESS_ACTIVITY_CACHE_TTL_FULL', 1800),

    /** Server cache for business account history (pay-ins + withdrawals). */
    'business_activity_cache_ttl_account' => (int) env('CONSUMER_BUSINESS_ACTIVITY_CACHE_TTL_ACCOUNT', 600),
];
