<?php

use Illuminate\Support\Str;

return [
    'driver' => env('SESSION_DRIVER', 'database'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    /**
     * Admin panel + investor pitch use at least this many minutes (whichever is higher
     * vs lifetime) so password/forms stay valid through long review sessions.
     */
    'admin_investor_lifetime' => max(120, (int) env('SESSION_ADMIN_INVESTOR_LIFETIME', 720)),
    /** How often open admin/investor pages refresh CSRF via /session/keepalive (seconds). */
    'keepalive_interval_seconds' => max(60, (int) env('SESSION_KEEPALIVE_INTERVAL', 300)),
    'expire_on_close' => false,
    'encrypt' => env('SESSION_ENCRYPT', false),
    'files' => storage_path('framework/sessions'),
    'connection' => null,
    'table' => 'sessions',
    'store' => null,
    'lottery' => [2, 100],
    'cookie' => env('SESSION_COOKIE', Str::slug(env('APP_NAME', 'laravel'), '_').'_session'),
    'path' => env('SESSION_PATH', '/'),
    /*
     * Null SESSION_SECURE_COOKIE used to become false via `$config['secure'] ?? false`
     * in StartSession — session cookie then lacked Secure while XSRF-TOKEN still had it.
     * That breaks login on some mobile browsers. Default to Secure when APP_URL is https.
     */
    'domain' => env('SESSION_DOMAIN') ?: null,
    'secure' => ($secureEnv = env('SESSION_SECURE_COOKIE')) === null
        ? str_starts_with(strtolower((string) env('APP_URL', '')), 'https://')
        : filter_var($secureEnv, FILTER_VALIDATE_BOOLEAN),
    'http_only' => true,
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
    'partitioned' => false,
];
