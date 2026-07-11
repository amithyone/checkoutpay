<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin panel URL path
    |--------------------------------------------------------------------------
    |
    | Real admin UI lives at /{path} (default: enter0). Keep this uncommon.
    | Do not use "admin" — that path is reserved as a honeypot.
    |
    */
    'path' => trim((string) env('ADMIN_PATH', 'enter0'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Honeypot path (scanner bait)
    |--------------------------------------------------------------------------
    |
    | Requests under this prefix are logged and never authenticate.
    | After enough hits, the client IP is temporarily blocked.
    |
    */
    'honeypot_path' => trim((string) env('ADMIN_HONEYPOT_PATH', 'admin'), '/'),

    'honeypot' => [
        'enabled' => (bool) env('ADMIN_HONEYPOT_ENABLED', true),
        /** Hits within the window before the IP is banned. */
        'max_hits' => (int) env('ADMIN_HONEYPOT_MAX_HITS', 3),
        /** Sliding window for counting hits (minutes). */
        'hit_window_minutes' => (int) env('ADMIN_HONEYPOT_HIT_WINDOW', 60),
        /** How long a banned IP stays blocked (minutes). */
        'ban_minutes' => (int) env('ADMIN_HONEYPOT_BAN_MINUTES', 1440),
    ],

];
