<?php
/**
 * Plugin Name:       CheckoutPay Payment Gateway
 * Plugin URI:        https://check-outpay.com/
 * Description:       Accept bank-transfer payments in WooCommerce via CheckoutPay (classic and block checkout).
 * Version:           1.2.4
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            CheckoutPay
 * Author URI:        https://check-outpay.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       checkoutpay-gateway
 * Domain Path:        /languages
 * WC requires at least: 7.0
 * WC tested up to:      9.6
 *
 * @package CheckoutPay
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CHECKOUTPAY_VERSION', '1.2.4');
define('CHECKOUTPAY_PORTAL_URL', 'https://check-outpay.com');
define('CHECKOUTPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHECKOUTPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHECKOUTPAY_PLUGIN_FILE', __FILE__);
define('CHECKOUTPAY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Declare compatibility with WooCommerce features (HPOS, block checkout).
 */
add_action('before_woocommerce_init', function () {
    if (!class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        return;
    }

    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', CHECKOUTPAY_PLUGIN_FILE, true);
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', CHECKOUTPAY_PLUGIN_FILE, true);
});

/**
 * @return bool
 */
function checkoutpay_is_woocommerce_active()
{
    return class_exists('WooCommerce');
}

/**
 * Admin notice when WooCommerce is missing.
 */
function checkoutpay_woocommerce_missing_notice()
{
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('CheckoutPay Payment Gateway requires WooCommerce to be installed and active.', 'checkoutpay-gateway'); ?></p>
    </div>
    <?php
}

/**
 * Plugin action links (Settings only — admin screen).
 *
 * @param array<string> $links Existing links.
 * @return array<string>
 */
function checkoutpay_plugin_action_links($links)
{
    if (!checkoutpay_is_woocommerce_active()) {
        return $links;
    }

    $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=checkoutpay');
    $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'checkoutpay-gateway') . '</a>';

    return array_merge(array($settings_link), $links);
}
add_filter('plugin_action_links_' . CHECKOUTPAY_PLUGIN_BASENAME, 'checkoutpay_plugin_action_links');

add_action('admin_notices', function () {
    if (!checkoutpay_is_woocommerce_active()) {
        checkoutpay_woocommerce_missing_notice();
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
                    __('CheckoutPay is enabled but missing API URL or API Key, so it will not appear at checkout. <a href="%s">Configure CheckoutPay</a>.', 'checkoutpay-gateway'),
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
function checkoutpay_init_gateway()
{
    if (!checkoutpay_is_woocommerce_active() || !class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once CHECKOUTPAY_PLUGIN_DIR . 'includes/class-checkoutpay-gateway.php';

    add_filter('woocommerce_payment_gateways', 'checkoutpay_add_gateway');
}
add_action('plugins_loaded', 'checkoutpay_init_gateway', 20);

/**
 * @param array<int, string> $gateways Gateway class names.
 * @return array<int, string>
 */
function checkoutpay_add_gateway($gateways)
{
    $gateways[] = 'WC_CheckoutPay_Gateway';

    return $gateways;
}

/**
 * Register CheckoutPay for WooCommerce block-based checkout.
 */
function checkoutpay_init_blocks_support()
{
    if (!checkoutpay_is_woocommerce_active()) {
        return;
    }

    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    require_once CHECKOUTPAY_PLUGIN_DIR . 'includes/class-checkoutpay-blocks.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function ($payment_method_registry) {
            $payment_method_registry->register(new WC_CheckoutPay_Blocks());
        }
    );
}
add_action('woocommerce_blocks_loaded', 'checkoutpay_init_blocks_support');

/**
 * Plugin activation.
 */
function checkoutpay_activate()
{
    if (!checkoutpay_is_woocommerce_active()) {
        deactivate_plugins(CHECKOUTPAY_PLUGIN_BASENAME);
        wp_die(
            esc_html__('CheckoutPay Payment Gateway requires WooCommerce. Please install and activate WooCommerce first.', 'checkoutpay-gateway'),
            esc_html__('Plugin Activation Error', 'checkoutpay-gateway'),
            array('back_link' => true)
        );
    }

    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'checkoutpay_activate');

/**
 * Plugin deactivation.
 */
function checkoutpay_deactivate()
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'checkoutpay_deactivate');
