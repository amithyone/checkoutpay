<?php
/**
 * CheckoutPay Payment Gateway
 *
 * @package CheckoutPay
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_CheckoutPay_Gateway Class
 */
class WC_CheckoutPay_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'checkoutpay';
        $icon_path = CHECKOUTPAY_PLUGIN_DIR . 'assets/images/checkoutpay-logo.png';
        $this->icon = file_exists($icon_path) ? CHECKOUTPAY_PLUGIN_URL . 'assets/images/checkoutpay-logo.png' : '';
        $this->has_fields = false;
        $this->method_title = __('CheckoutPay', 'checkoutpay-gateway');
        $this->method_description = __('Accept bank-transfer payments via CheckoutPay. Customers pay to a virtual account; orders update automatically when payment is confirmed.', 'checkoutpay-gateway');
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
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'checkoutpay-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable CheckoutPay Payment Gateway', 'checkoutpay-gateway'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'checkoutpay-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'checkoutpay-gateway'),
                'default' => __('CheckoutPay', 'checkoutpay-gateway'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'checkoutpay-gateway'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'checkoutpay-gateway'),
                'default' => __('Pay securely via CheckoutPay. You will receive payment instructions via email.', 'checkoutpay-gateway'),
                'desc_tip' => true,
            ),
            'api_url' => array(
                'title' => __('API URL', 'checkoutpay-gateway'),
                'type' => 'text',
                'description' => __('Enter your CheckoutPay API URL (e.g., https://check-outpay.com/api/v1)', 'checkoutpay-gateway'),
                'default' => 'https://check-outpay.com/api/v1',
                'desc_tip' => true,
            ),
            'api_key' => array(
                'title' => __('API Key', 'checkoutpay-gateway'),
                'type' => 'password',
                'description' => __('Enter your CheckoutPay API Key. You can find this in your CheckoutPay dashboard.', 'checkoutpay-gateway'),
                'default' => '',
                'desc_tip' => true,
            ),
            'checkoutpay_webhook_url' => array(
                'title' => __('Webhook URL (for CheckoutPay)', 'checkoutpay-gateway'),
                'type' => 'checkoutpay_webhook_url',
                'description' => __('Copy this exact URL into your CheckoutPay business website webhook settings. The plugin sends it automatically on each order; it must match what you save in CheckoutPay.', 'checkoutpay-gateway'),
            ),
            'checkoutpay_charges_info' => array(
                'title' => __('Charges (from CheckoutPay)', 'checkoutpay-gateway'),
                'type' => 'checkoutpay_charges_info',
                'description' => __('Live fee rules for this store, as configured on your CheckoutPay business website. Refresh after changing settings in CheckoutPay.', 'checkoutpay-gateway'),
            ),
            'auto_complete_orders' => array(
                'title' => __('Order status on payment', 'checkoutpay-gateway'),
                'type' => 'checkbox',
                'label' => __('Mark orders as Completed when payment is approved', 'checkoutpay-gateway'),
                'default' => 'no',
                'description' => __('Default WooCommerce behavior is Processing for physical goods. Enable to set Completed immediately when CheckoutPay confirms payment.', 'checkoutpay-gateway'),
            ),
            'split_payment_notice' => array(
                'title' => __('Split payment', 'checkoutpay-gateway'),
                'type' => 'title',
                'description' => __('Installment and split payments are configured in your CheckoutPay dashboard (business websites and invoices), not in this plugin. WooCommerce orders use a single bank transfer per checkout.', 'checkoutpay-gateway'),
            ),
            'test_mode' => array(
                'title' => __('Test Mode', 'checkoutpay-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'checkoutpay-gateway'),
                'default' => 'no',
                'description' => __('Enable test mode to use test API credentials.', 'checkoutpay-gateway'),
            ),
            'developer_program_partner_business_id' => array(
                'title' => __('Developer program partner ID', 'checkoutpay-gateway'),
                'type' => 'text',
                'description' => __('Optional. CheckoutPay Business ID of an approved developer partner (sent as developer_program_partner_business_id on payment-request).', 'checkoutpay-gateway'),
                'default' => '',
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __('Instructions', 'checkoutpay-gateway'),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page.', 'checkoutpay-gateway'),
                'default' => __('Please check your email for payment instructions. Complete the payment to confirm your order.', 'checkoutpay-gateway'),
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
    public function get_checkoutpay_portal_url() {
        $url = defined('CHECKOUTPAY_PORTAL_URL') ? CHECKOUTPAY_PORTAL_URL : 'https://check-outpay.com';

        return untrailingslashit($url);
    }

    /**
     * Business websites settings on CheckoutPay.
     *
     * @return string
     */
    public function get_checkoutpay_dashboard_websites_url() {
        return $this->get_checkoutpay_portal_url() . '/dashboard/websites';
    }

    /**
     * Render read-only webhook URL with copy button (WooCommerce settings).
     *
     * @param string $key  Field key.
     * @param array  $data Field definition.
     * @return string
     */
    public function generate_checkoutpay_webhook_url_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $webhook_url = $this->get_webhook_url();
        $website_url = $this->get_store_website_url();
        $portal_url = $this->get_checkoutpay_portal_url();
        $dashboard_websites_url = $this->get_checkoutpay_dashboard_websites_url();
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
                    <p style="margin: 0 0 6px;"><strong><?php esc_html_e('CheckoutPay site URL', 'checkoutpay-gateway'); ?></strong></p>
                    <p style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; max-width: 42rem; margin-bottom: 12px;">
                        <input
                            type="text"
                            readonly
                            class="large-text code"
                            id="checkoutpay-portal-url"
                            value="<?php echo esc_attr($portal_url); ?>/"
                            onclick="this.select();"
                            style="flex: 1 1 280px;"
                        />
                        <a href="<?php echo esc_url($dashboard_websites_url); ?>" class="button" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e('Open website settings', 'checkoutpay-gateway'); ?>
                        </a>
                    </p>
                    <p class="description" style="margin: 0 0 12px;">
                        <?php esc_html_e('Register your WooCommerce store URL below in CheckoutPay → Dashboard → Websites.', 'checkoutpay-gateway'); ?>
                    </p>
                    <p style="margin: 0 0 6px;"><strong><?php esc_html_e('Webhook URL', 'checkoutpay-gateway'); ?></strong></p>
                    <p style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; max-width: 42rem;">
                        <input
                            type="text"
                            readonly
                            class="large-text code"
                            id="checkoutpay-webhook-url"
                            value="<?php echo esc_attr($webhook_url); ?>"
                            onclick="this.select();"
                            style="flex: 1 1 280px;"
                        />
                        <button type="button" class="button" id="checkoutpay-copy-webhook-url">
                            <?php esc_html_e('Copy webhook URL', 'checkoutpay-gateway'); ?>
                        </button>
                    </p>
                    <p class="description" style="margin: 8px 0 12px;">
                        <?php esc_html_e('CheckoutPay → Business websites → paste into Webhook URL for this store domain.', 'checkoutpay-gateway'); ?>
                    </p>
                    <p style="margin: 0 0 6px;"><strong><?php esc_html_e('Website URL (also sent to CheckoutPay)', 'checkoutpay-gateway'); ?></strong></p>
                    <p style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; max-width: 42rem;">
                        <input
                            type="text"
                            readonly
                            class="large-text code"
                            id="checkoutpay-website-url"
                            value="<?php echo esc_attr($website_url); ?>"
                            onclick="this.select();"
                            style="flex: 1 1 280px;"
                        />
                        <button type="button" class="button" id="checkoutpay-copy-website-url">
                            <?php esc_html_e('Copy website URL', 'checkoutpay-gateway'); ?>
                        </button>
                    </p>
                    <p class="description">
                        <?php esc_html_e('Use the same domain in CheckoutPay when you register or approve this WooCommerce site.', 'checkoutpay-gateway'); ?>
                    </p>
                    <script>
                    (function () {
                        function copyInputValue(inputId, buttonId, copiedLabel) {
                            var input = document.getElementById(inputId);
                            var button = document.getElementById(buttonId);
                            if (!input || !button) {
                                return;
                            }
                            var originalText = button.textContent;
                            function showCopied() {
                                button.textContent = copiedLabel;
                                setTimeout(function () {
                                    button.textContent = originalText;
                                }, 2000);
                            }
                            button.addEventListener('click', function () {
                                input.select();
                                input.setSelectionRange(0, 99999);
                                if (navigator.clipboard && navigator.clipboard.writeText) {
                                    navigator.clipboard.writeText(input.value).then(showCopied).catch(function () {
                                        try {
                                            document.execCommand('copy');
                                            showCopied();
                                        } catch (e) {
                                            alert(input.value);
                                        }
                                    });
                                } else {
                                    try {
                                        document.execCommand('copy');
                                        showCopied();
                                    } catch (e) {
                                        alert(input.value);
                                    }
                                }
                            });
                        }
                        copyInputValue(
                            'checkoutpay-webhook-url',
                            'checkoutpay-copy-webhook-url',
                            <?php echo wp_json_encode(__('Copied!', 'checkoutpay-gateway')); ?>
                        );
                        copyInputValue(
                            'checkoutpay-website-url',
                            'checkoutpay-copy-website-url',
                            <?php echo wp_json_encode(__('Copied!', 'checkoutpay-gateway')); ?>
                        );
                    })();
                    </script>
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
    public function generate_checkoutpay_charges_info_html($key, $data) {
        $field_key = $this->get_field_key($key);
        $defaults = array(
            'title' => '',
            'description' => '',
        );
        $data = wp_parse_args($data, $defaults);
        $website_url = $this->get_store_website_url();
        $webhook_url = $this->get_webhook_url();
        $portal_url = $this->get_checkoutpay_portal_url();
        $dashboard_websites_url = $this->get_checkoutpay_dashboard_websites_url();
        $sample_amount = 10000;
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
                        <button type="button" class="button" id="checkoutpay-refresh-charges">
                            <?php esc_html_e('Refresh charges', 'checkoutpay-gateway'); ?>
                        </button>
                        <span id="checkoutpay-charges-loading" style="display:none; margin-left: 8px;">
                            <?php esc_html_e('Loading…', 'checkoutpay-gateway'); ?>
                        </span>
                    </p>
                    <div id="checkoutpay-charges-panel" style="margin-top: 12px; max-width: 40rem; padding: 12px 14px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px;">
                        <p class="description" style="margin: 0;">
                            <?php esc_html_e('Save your API URL and API Key above, then click Refresh charges.', 'checkoutpay-gateway'); ?>
                        </p>
                    </div>
                    <script>
                    (function () {
                        var panel = document.getElementById('checkoutpay-charges-panel');
                        var loading = document.getElementById('checkoutpay-charges-loading');
                        var refreshBtn = document.getElementById('checkoutpay-refresh-charges');
                        if (!panel || !refreshBtn) {
                            return;
                        }

                        var websiteUrl = <?php echo wp_json_encode($website_url); ?>;
                        var webhookUrl = <?php echo wp_json_encode($webhook_url); ?>;
                        var portalUrl = <?php echo wp_json_encode($portal_url); ?>;
                        var dashboardWebsitesUrl = <?php echo wp_json_encode($dashboard_websites_url); ?>;
                        var sampleAmount = <?php echo (int) $sample_amount; ?>;

                        function getSettingInput(suffix) {
                            return document.getElementById('woocommerce_checkoutpay_' + suffix)
                                || document.querySelector('[name="woocommerce_checkoutpay_' + suffix + '"]');
                        }

                        function escapeHtml(text) {
                            var div = document.createElement('div');
                            div.textContent = text == null ? '' : String(text);
                            return div.innerHTML;
                        }

                        function formatMoney(amount) {
                            var n = parseFloat(amount);
                            if (isNaN(n)) {
                                return '—';
                            }
                            return '₦' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }

                        function renderError(message) {
                            panel.innerHTML = '<p style="margin:0;color:#b32d2e;"><strong><?php echo esc_js(__('Unable to load charges', 'checkoutpay-gateway')); ?></strong> ' + escapeHtml(message) + '</p>';
                        }

                        function renderData(payload) {
                            var d = payload.data || {};
                            var sample = d.sample || {};
                            var feeLine = '';
                            if (!d.charges_enabled || d.charge_exempt) {
                                feeLine = '<?php echo esc_js(__('No charges apply (disabled or exempt)', 'checkoutpay-gateway')); ?>';
                            } else {
                                feeLine = escapeHtml(d.charge_percentage) + '% + ' + formatMoney(d.charge_fixed);
                            }

                            var html = '';
                            html += '<table class="widefat striped" style="margin:0;background:#fff;"><tbody>';
                            html += '<tr><th style="width:40%;"><?php echo esc_js(__('Matched website', 'checkoutpay-gateway')); ?></th><td>' + escapeHtml((d.website && d.website.url) ? d.website.url : '—') + '</td></tr>';
                            html += '<tr><th><?php echo esc_js(__('Fee structure', 'checkoutpay-gateway')); ?></th><td>' + feeLine + '</td></tr>';
                            html += '<tr><th><?php echo esc_js(__('Who pays fees', 'checkoutpay-gateway')); ?></th><td>' + escapeHtml(d.paid_by_label || '—') + '</td></tr>';
                            html += '<tr><th><?php echo esc_js(__('Sample order', 'checkoutpay-gateway')); ?></th><td>' + formatMoney(d.sample_amount) + '</td></tr>';
                            html += '<tr><th><?php echo esc_js(__('Fees on sample', 'checkoutpay-gateway')); ?></th><td>' + formatMoney(sample.total_charges) + '</td></tr>';
                            html += '<tr><th><?php echo esc_js(__('Customer transfers', 'checkoutpay-gateway')); ?></th><td><strong>' + formatMoney(sample.amount_to_pay) + '</strong></td></tr>';
                            html += '<tr><th><?php echo esc_js(__('You receive', 'checkoutpay-gateway')); ?></th><td><strong>' + formatMoney(sample.business_receives) + '</strong></td></tr>';
                            html += '</tbody></table>';
                            if (d.dashboard_note) {
                                html += '<p class="description" style="margin:10px 0 0;">' + escapeHtml(d.dashboard_note) + '</p>';
                            }
                            var settingsUrl = (d.dashboard_websites_url && String(d.dashboard_websites_url).indexOf('http') === 0)
                                ? d.dashboard_websites_url
                                : dashboardWebsitesUrl;
                            html += '<p style="margin:8px 0 0;"><a href="' + escapeHtml(settingsUrl) + '" target="_blank" rel="noopener noreferrer"><?php echo esc_js(__('Open CheckoutPay website settings', 'checkoutpay-gateway')); ?></a> · <a href="' + escapeHtml((d.portal_url && String(d.portal_url).indexOf('http') === 0) ? d.portal_url : portalUrl) + '" target="_blank" rel="noopener noreferrer"><?php echo esc_js(__('CheckoutPay home', 'checkoutpay-gateway')); ?></a></p>';
                            panel.innerHTML = html;
                        }

                        function loadCharges() {
                            var apiUrlInput = getSettingInput('api_url');
                            var apiKeyInput = getSettingInput('api_key');
                            var apiUrl = apiUrlInput ? String(apiUrlInput.value || '').replace(/\/+$/, '') : '';
                            var apiKey = apiKeyInput ? String(apiKeyInput.value || '') : '';

                            if (!apiUrl || !apiKey) {
                                renderError('<?php echo esc_js(__('API URL and API Key are required.', 'checkoutpay-gateway')); ?>');
                                return;
                            }

                            if (loading) {
                                loading.style.display = 'inline';
                            }
                            refreshBtn.disabled = true;

                            var url = apiUrl + '/integration/charge-settings?website_url=' + encodeURIComponent(websiteUrl)
                                + '&webhook_url=' + encodeURIComponent(webhookUrl)
                                + '&sample_amount=' + encodeURIComponent(String(sampleAmount));

                            fetch(url, {
                                method: 'GET',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-API-Key': apiKey
                                }
                            })
                                .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, status: res.status, body: body }; }); })
                                .then(function (result) {
                                    if (result.ok && result.body && result.body.success) {
                                        renderData(result.body);
                                    } else {
                                        var msg = (result.body && result.body.message) ? result.body.message : ('HTTP ' + result.status);
                                        renderError(msg);
                                    }
                                })
                                .catch(function (err) {
                                    renderError(err && err.message ? err.message : '<?php echo esc_js(__('Network error', 'checkoutpay-gateway')); ?>');
                                })
                                .finally(function () {
                                    if (loading) {
                                        loading.style.display = 'none';
                                    }
                                    refreshBtn.disabled = false;
                                });
                        }

                        refreshBtn.addEventListener('click', loadCharges);
                    })();
                    </script>
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
            wc_add_notice(__('Payment error: ', 'checkoutpay-gateway') . $response->get_error_message(), 'error');
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }

        $response_data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_data['success']) && $response_data['success'] && isset($response_data['data'])) {
            $payment_data = $response_data['data'];
            
            // Get charges from API response
            $charges_data = isset($payment_data['charges']) 
                ? $payment_data['charges'] 
                : $this->calculateCharges($original_amount);
            
            // Store payment information in order meta
            $order->update_meta_data('_checkoutpay_transaction_id', $payment_data['transaction_id']);
            $order->update_meta_data('_checkoutpay_account_number', $payment_data['account_number']);
            $order->update_meta_data('_checkoutpay_account_name', isset($payment_data['account_name']) ? $payment_data['account_name'] : (isset($payment_data['account_details']['account_name']) ? $payment_data['account_details']['account_name'] : ''));
            $order->update_meta_data('_checkoutpay_bank_name', isset($payment_data['bank_name']) ? $payment_data['bank_name'] : (isset($payment_data['account_details']['bank_name']) ? $payment_data['account_details']['bank_name'] : ''));
            $order->update_meta_data('_checkoutpay_status', 'pending');
            $order->update_meta_data('_checkoutpay_charges', $charges_data);
            $order->update_meta_data('_checkoutpay_original_amount', $original_amount);
            $order->update_meta_data('_checkoutpay_expires_at', isset($payment_data['expires_at']) ? $payment_data['expires_at'] : '');
            
            // Update order total if customer pays charges
            if (isset($charges_data['paid_by_customer']) && $charges_data['paid_by_customer'] && isset($charges_data['amount_to_pay'])) {
                $order->set_total($charges_data['amount_to_pay']);
            }
            
            $order->save();

            // Mark order as pending payment
            $order->update_status('pending', __('Awaiting CheckoutPay payment', 'checkoutpay-gateway'));

            // Return success
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : __('Payment request failed. Please try again.', 'checkoutpay-gateway');
            wc_add_notice(__('Payment error: ', 'checkoutpay-gateway') . $error_message, 'error');
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
            return new WP_Error('invalid_order', __('Invalid order ID.', 'checkoutpay-gateway'));
        }

        if (!wp_verify_nonce($nonce, $this->thankyou_nonce_action($order_id))) {
            return new WP_Error('invalid_nonce', __('Security check failed. Please refresh the page and try again.', 'checkoutpay-gateway'));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('order_not_found', __('Order not found.', 'checkoutpay-gateway'));
        }

        if ($order->get_payment_method() !== $this->id) {
            return new WP_Error('invalid_gateway', __('Invalid payment method for this order.', 'checkoutpay-gateway'));
        }

        return $order;
    }

    /**
     * Check payment status
     */
    public function check_payment_status() {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';

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
                $this->mark_order_paid($order, __('Payment confirmed via CheckoutPay', 'checkoutpay-gateway'), $transaction_id);
                wp_send_json_success(array('status' => 'completed'));
            } else {
                wp_send_json_success(array('status' => $status));
            }
        } else {
            wp_send_json_error(array('message' => isset($response_data['message']) ? $response_data['message'] : __('Unable to retrieve payment status', 'checkoutpay-gateway')));
        }
    }

    /**
     * Update payment amount (correct wrong amount) then check status
     * Called via AJAX when customer says they paid a different amount.
     */
    public function update_payment_amount() {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $new_amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

        if ($new_amount <= 0) {
            wp_send_json_error(array('message' => __('Invalid order or amount.', 'checkoutpay-gateway')));
            return;
        }

        $order = $this->verify_thankyou_request($order_id, $nonce);
        if (is_wp_error($order)) {
            wp_send_json_error(array('message' => $order->get_error_message()));
            return;
        }

        $transaction_id = $order->get_meta('_checkoutpay_transaction_id');
        if (!$transaction_id) {
            wp_send_json_error(array('message' => __('Transaction ID not found.', 'checkoutpay-gateway')));
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
            $msg = isset($body['message']) ? $body['message'] : __('Failed to update amount.', 'checkoutpay-gateway');
            wp_send_json_error(array('message' => $msg));
            return;
        }

        // Update order meta from response if present
        if (!empty($body['data'])) {
            $data = $body['data'];
            if (isset($data['amount'])) {
                $order->update_meta_data('_checkoutpay_original_amount', $data['amount']);
            }
            if (isset($data['charges']) && is_array($data['charges'])) {
                $order->update_meta_data('_checkoutpay_charges', $data['charges']);
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
                    $this->mark_order_paid($order, __('Payment confirmed via CheckoutPay (after amount correction)', 'checkoutpay-gateway'), $transaction_id);
                    wp_send_json_success(array('status' => 'completed', 'message' => __('Amount updated and payment confirmed!', 'checkoutpay-gateway')));
                }
                wp_send_json_success(array('status' => $status, 'message' => __('Amount updated. Payment is still pending.', 'checkoutpay-gateway')));
            }
        }

        wp_send_json_success(array('status' => 'pending', 'message' => __('Amount updated. You can check status again in a moment.', 'checkoutpay-gateway')));
    }

    /**
     * Handle webhook callback
     * Expects POST body: event, transaction_id, status, amount, received_amount, charges, etc.
     */
    public function handle_webhook() {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        // Accept transaction_id and either status or event (API sends event + transaction_id + status)
        $transaction_id = isset($data['transaction_id']) ? sanitize_text_field($data['transaction_id']) : '';
        $status = isset($data['status']) ? sanitize_text_field($data['status']) : '';
        $event = isset($data['event']) ? sanitize_text_field($data['event']) : '';

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

        $orders = wc_get_orders(array(
            'meta_key' => '_checkoutpay_transaction_id',
            'meta_value' => $transaction_id,
            'limit' => 1,
        ));

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
                $order->update_meta_data('_checkoutpay_received_amount', $data['received_amount']);
            }
            if (isset($data['charges']) && is_array($data['charges'])) {
                $order->update_meta_data('_checkoutpay_charges', $data['charges']);
            }
            $this->mark_order_paid($order, __('Payment confirmed via CheckoutPay webhook', 'checkoutpay-gateway'), $transaction_id);
            status_header(200);
            echo json_encode(array('success' => true));
        } elseif ($status === 'rejected' || $status === 'failed' || $event === 'payment.rejected') {
            $reason = isset($data['mismatch_reason']) ? sanitize_text_field($data['mismatch_reason']) : (isset($data['reason']) ? sanitize_text_field($data['reason']) : __('Payment rejected', 'checkoutpay-gateway'));
            $order->update_status('failed', __('Payment rejected via CheckoutPay: ', 'checkoutpay-gateway') . $reason);
            $order->update_meta_data('_checkoutpay_status', 'rejected');
            $order->save();
            status_header(200);
            echo json_encode(array('success' => true));
        } else {
            $order->update_meta_data('_checkoutpay_status', $status);
            $order->save();
            status_header(200);
            echo json_encode(array('success' => true));
        }

        exit;
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
            echo '<div class="woocommerce-message">' . __('Payment confirmed. Thank you for your order!', 'checkoutpay-gateway') . '</div>';
        } else {
            $charges = $order->get_meta('_checkoutpay_charges');
            $original_amount = $order->get_meta('_checkoutpay_original_amount');
            
            echo '<div class="woocommerce-info">';
            echo '<p>' . esc_html($this->get_option('instructions')) . '</p>';
            
            if ($transaction_id) {
                echo '<p><strong>' . __('Transaction ID:', 'checkoutpay-gateway') . '</strong> ' . esc_html($transaction_id) . '</p>';
            }
            
            if ($account_number && $account_name && $bank_name) {
                echo '<div class="checkoutpay-account-details" style="margin-top: 15px; padding: 15px; background: #e8f4f8; border-left: 4px solid #0073aa; border-radius: 5px;">';
                echo '<p><strong>' . __('Payment Instructions:', 'checkoutpay-gateway') . '</strong></p>';
                echo '<p><strong>' . __('Account Number:', 'checkoutpay-gateway') . '</strong> ' . esc_html($account_number) . '</p>';
                echo '<p><strong>' . __('Account Name:', 'checkoutpay-gateway') . '</strong> ' . esc_html($account_name) . '</p>';
                echo '<p><strong>' . __('Bank Name:', 'checkoutpay-gateway') . '</strong> ' . esc_html($bank_name) . '</p>';
                echo '</div>';
            }
            
            if ($charges && is_array($charges)) {
                echo '<div class="checkoutpay-charges-info" style="margin-top: 15px; padding: 15px; background: #f0f0f0; border-radius: 5px;">';
                echo '<p><strong>' . __('Payment Details:', 'checkoutpay-gateway') . '</strong></p>';
                echo '<p>' . __('Order Amount:', 'checkoutpay-gateway') . ' ' . wc_price($original_amount ?: $order->get_total()) . '</p>';
                if (isset($charges['total']) && $charges['total'] > 0) {
                    echo '<p>' . __('Charges:', 'checkoutpay-gateway') . ' ' . wc_price($charges['total']) . '</p>';
                    if (isset($charges['amount_to_pay'])) {
                        echo '<p><strong>' . __('Total to Pay:', 'checkoutpay-gateway') . ' ' . wc_price($charges['amount_to_pay']) . '</strong></p>';
                    } elseif (isset($charges['business_receives'])) {
                        echo '<p><strong>' . __('You will receive:', 'checkoutpay-gateway') . ' ' . wc_price($charges['business_receives']) . '</strong></p>';
                    }
                }
                echo '</div>';
            }
            
            echo '<p><button type="button" id="checkoutpay-check-status" class="button">' . __('Check Payment Status', 'checkoutpay-gateway') . '</button></p>';
            
            echo '<div class="checkoutpay-update-amount" style="margin-top: 15px; padding: 15px; background: #fff8e5; border: 1px solid #e5d48a; border-radius: 5px;">';
            echo '<p><strong>' . __('Paid a different amount?', 'checkoutpay-gateway') . '</strong></p>';
            echo '<p class="description">' . __('If you transferred a different sum than shown above, enter the actual amount you paid and we will update the transaction and re-check for your payment.', 'checkoutpay-gateway') . '</p>';
            echo '<p style="margin-bottom: 8px;"><label for="checkoutpay-actual-amount">' . __('Amount paid:', 'checkoutpay-gateway') . ' </label>';
            echo '<input type="number" id="checkoutpay-actual-amount" name="checkoutpay_actual_amount" min="0.01" step="0.01" placeholder="0.00" style="width: 120px; padding: 6px;"> ';
            echo '<button type="button" id="checkoutpay-update-amount-btn" class="button">' . __('Update amount & check status', 'checkoutpay-gateway') . '</button></p>';
            echo '</div>';
            
            echo '</div>';
            $thankyou_nonce = wp_create_nonce($this->thankyou_nonce_action($order_id));
            ?>
            <script>
            jQuery(document).ready(function($) {
                var checkoutpayNonce = <?php echo wp_json_encode($thankyou_nonce); ?>;

                $('#checkoutpay-check-status').on('click', function() {
                    var button = $(this);
                    button.prop('disabled', true).text('<?php echo esc_js(__('Checking...', 'checkoutpay-gateway')); ?>');
                    
                    $.ajax({
                        url: '<?php echo esc_url(add_query_arg('wc-api', 'wc_checkoutpay_gateway', home_url('/'))); ?>',
                        type: 'GET',
                        data: {
                            order_id: <?php echo (int) $order_id; ?>,
                            nonce: checkoutpayNonce
                        },
                        success: function(response) {
                            if (response.success && response.data.status === 'completed') {
                                location.reload();
                            } else {
                                button.prop('disabled', false).text('<?php _e('Check Payment Status', 'checkoutpay-gateway'); ?>');
                                alert('<?php _e('Payment is still pending. Please check your email for payment instructions.', 'checkoutpay-gateway'); ?>');
                            }
                        },
                        error: function() {
                            button.prop('disabled', false).text('<?php _e('Check Payment Status', 'checkoutpay-gateway'); ?>');
                            alert('<?php _e('Unable to check payment status. Please try again later.', 'checkoutpay-gateway'); ?>');
                        }
                    });
                });

                $('#checkoutpay-update-amount-btn').on('click', function() {
                var button = $(this);
                var amountInput = $('#checkoutpay-actual-amount');
                var amount = parseFloat(amountInput.val());
                if (!amount || amount <= 0) {
                    alert('<?php echo esc_js(__('Please enter the amount you paid.', 'checkoutpay-gateway')); ?>');
                    return;
                }
                button.prop('disabled', true).text('<?php echo esc_js(__('Updating...', 'checkoutpay-gateway')); ?>');
                $.ajax({
                    url: '<?php echo esc_url(add_query_arg('wc-api', 'wc_checkoutpay_update_amount', home_url('/'))); ?>',
                    type: 'POST',
                    data: {
                        order_id: <?php echo (int) $order_id; ?>,
                        amount: amount,
                        nonce: checkoutpayNonce
                    },
                    success: function(response) {
                        if (response.success) {
                            if (response.data && response.data.status === 'completed') {
                                location.reload();
                            } else {
                                button.prop('disabled', false).text('<?php echo esc_js(__('Update amount & check status', 'checkoutpay-gateway')); ?>');
                                alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Amount updated. You can check status again.', 'checkoutpay-gateway')); ?>');
                            }
                        } else {
                            button.prop('disabled', false).text('<?php echo esc_js(__('Update amount & check status', 'checkoutpay-gateway')); ?>');
                            alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Update failed. Please try again.', 'checkoutpay-gateway')); ?>');
                        }
                    },
                    error: function(xhr) {
                        button.prop('disabled', false).text('<?php echo esc_js(__('Update amount & check status', 'checkoutpay-gateway')); ?>');
                        var msg = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ? xhr.responseJSON.data.message : '<?php echo esc_js(__('Unable to update. Please try again.', 'checkoutpay-gateway')); ?>';
                        alert(msg);
                    }
                });
                });
            });
            </script>
            <?php
        }
    }
}
