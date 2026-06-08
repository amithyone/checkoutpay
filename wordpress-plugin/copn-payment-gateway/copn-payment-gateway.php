<?php
/**
 * Plugin Name:       COPN Payment Gateway for Nigerian Businesses
 * Plugin URI:        https://check-outpay.com/wordpress-plugin
 * Description:       COPN (CheckoutPay Nigeria) — official bank-transfer payment gateway for Nigerian businesses. Connects your store to CheckoutPay for virtual account checkout, webhooks, and order updates. Requires WooCommerce.
 * Version:           1.4.5
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            CheckoutPay
 * Author URI:        https://check-outpay.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       copn-payment-gateway
 * Domain Path:        /languages
 * WC requires at least: 7.0
 * WC tested up to:      9.6
 *
 * @package COPN
 */

if (!defined('ABSPATH')) {
    exit;
}

define('COPN_VERSION', '1.4.5');
define('COPN_PORTAL_URL', 'https://check-outpay.com');
define('COPN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('COPN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COPN_PLUGIN_FILE', __FILE__);
define('COPN_PLUGIN_BASENAME', plugin_basename(__FILE__));

/** @deprecated 1.4.0 Use COPN_* constants. */
define('CHECKOUTPAY_VERSION', COPN_VERSION);
define('CHECKOUTPAY_PORTAL_URL', COPN_PORTAL_URL);
define('CHECKOUTPAY_PLUGIN_DIR', COPN_PLUGIN_DIR);
define('CHECKOUTPAY_PLUGIN_URL', COPN_PLUGIN_URL);
define('CHECKOUTPAY_PLUGIN_FILE', COPN_PLUGIN_FILE);
define('CHECKOUTPAY_PLUGIN_BASENAME', COPN_PLUGIN_BASENAME);

/**
 * Declare compatibility with WooCommerce features (HPOS, block checkout).
 */
add_action('before_woocommerce_init', function () {
    if (!class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        return;
    }

    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', COPN_PLUGIN_FILE, true);
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', COPN_PLUGIN_FILE, true);
});

/**
 * @return bool
 */
function copn_is_woocommerce_active()
{
    return class_exists('WooCommerce');
}

/** @deprecated 1.4.0 */
function checkoutpay_is_woocommerce_active()
{
    return copn_is_woocommerce_active();
}

/**
 * Admin notice when WooCommerce is missing.
 */
function copn_woocommerce_missing_notice()
{
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('COPN Payment Gateway for Nigerian Businesses requires WooCommerce to be installed and active.', 'copn-payment-gateway'); ?></p>
    </div>
    <?php
}

/**
 * Plugin action links (Settings only — admin screen).
 *
 * @param array<string> $links Existing links.
 * @return array<string>
 */
function copn_plugin_action_links($links)
{
    if (!copn_is_woocommerce_active()) {
        return $links;
    }

    $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=checkoutpay');
    $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'copn-payment-gateway') . '</a>';

    return array_merge(array($settings_link), $links);
}
add_filter('plugin_action_links_' . COPN_PLUGIN_BASENAME, 'copn_plugin_action_links');

add_action('admin_notices', function () {
    if (!copn_is_woocommerce_active()) {
        copn_woocommerce_missing_notice();
        return;
    }

    $settings = get_option('woocommerce_checkoutpay_settings', array());
    if (($settings['enabled'] ?? 'no') !== 'yes') {
        return;
    }

    if (!empty($settings['api_key']) && !empty($settings['api_url'])) {
        return;
    }

    $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=checkoutpay');
    ?>
    <div class="notice notice-warning">
        <p>
            <?php
            echo wp_kses_post(
                sprintf(
                    /* translators: %s: WooCommerce CheckoutPay settings URL */
                    __('CheckoutPay (COPN) is enabled but missing API URL or API Key. <a href="%s">Configure settings</a>.', 'copn-payment-gateway'),
                    esc_url($settings_url)
                )
            );
            ?>
        </p>
    </div>
    <?php
});

/**
 * Load gateway class after WooCommerce payment gateway base is available.
 */
function copn_init_gateway()
{
    if (!copn_is_woocommerce_active() || !class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once COPN_PLUGIN_DIR . 'includes/class-copn-gateway.php';

    add_filter('woocommerce_payment_gateways', 'copn_add_gateway');
}
add_action('plugins_loaded', 'copn_init_gateway', 20);

/**
 * @param array<int, string> $gateways Gateway class names.
 * @return array<int, string>
 */
function copn_add_gateway($gateways)
{
    $gateways[] = 'Copn_Gateway';

    return $gateways;
}

/**
 * Register COPN / CheckoutPay for WooCommerce block-based checkout.
 */
function copn_init_blocks_support()
{
    if (!copn_is_woocommerce_active()) {
        return;
    }

    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    require_once COPN_PLUGIN_DIR . 'includes/class-copn-blocks.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function ($payment_method_registry) {
            $payment_method_registry->register(new Copn_Blocks());
        }
    );
}
add_action('woocommerce_blocks_loaded', 'copn_init_blocks_support');

/**
 * Plugin activation.
 */
function copn_activate()
{
    if (!copn_is_woocommerce_active()) {
        deactivate_plugins(COPN_PLUGIN_BASENAME);
        wp_die(
            esc_html__('COPN Payment Gateway for Nigerian Businesses requires WooCommerce. Please install and activate WooCommerce first.', 'copn-payment-gateway'),
            esc_html__('Plugin Activation Error', 'copn-payment-gateway'),
            array('back_link' => true)
        );
    }

    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'copn_activate');

/**
 * Plugin deactivation.
 */
function copn_deactivate()
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'copn_deactivate');
