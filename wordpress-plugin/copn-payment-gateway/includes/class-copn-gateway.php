<?php
/**
 * COPN Payment Gateway for Nigerian Businesses
 *
 * @package COPN
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Copn_Gateway — WooCommerce payment gateway for CheckoutPay.
 */
class Copn_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'checkoutpay';
        $icon_path = COPN_PLUGIN_DIR . 'assets/images/checkoutpay-logo.png';
        $this->icon = file_exists($icon_path) ? COPN_PLUGIN_URL . 'assets/images/checkoutpay-logo.png' : '';
        $this->has_fields = false;
        $this->method_title = __('CheckoutPay', 'copn-payment-gateway');
        $this->method_description = __('Accept bank-transfer payments via CheckoutPay. Customers pay to a virtual account; orders update automatically when payment is confirmed.', 'copn-payment-gateway');
        $this->supports = array('products');

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->api_key = $this->get_option('api_key');
        $this->api_url = $this->get_option('api_url');
        if (empty($this->api_url)) {
            $this->api_url = 'https://check-outpay.com/api/v1';
        }
        $this->test_mode = $this->get_option('test_mode');
        $this->developer_program_partner_business_id = $this->get_option('developer_program_partner_business_id');
        $this->auto_complete_orders = $this->get_option('auto_complete_orders');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_checkoutpay_gateway', array($this, 'check_payment_status'));
        add_action('woocommerce_api_wc_checkoutpay_update_amount', array($this, 'update_payment_amount'));
        add_action('woocommerce_api_wc_checkoutpay_webhook', array($this, 'handle_webhook'));
        
        // Payment listener/API hook
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_thankyou_scripts'));
    }

    /**
     * Enqueue admin scripts on CheckoutPay WooCommerce settings section.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public function enqueue_admin_scripts($hook_suffix) {
        if ('woocommerce_page_wc-settings' !== $hook_suffix) {
            return;
        }

        $tab = sanitize_key((string) filter_input(INPUT_GET, 'tab', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $section = sanitize_key((string) filter_input(INPUT_GET, 'section', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

        if ('checkout' !== $tab || 'checkoutpay' !== $section) {
            return;
        }

        $version = defined('COPN_VERSION') ? COPN_VERSION : '1.3.4';
        $base_url = defined('COPN_PLUGIN_URL') ? COPN_PLUGIN_URL : '';

        wp_enqueue_script(
            'copn-admin-settings',
            $base_url . 'assets/js/admin-settings.js',
            array(),
            $version,
            true
        );

        wp_localize_script(
            'copn-admin-settings',
            'copnAdminSettings',
            array(
                'copiedLabel' => __('Copied!', 'copn-payment-gateway'),
                'pairs' => array(
                    array(
                        'inputId' => 'copn-webhook-url',
                        'buttonId' => 'copn-copy-webhook-url',
                    ),
                    array(
                        'inputId' => 'copn-website-url',
                        'buttonId' => 'copn-copy-website-url',
                    ),
                ),
            )
        );

        wp_enqueue_script(
            'copn-admin-charges',
            $base_url . 'assets/js/admin-charges.js',
            array(),
            $version,
            true
        );

        wp_localize_script(
            'copn-admin-charges',
            'copnAdminCharges',
            array(
                'websiteUrl' => $this->get_store_website_url(),
                'webhookUrl' => $this->get_webhook_url(),
                'portalUrl' => $this->get_copn_portal_url(),
                'dashboardWebsitesUrl' => $this->get_copn_dashboard_websites_url(),
                'sampleAmount' => 10000,
                'i18n' => array(
                    'unableToLoad' => __('Unable to load charges', 'copn-payment-gateway'),
                    'noCharges' => __('No charges apply (disabled or exempt)', 'copn-payment-gateway'),
                    'matchedWebsite' => __('Matched website', 'copn-payment-gateway'),
                    'feeStructure' => __('Fee structure', 'copn-payment-gateway'),
                    'whoPaysFees' => __('Who pays fees', 'copn-payment-gateway'),
                    'sampleOrder' => __('Sample order', 'copn-payment-gateway'),
                    'feesOnSample' => __('Fees on sample', 'copn-payment-gateway'),
                    'customerTransfers' => __('Customer transfers', 'copn-payment-gateway'),
                    'youReceive' => __('You receive', 'copn-payment-gateway'),
                    'openSettings' => __('Open CheckoutPay website settings', 'copn-payment-gateway'),
                    'checkoutpayHome' => __('CheckoutPay home', 'copn-payment-gateway'),
                    'apiRequired' => __('API URL and API Key are required.', 'copn-payment-gateway'),
                    'networkError' => __('Network error', 'copn-payment-gateway'),
                ),
            )
        );
    }

    /**
     * Enqueue thank-you page scripts when the order used CheckoutPay.
     */
    public function maybe_enqueue_thankyou_scripts() {
        if (!function_exists('is_order_received_page') || !is_order_received_page()) {
            return;
        }

        global $wp;
        $order_id = isset($wp->query_vars['order-received']) ? absint($wp->query_vars['order-received']) : 0;
        if ($order_id <= 0) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        if (!$order->get_meta('_checkoutpay_transaction_id')) {
            return;
        }

        $version = defined('COPN_VERSION') ? COPN_VERSION : '1.3.4';
        $base_url = defined('COPN_PLUGIN_URL') ? COPN_PLUGIN_URL : '';

        wp_enqueue_script('jquery');
        wp_enqueue_script(
            'copn-thankyou-payment',
            $base_url . 'assets/js/thankyou-payment.js',
            array('jquery'),
            $version,
            true
        );

        wp_localize_script(
            'copn-thankyou-payment',
            'copnThankyou',
            array(
                'orderId' => $order_id,
                'nonce' => wp_create_nonce($this->thankyou_nonce_action($order_id)),
                'checkStatusUrl' => esc_url(add_query_arg('wc-api', 'wc_checkoutpay_gateway', home_url('/'))),
                'updateAmountUrl' => esc_url(add_query_arg('wc-api', 'wc_checkoutpay_update_amount', home_url('/'))),
                'i18n' => array(
                    'checking' => __('Checking...', 'copn-payment-gateway'),
                    'checkStatus' => __('Check Payment Status', 'copn-payment-gateway'),
                    'stillPending' => __('Payment is still pending. Please check your email for payment instructions.', 'copn-payment-gateway'),
                    'checkError' => __('Unable to check payment status. Please try again later.', 'copn-payment-gateway'),
                    'enterAmount' => __('Please enter the amount you paid.', 'copn-payment-gateway'),
                    'updating' => __('Updating...', 'copn-payment-gateway'),
                    'updateAmount' => __('Update amount & check status', 'copn-payment-gateway'),
                    'amountUpdated' => __('Amount updated. You can check status again.', 'copn-payment-gateway'),
                    'updateFailed' => __('Update failed. Please try again.', 'copn-payment-gateway'),
                    'updateError' => __('Unable to update. Please try again.', 'copn-payment-gateway'),
                ),
            )
        );
    }

    /**
     * Sanitize a monetary amount from external input.
     *
     * @param mixed $value Raw value.
     * @return float
     */
    private function sanitize_received_amount($value) {
        if (!is_numeric($value)) {
            return 0.0;
        }

        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        $amount = (float) wc_format_decimal($value, $decimals);

        return max(0.0, $amount);
    }

    /**
     * Sanitize charges array from API/webhook JSON (whitelist keys).
     *
     * @param mixed $charges Raw charges value.
     * @return array<string, mixed>
     */
    private function sanitize_charges_array($charges) {
        if (!is_array($charges)) {
            return array();
        }

        $float_keys = array(
            'total',
            'amount_to_pay',
            'business_receives',
            'percentage',
            'fixed',
            'charge_percentage',
            'charge_fixed',
            'sample_amount',
        );
        $bool_keys = array('paid_by_customer', 'charges_enabled', 'charge_exempt');
        $string_keys = array('paid_by_label', 'dashboard_note');
        $sanitized = array();

        foreach ($charges as $key => $value) {
            $key = sanitize_key((string) $key);

            if ('sample' === $key && is_array($value)) {
                $sanitized['sample'] = $this->sanitize_charges_array($value);
                continue;
            }

            if (in_array($key, $float_keys, true) && is_numeric($value)) {
                $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
                $sanitized[$key] = (float) wc_format_decimal($value, $decimals);
            } elseif (in_array($key, $bool_keys, true)) {
                $sanitized[$key] = (bool) $value;
            } elseif (in_array($key, $string_keys, true)) {
                $sanitized[$key] = sanitize_text_field((string) $value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize webhook JSON body after json_decode (whitelist known keys).
     *
     * @param array<string, mixed> $data Decoded webhook payload.
     * @return array<string, mixed>
     */
    private function sanitize_webhook_payload(array $data) {
        $out = array();

        if (isset($data['transaction_id'])) {
            $out['transaction_id'] = sanitize_text_field((string) $data['transaction_id']);
        }
        if (isset($data['status'])) {
            $out['status'] = sanitize_text_field((string) $data['status']);
        }
        if (isset($data['event'])) {
            $out['event'] = sanitize_text_field((string) $data['event']);
        }
        if (isset($data['signature'])) {
            $out['signature'] = sanitize_text_field((string) $data['signature']);
        }
        if (isset($data['mismatch_reason'])) {
            $out['mismatch_reason'] = sanitize_text_field((string) $data['mismatch_reason']);
        }
        if (isset($data['reason'])) {
            $out['reason'] = sanitize_text_field((string) $data['reason']);
        }
        if (isset($data['amount']) && is_numeric($data['amount'])) {
            $out['amount'] = $this->sanitize_received_amount($data['amount']);
        }
        if (isset($data['received_amount'])) {
            $out['received_amount'] = $this->sanitize_received_amount($data['received_amount']);
        }
        if (isset($data['charges'])) {
            $out['charges'] = $this->sanitize_charges_array($data['charges']);
        }

        return $out;
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'copn-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable CheckoutPay', 'copn-payment-gateway'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'copn-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'copn-payment-gateway'),
                'default' => __('CheckoutPay', 'copn-payment-gateway'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'copn-payment-gateway'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'copn-payment-gateway'),
                'default' => __('Pay securely via CheckoutPay. You will receive payment instructions via email.', 'copn-payment-gateway'),
                'desc_tip' => true,
            ),
            'api_url' => array(
                'title' => __('API URL', 'copn-payment-gateway'),
                'type' => 'text',
                'description' => __('Enter your CheckoutPay API URL (e.g., https://check-outpay.com/api/v1)', 'copn-payment-gateway'),
                'default' => 'https://check-outpay.com/api/v1',
                'desc_tip' => true,
            ),
            'api_key' => array(
                'title' => __('API Key', 'copn-payment-gateway'),
                'type' => 'password',
                'description' => __('Enter your CheckoutPay API Key. You can find this in your CheckoutPay dashboard.', 'copn-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ),
            'copn_webhook_url' => array(
                'title' => __('Webhook URL (for CheckoutPay)', 'copn-payment-gateway'),
                'type' => 'copn_webhook_url',
                'description' => __('Copy this exact URL into your CheckoutPay business website webhook settings. The plugin sends it automatically on each order; it must match what you save in CheckoutPay.', 'copn-payment-gateway'),
            ),
            'copn_charges_info' => array(
                'title' => __('Charges (from CheckoutPay)', 'copn-payment-gateway'),
                'type' => 'copn_charges_info',
                'description' => __('Live fee rules for this store, as configured on your CheckoutPay business website. Refresh after changing settings in CheckoutPay.', 'copn-payment-gateway'),
            ),
            'auto_complete_orders' => array(
                'title' => __('Order status on payment', 'copn-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Mark orders as Completed when payment is approved', 'copn-payment-gateway'),
                'default' => 'no',
                'description' => __('Default WooCommerce behavior is Processing for physical goods. Enable to set Completed immediately when CheckoutPay confirms payment.', 'copn-payment-gateway'),
            ),
            'split_payment_notice' => array(
                'title' => __('Split payment', 'copn-payment-gateway'),
                'type' => 'title',
                'description' => __('Installment and split payments are configured in your CheckoutPay dashboard (business websites and invoices), not in this plugin. WooCommerce orders use a single bank transfer per checkout.', 'copn-payment-gateway'),
            ),
            'test_mode' => array(
                'title' => __('Test Mode', 'copn-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'copn-payment-gateway'),
                'default' => 'no',
                'description' => __('Enable test mode to use test API credentials.', 'copn-payment-gateway'),
            ),
            'developer_program_partner_business_id' => array(
                'title' => __('Developer program partner ID', 'copn-payment-gateway'),
                'type' => 'text',
                'description' => __('Optional. CheckoutPay Business ID of an approved developer partner (sent as developer_program_partner_business_id on payment-request).', 'copn-payment-gateway'),
                'default' => '',
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __('Instructions', 'copn-payment-gateway'),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page.', 'copn-payment-gateway'),
                'default' => __('Please check your email for payment instructions. Complete the payment to confirm your order.', 'copn-payment-gateway'),
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Webhook URL CheckoutPay should POST to when a payment is approved.
     *
     * @return string
     */
    public function get_webhook_url() {
        return add_query_arg('wc-api', 'wc_checkoutpay_webhook', home_url('/'));
    }

    /**
     * Store URL sent to CheckoutPay for website matching.
     *
     * @return string
     */
    public function get_store_website_url() {
        return home_url('/');
    }

    /**
     * CheckoutPay platform URL (merchant dashboard).
     *
     * @return string
     */
    public function get_copn_portal_url() {
        $url = defined('COPN_PORTAL_URL') ? COPN_PORTAL_URL : 'https://check-outpay.com';

        return untrailingslashit($url);
    }

    /**
     * Business websites settings on CheckoutPay.
     *
     * @return string
     */
    public function get_copn_dashboard_websites_url() {
        return $this->get_copn_portal_url() . '/dashboard/websites';
    }

    /**
     * Render read-only webhook URL with copy button (WooCommerce settings).
     *
     * @param string $key  Field key.
     * @param array  $data Field definition.
     * @return string
     */
    public function generate_copn_webhook_url_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $webhook_url = $this->get_webhook_url();
        $website_url = $this->get_store_website_url();
        $portal_url = $this->get_copn_portal_url();
        $dashboard_websites_url = $this->get_copn_dashboard_websites_url();
        $defaults = array(
            'title' => '',
            'description' => '',
        );
        $data = wp_parse_args($data, $defaults);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <p class="description" style="margin-bottom: 8px;">
                        <?php echo wp_kses_post($data['description']); ?>
                    </p>
                    <p style="margin: 0 0 6px;"><strong><?php esc_html_e('CheckoutPay site URL', 'copn-payment-gateway'); ?></strong></p>
                    <p style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; max-width: 42rem; margin-bottom: 12px;">
                        <input
                            type="text"
                            readonly
                            class="large-text code"
                            id="copn-portal-url"
                            value="<?php echo esc_attr($portal_url); ?>/"
                            style="flex: 1 1 280px;"
                        />
                        <a href="<?php echo esc_url($dashboard_websites_url); ?>" class="button" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Open website settings', 'copn-payment-gateway'); ?>
                        </a>
                    </p>
                    <p class="description" style="margin: 0 0 12px;">
                        <?php esc_html_e('Register your WooCommerce store URL below in CheckoutPay → Dashboard → Websites.', 'copn-payment-gateway'); ?>
                    </p>
                    <p style="margin: 0 0 6px;"><strong><?php esc_html_e('Webhook URL', 'copn-payment-gateway'); ?></strong></p>
                    <p style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; max-width: 42rem;">
                        <input
                            type="text"
                            readonly
                            class="large-text code"
                            id="copn-webhook-url"
                            value="<?php echo esc_attr($webhook_url); ?>"
                            style="flex: 1 1 280px;"
                        />
                        <button type="button" class="button" id="copn-copy-webhook-url">
                            <?php esc_html_e('Copy webhook URL', 'copn-payment-gateway'); ?>
                        </button>
                    </p>
                    <p class="description" style="margin: 8px 0 12px;">
                        <?php esc_html_e('CheckoutPay → Business websites → paste into Webhook URL for this store domain.', 'copn-payment-gateway'); ?>
                    </p>
                    <p style="margin: 0 0 6px;"><strong><?php esc_html_e('Website URL (also sent to CheckoutPay)', 'copn-payment-gateway'); ?></strong></p>
                    <p style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; max-width: 42rem;">
                        <input
                            type="text"
                            readonly
                            class="large-text code"
                            id="copn-website-url"
                            value="<?php echo esc_attr($website_url); ?>"
                            style="flex: 1 1 280px;"
                        />
                        <button type="button" class="button" id="copn-copy-website-url">
                            <?php esc_html_e('Copy website URL', 'copn-payment-gateway'); ?>
                        </button>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Use the same domain in CheckoutPay when you register or approve this WooCommerce site.', 'copn-payment-gateway'); ?>
                    </p>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Render read-only charge settings loaded from CheckoutPay API.
     *
     * @param string $key  Field key.
     * @param array  $data Field definition.
     * @return string
     */
    public function generate_copn_charges_info_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'description' => '',
        );
        $data = wp_parse_args($data, $defaults);
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp" id="<?php echo esc_attr($field_key); ?>">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <p class="description" style="margin-bottom: 10px;">
                        <?php echo wp_kses_post($data['description']); ?>
                    </p>
                    <p>
                        <button type="button" class="button" id="copn-refresh-charges">
                            <?php esc_html_e('Refresh charges', 'copn-payment-gateway'); ?>
                        </button>
                        <span id="copn-charges-loading" style="display:none; margin-left: 8px;">
                            <?php esc_html_e('Loading…', 'copn-payment-gateway'); ?>
                        </span>
                    </p>
                    <div id="copn-charges-panel" style="margin-top: 12px; max-width: 40rem; padding: 12px 14px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px;">
                        <p class="description" style="margin: 0;">
                            <?php esc_html_e('Save your API URL and API Key above, then click Refresh charges.', 'copn-payment-gateway'); ?>
                        </p>
                    </div>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Mark a WooCommerce order paid after CheckoutPay approval.
     *
     * @param WC_Order $order Order.
     * @param string   $note  Order note.
     * @param string   $transaction_id Optional transaction id for payment_complete.
     */
    private function mark_order_paid($order, $note, $transaction_id = '') {
        $txn = $transaction_id !== '' ? $transaction_id : (string) $order->get_meta('_checkoutpay_transaction_id');

        if ('yes' === $this->get_option('auto_complete_orders')) {
            $order->payment_complete($txn !== '' ? $txn : null);
            $order->update_status('completed', $note);
        } else {
            $order->payment_complete($txn !== '' ? $txn : null);
            $order->add_order_note($note);
        }

        $order->update_meta_data('_checkoutpay_status', 'approved');
        $order->save();
    }

    /**
     * Whether this gateway is available at checkout.
     *
     * @return bool
     */
    public function is_available() {
        if ('yes' !== $this->enabled) {
            return false;
        }

        if (is_admin() && !wp_doing_ajax()) {
            return parent::is_available();
        }

        if (empty($this->api_key) || empty($this->api_url)) {
            return false;
        }

        return parent::is_available();
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id Order ID
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }

        // Get original order amount
        $original_amount = $order->get_total();

        // Get customer name
        $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (empty($customer_name)) {
            $customer_name = $order->get_billing_email();
        }

        // Create payment request (charges will be calculated on server side)
        $payment_data = array(
            'name' => $customer_name,
            'amount' => $original_amount,
            'service' => 'WC-' . $order_id,
            'webhook_url' => $this->get_webhook_url(),
            'website_url' => $this->get_store_website_url(),
        );

        $partner_id = absint($this->developer_program_partner_business_id);
        if ($partner_id > 0) {
            $payment_data['developer_program_partner_business_id'] = $partner_id;
        }

        // Make API request
        $response = $this->make_api_request('payment-request', $payment_data);

        if (is_wp_error($response)) {
            wc_add_notice(__('Payment error: ', 'copn-payment-gateway') . $response->get_error_message(), 'error');
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }

        $response_data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_data['success']) && $response_data['success'] && isset($response_data['data'])) {
            $payment_data = $response_data['data'];
            
            // Get charges from API response
            $charges_data = isset($payment_data['charges']) && is_array($payment_data['charges'])
                ? $this->sanitize_charges_array($payment_data['charges'])
                : $this->calculateCharges($original_amount);

            $account_name = '';
            if (isset($payment_data['account_name'])) {
                $account_name = sanitize_text_field((string) $payment_data['account_name']);
            } elseif (isset($payment_data['account_details']['account_name'])) {
                $account_name = sanitize_text_field((string) $payment_data['account_details']['account_name']);
            }

            $bank_name = '';
            if (isset($payment_data['bank_name'])) {
                $bank_name = sanitize_text_field((string) $payment_data['bank_name']);
            } elseif (isset($payment_data['account_details']['bank_name'])) {
                $bank_name = sanitize_text_field((string) $payment_data['account_details']['bank_name']);
            }

            // Store payment information in order meta
            $order->update_meta_data('_checkoutpay_transaction_id', sanitize_text_field((string) $payment_data['transaction_id']));
            $order->update_meta_data('_checkoutpay_account_number', isset($payment_data['account_number']) ? sanitize_text_field((string) $payment_data['account_number']) : '');
            $order->update_meta_data('_checkoutpay_account_name', $account_name);
            $order->update_meta_data('_checkoutpay_bank_name', $bank_name);
            $order->update_meta_data('_checkoutpay_status', 'pending');
            $order->update_meta_data('_checkoutpay_charges', $charges_data);
            $order->update_meta_data('_checkoutpay_original_amount', $original_amount);
            $order->update_meta_data('_checkoutpay_expires_at', isset($payment_data['expires_at']) ? sanitize_text_field((string) $payment_data['expires_at']) : '');
            
            // Update order total if customer pays charges
            if (isset($charges_data['paid_by_customer']) && $charges_data['paid_by_customer'] && isset($charges_data['amount_to_pay'])) {
                $order->set_total($charges_data['amount_to_pay']);
            }
            
            $order->save();

            // Mark order as pending payment
            $order->update_status('pending', __('Awaiting CheckoutPay payment', 'copn-payment-gateway'));

            // Return success
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : __('Payment request failed. Please try again.', 'copn-payment-gateway');
            wc_add_notice(__('Payment error: ', 'copn-payment-gateway') . $error_message, 'error');
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array|WP_Error
     */
    private function make_api_request($endpoint, $data = array(), $method = 'POST') {
        $api_url = trailingslashit($this->api_url) . $endpoint;
        
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->api_key,
            ),
        );

        if ($method === 'POST' && !empty($data)) {
            $args['body'] = json_encode($data);
        }

        return wp_remote_request($api_url, $args);
    }

    /**
     * Nonce action for thank-you page AJAX (status check / amount update).
     *
     * @param int $order_id Order ID.
     * @return string
     */
    private function thankyou_nonce_action($order_id) {
        return 'checkoutpay_thankyou_' . absint($order_id);
    }

    /**
     * Verify thank-you page request nonce and that the order uses CheckoutPay.
     *
     * @param int    $order_id Order ID.
     * @param string $nonce    Nonce value.
     * @return WC_Order|WP_Error
     */
    private function verify_thankyou_request($order_id, $nonce) {
        if ($order_id < 1) {
            return new WP_Error('invalid_order', __('Invalid order ID.', 'copn-payment-gateway'));
        }

        if (!wp_verify_nonce($nonce, $this->thankyou_nonce_action($order_id))) {
            return new WP_Error('invalid_nonce', __('Security check failed. Please refresh the page and try again.', 'copn-payment-gateway'));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', __('Order not found.', 'copn-payment-gateway'));
        }

        if ($order->get_payment_method() !== $this->id) {
            return new WP_Error('invalid_gateway', __('Invalid payment method for this order.', 'copn-payment-gateway'));
        }

        return $order;
    }

    /**
     * Check payment status
     */
    public function check_payment_status() {
        $order_id = absint(filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_NUMBER_INT));
        $nonce = sanitize_text_field((string) filter_input(INPUT_GET, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

        $order = $this->verify_thankyou_request($order_id, $nonce);
        if (is_wp_error($order)) {
            wp_send_json_error(array('message' => $order->get_error_message()));
            return;
        }

        $transaction_id = $order->get_meta('_checkoutpay_transaction_id');

        if (!$transaction_id) {
            wp_send_json_error(array('message' => 'Transaction ID not found'));
            return;
        }

        // Check payment status via API: GET /api/v1/payment/{transactionId}
        $api_url = trailingslashit($this->api_url) . 'payment/' . $transaction_id;

        $response = wp_remote_get($api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->api_key,
            ),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $response_data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_data['success']) && $response_data['success'] && isset($response_data['data']['status'])) {
            $status = $response_data['data']['status'];

            if ($status === 'approved') {
                $this->mark_order_paid($order, __('Payment confirmed via CheckoutPay', 'copn-payment-gateway'), $transaction_id);
                wp_send_json_success(array('status' => 'completed'));
            } else {
                wp_send_json_success(array('status' => $status));
            }
        } else {
            wp_send_json_error(array('message' => isset($response_data['message']) ? $response_data['message'] : __('Unable to retrieve payment status', 'copn-payment-gateway')));
        }
    }

    /**
     * Update payment amount (correct wrong amount) then check status
     * Called via AJAX when customer says they paid a different amount.
     */
    public function update_payment_amount() {
        $order_id = absint(filter_input(INPUT_POST, 'order_id', FILTER_SANITIZE_NUMBER_INT));
        $new_amount = (float) filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $nonce = sanitize_text_field((string) filter_input(INPUT_POST, 'nonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

        if ($new_amount <= 0) {
            wp_send_json_error(array('message' => __('Invalid order or amount.', 'copn-payment-gateway')));
            return;
        }

        $order = $this->verify_thankyou_request($order_id, $nonce);
        if (is_wp_error($order)) {
            wp_send_json_error(array('message' => $order->get_error_message()));
            return;
        }

        $transaction_id = $order->get_meta('_checkoutpay_transaction_id');
        if (!$transaction_id) {
            wp_send_json_error(array('message' => __('Transaction ID not found.', 'copn-payment-gateway')));
            return;
        }

        // PATCH /api/v1/payment/{transactionId}/amount
        $api_url = trailingslashit($this->api_url) . 'payment/' . $transaction_id . '/amount';
        $response = wp_remote_request($api_url, array(
            'method' => 'PATCH',
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->api_key,
            ),
            'body' => json_encode(array('new_amount' => $new_amount)),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['success'])) {
            $msg = isset($body['message']) ? $body['message'] : __('Failed to update amount.', 'copn-payment-gateway');
            wp_send_json_error(array('message' => $msg));
            return;
        }

        // Update order meta from response if present
        if (!empty($body['data'])) {
            $data = $body['data'];
            if (isset($data['amount'])) {
                $order->update_meta_data('_checkoutpay_original_amount', $this->sanitize_received_amount($data['amount']));
            }
            if (isset($data['charges'])) {
                $order->update_meta_data('_checkoutpay_charges', $this->sanitize_charges_array($data['charges']));
            }
            $order->save();
        }

        // Now check status (GET) and return same shape as check_payment_status
        $get_url = trailingslashit($this->api_url) . 'payment/' . $transaction_id;
        $get_response = wp_remote_get($get_url, array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->api_key,
            ),
        ));

        if (!is_wp_error($get_response)) {
            $get_data = json_decode(wp_remote_retrieve_body($get_response), true);
            if (isset($get_data['success']) && $get_data['success'] && isset($get_data['data']['status'])) {
                $status = $get_data['data']['status'];
                if ($status === 'approved') {
                    $this->mark_order_paid($order, __('Payment confirmed via CheckoutPay (after amount correction)', 'copn-payment-gateway'), $transaction_id);
                    wp_send_json_success(array('status' => 'completed', 'message' => __('Amount updated and payment confirmed!', 'copn-payment-gateway')));
                }
                wp_send_json_success(array('status' => $status, 'message' => __('Amount updated. Payment is still pending.', 'copn-payment-gateway')));
            }
        }

        wp_send_json_success(array('status' => 'pending', 'message' => __('Amount updated. You can check status again in a moment.', 'copn-payment-gateway')));
    }

    /**
     * Handle webhook callback
     * Expects POST body: event, transaction_id, status, amount, received_amount, charges, etc.
     */
    public function handle_webhook() {
        $payload = file_get_contents('php://input');
        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            status_header(400);
            exit;
        }

        $data = $this->sanitize_webhook_payload($decoded);

        // Accept transaction_id and either status or event (API sends event + transaction_id + status)
        $transaction_id = isset($data['transaction_id']) ? $data['transaction_id'] : '';
        $status = isset($data['status']) ? $data['status'] : '';
        $event = isset($data['event']) ? $data['event'] : '';

        if (empty($transaction_id)) {
            status_header(400);
            exit;
        }
        if (empty($status) && empty($event)) {
            status_header(400);
            exit;
        }
        if (empty($status) && $event === 'payment.approved') {
            $status = 'approved';
        }

        // Single order lookup by payment meta (webhook payload is server-to-server).
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        $orders = wc_get_orders(array(
            'limit' => 1,
            'meta_key' => '_checkoutpay_transaction_id',
            'meta_value' => $transaction_id,
        ));
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

        if (empty($orders)) {
            status_header(404);
            exit;
        }

        $order = $orders[0];
        if (empty($status)) {
            $status = 'pending';
        }

        // Verify webhook signature if available
        if (isset($data['signature'])) {
            // Add signature verification here if your API provides it
        }

        // Update order status based on webhook event
        if ($status === 'approved' || $event === 'payment.approved') {
            if (isset($data['received_amount'])) {
                $order->update_meta_data('_checkoutpay_received_amount', $this->sanitize_received_amount($data['received_amount']));
            }
            if (isset($data['charges'])) {
                $order->update_meta_data('_checkoutpay_charges', $this->sanitize_charges_array($data['charges']));
            }
            $this->mark_order_paid($order, __('Payment confirmed via CheckoutPay webhook', 'copn-payment-gateway'), $transaction_id);
            status_header(200);
            wp_send_json(array('success' => true));
        } elseif ($status === 'rejected' || $status === 'failed' || $event === 'payment.rejected') {
            $reason = isset($data['mismatch_reason']) ? sanitize_text_field($data['mismatch_reason']) : (isset($data['reason']) ? sanitize_text_field($data['reason']) : __('Payment rejected', 'copn-payment-gateway'));
            $order->update_status('failed', __('Payment rejected via CheckoutPay: ', 'copn-payment-gateway') . $reason);
            $order->update_meta_data('_checkoutpay_status', 'rejected');
            $order->save();
            status_header(200);
            wp_send_json(array('success' => true));
        } else {
            $order->update_meta_data('_checkoutpay_status', $status);
            $order->save();
            status_header(200);
            wp_send_json(array('success' => true));
        }
    }

    /**
     * Calculate charges for an amount (fallback method)
     *
     * @param float $amount
     * @return array
     */
    private function calculateCharges($amount) {
        // Default charges: 1% + 100 (fallback if API doesn't provide)
        $percentage = 1.0;
        $fixed = 100.0;
        $paid_by_customer = false; // Default: business pays
        
        $percentage_charge = ($amount * $percentage) / 100;
        $total_charges = $percentage_charge + $fixed;
        
        return array(
            'percentage' => round($percentage_charge, 2),
            'fixed' => $fixed,
            'total' => round($total_charges, 2),
            'amount_to_pay' => $paid_by_customer ? round($amount + $total_charges, 2) : $amount,
            'business_receives' => $paid_by_customer ? $amount : round($amount - $total_charges, 2),
            'paid_by_customer' => $paid_by_customer,
        );
    }

    /**
     * Output for the order received page
     */
    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        $transaction_id = $order->get_meta('_checkoutpay_transaction_id');
        $account_number = $order->get_meta('_checkoutpay_account_number');
        $account_name = $order->get_meta('_checkoutpay_account_name');
        $bank_name = $order->get_meta('_checkoutpay_bank_name');
        $status = $order->get_meta('_checkoutpay_status');

        if ($order->get_status() === 'processing' || $order->get_status() === 'completed') {
            echo '<div class="woocommerce-message">' . esc_html__('Payment confirmed. Thank you for your order!', 'copn-payment-gateway') . '</div>';
        } else {
            $charges = $order->get_meta('_checkoutpay_charges');
            $original_amount = $order->get_meta('_checkoutpay_original_amount');
            $display_amount = $original_amount ? $original_amount : $order->get_total();

            echo '<div class="woocommerce-info">';
            echo '<p>' . esc_html($this->get_option('instructions')) . '</p>';

            if ($transaction_id) {
                echo '<p><strong>' . esc_html__('Transaction ID:', 'copn-payment-gateway') . '</strong> ' . esc_html($transaction_id) . '</p>';
            }

            if ($account_number && $account_name && $bank_name) {
                echo '<div class="checkoutpay-account-details" style="margin-top: 15px; padding: 15px; background: #e8f4f8; border-left: 4px solid #0073aa; border-radius: 5px;">';
                echo '<p><strong>' . esc_html__('Payment Instructions:', 'copn-payment-gateway') . '</strong></p>';
                echo '<p><strong>' . esc_html__('Account Number:', 'copn-payment-gateway') . '</strong> ' . esc_html($account_number) . '</p>';
                echo '<p><strong>' . esc_html__('Account Name:', 'copn-payment-gateway') . '</strong> ' . esc_html($account_name) . '</p>';
                echo '<p><strong>' . esc_html__('Bank Name:', 'copn-payment-gateway') . '</strong> ' . esc_html($bank_name) . '</p>';
                echo '</div>';
            }

            if ($charges && is_array($charges)) {
                echo '<div class="checkoutpay-charges-info" style="margin-top: 15px; padding: 15px; background: #f0f0f0; border-radius: 5px;">';
                echo '<p><strong>' . esc_html__('Payment Details:', 'copn-payment-gateway') . '</strong></p>';
                echo '<p>' . esc_html__('Order Amount:', 'copn-payment-gateway') . ' ' . wp_kses_post(wc_price($display_amount)) . '</p>';
                if (isset($charges['total']) && $charges['total'] > 0) {
                    echo '<p>' . esc_html__('Charges:', 'copn-payment-gateway') . ' ' . wp_kses_post(wc_price($charges['total'])) . '</p>';
                    if (isset($charges['amount_to_pay'])) {
                        echo '<p><strong>' . esc_html__('Total to Pay:', 'copn-payment-gateway') . ' ' . wp_kses_post(wc_price($charges['amount_to_pay'])) . '</strong></p>';
                    } elseif (isset($charges['business_receives'])) {
                        echo '<p><strong>' . esc_html__('You will receive:', 'copn-payment-gateway') . ' ' . wp_kses_post(wc_price($charges['business_receives'])) . '</strong></p>';
                    }
                }
                echo '</div>';
            }

            echo '<p><button type="button" id="copn-check-status" class="button">' . esc_html__('Check Payment Status', 'copn-payment-gateway') . '</button></p>';

            echo '<div class="checkoutpay-update-amount" style="margin-top: 15px; padding: 15px; background: #fff8e5; border: 1px solid #e5d48a; border-radius: 5px;">';
            echo '<p><strong>' . esc_html__('Paid a different amount?', 'copn-payment-gateway') . '</strong></p>';
            echo '<p class="description">' . esc_html__('If you transferred a different sum than shown above, enter the actual amount you paid and we will update the transaction and re-check for your payment.', 'copn-payment-gateway') . '</p>';
            echo '<p style="margin-bottom: 8px;"><label for="checkoutpay-actual-amount">' . esc_html__('Amount paid:', 'copn-payment-gateway') . ' </label>';
            echo '<input type="number" id="copn-actual-amount" name="checkoutpay_actual_amount" min="0.01" step="0.01" placeholder="0.00" style="width: 120px; padding: 6px;"> ';
            echo '<button type="button" id="copn-update-amount-btn" class="button">' . esc_html__('Update amount & check status', 'copn-payment-gateway') . '</button></p>';
            echo '</div>';
            
            echo '</div>';
        }
    }
}
