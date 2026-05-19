<?php
/**
 * WooCommerce Blocks checkout integration for CheckoutPay.
 *
 * @package CheckoutPay
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Registers CheckoutPay on block-based checkout pages.
 */
final class WC_CheckoutPay_Blocks extends AbstractPaymentMethodType
{
    /**
     * Payment method ID (must match WC_CheckoutPay_Gateway::$id).
     *
     * @var string
     */
    protected $name = 'checkoutpay';

    /**
     * @var WC_CheckoutPay_Gateway|null
     */
    private $gateway;

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        $this->settings = get_option('woocommerce_checkoutpay_settings', array());

        if (function_exists('WC') && WC()->payment_gateways) {
            $gateways = WC()->payment_gateways->payment_gateways();
            $this->gateway = isset($gateways['checkoutpay']) ? $gateways['checkoutpay'] : null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function is_active()
    {
        return $this->gateway instanceof WC_CheckoutPay_Gateway && $this->gateway->is_available();
    }

    /**
     * {@inheritdoc}
     */
    public function get_payment_method_script_handles()
    {
        $script_path = CHECKOUTPAY_PLUGIN_DIR . 'assets/js/checkout-blocks.js';
        $script_url = CHECKOUTPAY_PLUGIN_URL . 'assets/js/checkout-blocks.js';

        wp_register_script(
            'wc-checkoutpay-blocks',
            $script_url,
            array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
            ),
            file_exists($script_path) ? (string) filemtime($script_path) : CHECKOUTPAY_VERSION,
            true
        );

        return array('wc-checkoutpay-blocks');
    }

    /**
     * {@inheritdoc}
     */
    public function get_payment_method_data()
    {
        $title = $this->get_setting('title');
        if ($title === '') {
            $title = __('CheckoutPay', 'checkoutpay-gateway');
        }

        $description = $this->get_setting('description');
        if ($description === '') {
            $description = __('Pay securely via bank transfer using CheckoutPay.', 'checkoutpay-gateway');
        }

        return array(
            'title' => $title,
            'description' => $description,
            'supports' => $this->gateway ? array_filter((array) $this->gateway->supports) : array('products'),
        );
    }
}
