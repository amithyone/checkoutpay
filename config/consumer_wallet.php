<?php

return [
    /** Per-wallet API budget for authenticated consumer routes (history/utility paginate in bursts). */
    'rate_limit_per_minute' => (int) env('CONSUMER_WALLET_RATE_LIMIT_PER_MINUTE', 240),
];
