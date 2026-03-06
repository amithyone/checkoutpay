=== CheckoutPay Payment Gateway ===
Contributors: checkoutpay
Tags: woocommerce, payment, payment-gateway, checkoutpay
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments via CheckoutPay payment gateway in WooCommerce stores.

== Description ==

CheckoutPay Payment Gateway allows WooCommerce store owners to accept payments through the CheckoutPay payment gateway. This plugin integrates seamlessly with WooCommerce and provides a secure, email-based payment verification system.

== Features ==

* Easy integration with WooCommerce
* Secure payment processing via CheckoutPay API
* Email-based payment verification
* Webhook support for automatic order status updates
* Test mode for development and testing
* Real-time payment status checking
* Customizable payment instructions

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/checkoutpay-gateway` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure WooCommerce is installed and activated
4. Go to WooCommerce > Settings > Payments
5. Enable CheckoutPay payment gateway
6. Configure your API URL and API Key from your CheckoutPay dashboard
7. Save changes

== Frequently Asked Questions ==

= Where do I get my API Key? =

You can find your API Key in your CheckoutPay business dashboard under Settings > API Keys.

= How do I set up webhooks? =

The plugin automatically sets up webhook endpoints. Make sure your CheckoutPay account has the webhook URL configured: `your-site.com/?wc-api=wc_checkoutpay_webhook`

= Does this work with test mode? =

Yes, enable test mode in the plugin settings to use test API credentials.

== Changelog ==

= 1.0.1 =
* Fixed: Check payment status now uses transaction_id and GET /api/v1/payment/{transactionId} (was incorrectly using payment_id and /payments/).
* Fixed: Webhook handler now accepts API payload (event, transaction_id, status) and no longer requires deprecated "reference" field.
* Fixed: Account name and bank name taken from API response (account_name, bank_name) with fallback to account_details.
* New: "Paid a different amount?" on the thank-you page: customer can enter the actual amount paid and click "Update amount & check status" to call the amount-correction API and re-check (so wrong-amount payments can still be matched).
* Improved: Webhook stores received_amount and uses mismatch_reason when payment is rejected.
* Docs: README updated with current API endpoints and webhook payload structure.

= 1.0.0 =
* Initial release
* WooCommerce integration
* Payment processing
* Webhook support
* Payment status checking

== Upgrade Notice ==

= 1.0.1 =
Fixes payment status check and webhook handling to match current CheckoutPay API. Upgrade if you use the "Check Payment Status" button or webhooks.

= 1.0.0 =
Initial release of CheckoutPay Payment Gateway for WooCommerce.
