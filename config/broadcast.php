<?php

return [
    /** Admin key for terminal register / lookup (header X-Admin-Key). */
    'admin_key' => env('BROADCAST_ADMIN_KEY'),

    /** Max verify-broadcast attempts per IP per minute. */
    'rate_limit_verify' => (int) env('BROADCAST_RATE_LIMIT_VERIFY', 120),
];
