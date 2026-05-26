<?php

namespace App\Support;

/**
 * Single source of truth for the CheckoutPay WooCommerce plugin download metadata.
 */
class CheckoutPayWordPressPlugin
{
    public static function version(): string
    {
        return (string) config('checkout.wordpress_plugin.version', '1.1.1');
    }

    public static function zipPath(): string
    {
        return (string) config('checkout.wordpress_plugin.zip', 'downloads/checkoutpay-gateway.zip');
    }

    public static function slug(): string
    {
        return (string) config('checkout.wordpress_plugin.slug', 'checkoutpay-gateway');
    }

    /**
     * Public download URL with version query string (cache busting).
     */
    public static function downloadUrl(): string
    {
        return asset(static::zipPath()).'?v='.rawurlencode(static::version());
    }

    public static function requiresWordPress(): string
    {
        return (string) config('checkout.wordpress_plugin.requires_wordpress', '5.8');
    }

    public static function requiresWooCommerce(): string
    {
        return (string) config('checkout.wordpress_plugin.requires_woocommerce', '7.0');
    }

    public static function requirementsLabel(): string
    {
        return sprintf(
            'WordPress %s+, WooCommerce %s+',
            static::requiresWordPress(),
            static::requiresWooCommerce()
        );
    }

    public static function versionLine(): string
    {
        return 'Version '.static::version().' | Requires '.static::requirementsLabel();
    }

    /**
     * Public plugin documentation page (Plugin URI for WordPress.org headers).
     */
    public static function pageUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/wordpress-plugin';
    }
}
