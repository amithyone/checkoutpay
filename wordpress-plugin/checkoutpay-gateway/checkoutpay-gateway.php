<?php
/**
 * Plugin Name: CheckoutPay Payment Gateway
 * Plugin URI: https://checkoutpay.com
 * Description: Accept payments via CheckoutPay payment gateway in WooCommerce
 * Version: 1.0.0
 * Author: CheckoutPay
 * Author URI: https://checkoutpay.com
 * Text Domain: checkoutpay-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('CHECKOUTPAY_VERSION', '1.0.0');
define('CHECKOUTPAY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CHECKOUTPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CHECKOUTPAY_PLUGIN_FILE', __FILE__);

/**
 * Check if WooCommerce is active
 */
function checkoutpay_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'checkoutpay_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * WooCommerce missing notice
 */
function checkoutpay_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('CheckoutPay Payment Gateway requires WooCommerce to be installed and active.', 'checkoutpay-gateway'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the gateway
 */
function checkoutpay_init_gateway() {
    if (!checkoutpay_check_woocommerce()) {
        return;
    }

    require_once CHECKOUTPAY_PLUGIN_DIR . 'includes/class-checkoutpay-gateway.php';
    
    add_filter('woocommerce_payment_gateways', 'checkoutpay_add_gateway');
}
add_action('plugins_loaded', 'checkoutpay_init_gateway', 0);

/**
 * Add the gateway to WooCommerce
 */
function checkoutpay_add_gateway($gateways) {
    $gateways[] = 'WC_CheckoutPay_Gateway';
    return $gateways;
}

/**
 * Plugin activation hook
 */
function checkoutpay_activate() {
    // Create necessary database tables or options if needed
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'checkoutpay_activate');

/**
 * Plugin deactivation hook
 */
function checkoutpay_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'checkoutpay_deactivate');
