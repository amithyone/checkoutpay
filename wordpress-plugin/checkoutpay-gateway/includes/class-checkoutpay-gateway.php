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
        $this->icon = CHECKOUTPAY_PLUGIN_URL . 'assets/images/checkoutpay-logo.png';
        $this->has_fields = false;
        $this->method_title = __('CheckoutPay', 'checkoutpay-gateway');
        $this->method_description = __('Accept payments via CheckoutPay payment gateway. Payments are processed securely through email-based payment verification.', 'checkoutpay-gateway');
        
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->api_key = $this->get_option('api_key');
        $this->api_url = $this->get_option('api_url');
        $this->test_mode = $this->get_option('test_mode');
        
        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_checkoutpay_gateway', array($this, 'check_payment_status'));
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
            'test_mode' => array(
                'title' => __('Test Mode', 'checkoutpay-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Test Mode', 'checkoutpay-gateway'),
                'default' => 'no',
                'description' => __('Enable test mode to use test API credentials.', 'checkoutpay-gateway'),
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
            'webhook_url' => add_query_arg('wc-api', 'wc_checkoutpay_webhook', home_url('/')),
        );

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
            $order->update_meta_data('_checkoutpay_account_name', isset($payment_data['account_details']['account_name']) ? $payment_data['account_details']['account_name'] : '');
            $order->update_meta_data('_checkoutpay_bank_name', isset($payment_data['account_details']['bank_name']) ? $payment_data['account_details']['bank_name'] : '');
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
     * Check payment status
     */
    public function check_payment_status() {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array('message' => 'Invalid order ID'));
            return;
        }

        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
            return;
        }

        $payment_id = $order->get_meta('_checkoutpay_payment_id');
        
        if (!$payment_id) {
            wp_send_json_error(array('message' => 'Payment ID not found'));
            return;
        }

        // Check payment status via API
        $api_url = trailingslashit($this->api_url) . 'payments/' . $payment_id;
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'X-API-Key' => $this->api_key,
            ),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }

        $response_data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_data['data']['status'])) {
            $status = $response_data['data']['status'];
            
            if ($status === 'approved' || $status === 'completed') {
                $order->payment_complete();
                $order->add_order_note(__('Payment confirmed via CheckoutPay', 'checkoutpay-gateway'));
                wp_send_json_success(array('status' => 'completed'));
            } else {
                wp_send_json_success(array('status' => $status));
            }
        } else {
            wp_send_json_error(array('message' => 'Unable to retrieve payment status'));
        }
    }

    /**
     * Handle webhook callback
     */
    public function handle_webhook() {
        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        if (!isset($data['reference']) || !isset($data['status'])) {
            status_header(400);
            exit;
        }

        // Find order by transaction_id
        $transaction_id = isset($data['transaction_id']) ? sanitize_text_field($data['transaction_id']) : '';
        
        if (empty($transaction_id)) {
            status_header(400);
            exit;
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
        $status = sanitize_text_field($data['status']);

        // Verify webhook signature if available
        if (isset($data['signature'])) {
            // Add signature verification here if your API provides it
        }

        // Update order status based on webhook event
        $event = isset($data['event']) ? sanitize_text_field($data['event']) : '';
        
        if ($status === 'approved' || $event === 'payment.approved') {
            $order->payment_complete();
            $order->add_order_note(__('Payment confirmed via CheckoutPay webhook', 'checkoutpay-gateway'));
            $order->update_meta_data('_checkoutpay_status', 'approved');
            
            // Update charges if provided
            if (isset($data['charges']) && is_array($data['charges'])) {
                $order->update_meta_data('_checkoutpay_charges', $data['charges']);
            }
            
            $order->save();
            
            status_header(200);
            echo json_encode(array('success' => true));
        } elseif ($status === 'rejected' || $status === 'failed' || $event === 'payment.rejected') {
            $reason = isset($data['reason']) ? sanitize_text_field($data['reason']) : __('Payment rejected', 'checkoutpay-gateway');
            $order->update_status('failed', __('Payment rejected via CheckoutPay: ', 'checkoutpay-gateway') . $reason);
            $order->update_meta_data('_checkoutpay_status', 'rejected');
            $order->save();
            
            status_header(200);
            echo json_encode(array('success' => true));
        } else {
            // Update status but don't change order status
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
            echo '</div>';
            ?>
            <script>
            jQuery(document).ready(function($) {
                $('#checkoutpay-check-status').on('click', function() {
                    var button = $(this);
                    button.prop('disabled', true).text('<?php _e('Checking...', 'checkoutpay-gateway'); ?>');
                    
                    $.ajax({
                        url: '<?php echo esc_url(add_query_arg('wc-api', 'wc_checkoutpay_gateway', home_url('/'))); ?>',
                        type: 'GET',
                        data: {
                            order_id: <?php echo $order_id; ?>
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
            });
            </script>
            <?php
        }
    }
}
