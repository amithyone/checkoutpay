=== CheckoutPay Payment Gateway ===
Contributors: checkoutpay
Tags: woocommerce, payment, payment-gateway, checkoutpay
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
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

= 1.0.0 =
* Initial release
* WooCommerce integration
* Payment processing
* Webhook support
* Payment status checking

== Upgrade Notice ==

= 1.0.0 =
Initial release of CheckoutPay Payment Gateway for WooCommerce.
