<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global match: max emails per run
    |--------------------------------------------------------------------------
    |
    | Only the newest unmatched processed emails (by created_at) are considered
    | each time global match runs (cron or admin). This keeps runs fast and avoids
    | cron timeouts. Older backlog is picked up on subsequent runs.
    | Set GLOBAL_MATCH_MAX_EMAILS in .env to tune (minimum 1).
    |
    */
    'global_match_max_emails' => max(1, (int) env('GLOBAL_MATCH_MAX_EMAILS', 200)),

    /*
    |--------------------------------------------------------------------------
    | WooCommerce WordPress plugin (download + version labels)
    |--------------------------------------------------------------------------
    |
    | Bump version when wordpress-plugin/copn-payment-gateway is released so
    | all site download links and cache-busting stay in sync.
    |
    */
    'wordpress_plugin' => [
        'version' => '1.4.6',
        'zip' => 'downloads/copn-payment-gateway.zip',
        'slug' => 'copn-payment-gateway',
        'requires_wordpress' => '5.8',
        'requires_woocommerce' => '7.0',
        'wordpress_org_url' => env(
            'CHECKOUT_WORDPRESS_PLUGIN_URL',
            'https://wordpress.org/plugins/copn-payment-gateway/'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Wallet (public marketing)
    |--------------------------------------------------------------------------
    |
    | contact_url: optional https://wa.me/234... for "Chat on WhatsApp" CTAs.
    |
    */
    'whatsapp_wallet' => [
        'contact_url' => env('WHATSAPP_WALLET_CONTACT_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cron / internal HTTP triggers
    |--------------------------------------------------------------------------
    |
    | Shared secret for /api/v1/transaction/check, /api/v1/cron/process-webhooks,
    | /api/v1/statistics, and web cron routes that already accept X-Cron-Token.
    | Pass via header X-Cron-Token or query ?token=
    |
    */
    'cron_api_token' => (string) env('CRON_EMAIL_FETCH_TOKEN', ''),

    /*
    | When true, /setup/* returns 404 (installation finished).
    */
    'setup_complete' => filter_var(env('APP_SETUP_COMPLETE', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Legacy domain redirect: check-outpay.com → check-outnow.com (Contabo cutover).
    */
    'legacy_host_redirect_enabled' => filter_var(env('LEGACY_HOST_REDIRECT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'legacy_host_redirect_to' => rtrim((string) env('LEGACY_HOST_REDIRECT_TO', 'https://check-outnow.com'), '/'),
    'legacy_hosts' => array_values(array_filter(array_map(
        'strtolower',
        array_map('trim', explode(',', (string) env(
            'LEGACY_HOSTS',
            'check-outpay.com,www.check-outpay.com'
        )))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Self-quarantine (hijack / empty-DB fail-closed)
    |--------------------------------------------------------------------------
    |
    | Trips when DB_HOST/database leave the allowlist or row floors fail
    | (e.g. attacker swapped DB_HOST to an empty remote). Lock file is on disk
    | so an attacker DB cannot clear quarantine. Unlock with QUARANTINE_UNLOCK_CODE
    | or delete storage/framework/quarantine.lock after fixing .error.
    |
    */
    'quarantine' => [
        'enabled' => filter_var(env('QUARANTINE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'allowed_db_hosts' => array_values(array_filter(array_map(
            'strtolower',
            array_map('trim', explode(',', (string) env('QUARANTINE_ALLOWED_DB_HOSTS', '127.0.0.1,localhost')))
        ))),
        'allowed_db_database' => strtolower(trim((string) env('QUARANTINE_ALLOWED_DB_DATABASE', 'checkoutpay'))),
        'min_payments' => (int) env('QUARANTINE_MIN_PAYMENTS', 0),
        'min_businesses' => (int) env('QUARANTINE_MIN_BUSINESSES', 0),
        'min_admins' => (int) env('QUARANTINE_MIN_ADMINS', 0),
        'required_tables' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('QUARANTINE_REQUIRED_TABLES', 'payments,businesses,admins,settings'))
        ))),
        'unlock_code' => (string) env('QUARANTINE_UNLOCK_CODE', ''),
        'check_interval_seconds' => max(5, (int) env('QUARANTINE_CHECK_INTERVAL_SECONDS', 60)),
        'lock_relative_path' => 'framework/quarantine.lock',
        'baseline_relative_path' => 'app/quarantine-baseline.json',
    ],

];
