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
    // On Namecheap: redirect browsers to Contabo, but keep these paths local (egress relay / APIs / admin).
    'legacy_host_redirect_skip_prefixes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'LEGACY_HOST_REDIRECT_SKIP_PREFIXES',
            '/api/,/cron/,/internal/,/mevon-egress,/enter0'
        ))
    ))),

    /*
    | Contabo → Namecheap merchant webhook egress (IP allowlists that only trust check-outpay.com).
    | Contabo: RELAY_CLIENT_ENABLED=true + RELAY_URL=https://check-outpay.com/api/v1/internal/webhook-egress
    | Namecheap: RELAY_RECEIVER_ENABLED=true + same RELAY_SECRET
    | After DNS cutover: turn client+receiver off.
    */
    'webhook_egress' => [
        'relay_client_enabled' => filter_var(env('WEBHOOK_EGRESS_RELAY_CLIENT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'relay_receiver_enabled' => filter_var(env('WEBHOOK_EGRESS_RELAY_RECEIVER_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'relay_url' => (string) env('WEBHOOK_EGRESS_RELAY_URL', 'https://check-outpay.com/api/v1/internal/webhook-egress'),
        'relay_secret' => (string) env('WEBHOOK_EGRESS_RELAY_SECRET', ''),
        'fallback_direct' => filter_var(env('WEBHOOK_EGRESS_FALLBACK_DIRECT', true), FILTER_VALIDATE_BOOLEAN),
        'allowed_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('WEBHOOK_EGRESS_ALLOWED_IPS', ''))))),
        'user_agent' => (string) env('WEBHOOK_EGRESS_USER_AGENT', 'CheckoutPay-Webhook/1.0 (+https://check-outpay.com)'),
    ],

    /*
    | Namecheap → Contabo API proxy (payment-request / account numbers land on Contabo DB).
    | Enable only on Namecheap. Skip webhook-egress so outbound still leaves from check-outpay.com.
    */
    'api_proxy' => [
        'enabled' => filter_var(env('API_PROXY_TO_CONTABO_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'to_base_url' => rtrim((string) env('API_PROXY_TO_CONTABO_URL', 'https://check-outnow.com'), '/'),
        'timeout_seconds' => max(5, (int) env('API_PROXY_TO_CONTABO_TIMEOUT', 25)),
        'fallback_local' => filter_var(env('API_PROXY_FALLBACK_LOCAL', false), FILTER_VALIDATE_BOOLEAN),
        'skip_prefixes' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'API_PROXY_SKIP_PREFIXES',
                '/api/v1/internal/webhook-egress,/api/v1/sync/live'
            ))
        ))),
    ],

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
