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

];
