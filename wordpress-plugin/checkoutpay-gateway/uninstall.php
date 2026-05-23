<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package CheckoutPay
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('woocommerce_checkoutpay_settings');
