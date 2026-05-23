=== CheckoutPay Payment Gateway ===
Contributors: checkoutpay
Tags: woocommerce, payment, payment gateway, bank transfer, nigeria
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept bank-transfer payments in WooCommerce via CheckoutPay — virtual account details, webhooks, and order status updates.

== Description ==

**CheckoutPay Payment Gateway** adds a WooCommerce payment method for Nigerian bank-transfer checkout. When a customer places an order, the plugin creates a payment request on your CheckoutPay business account and shows transfer instructions on the order thank-you page. Orders update automatically when CheckoutPay confirms the transfer (webhook or manual status check).

**Requirements**

* WordPress 5.8 or later
* WooCommerce 7.0 or later
* A [CheckoutPay](https://check-outpay.com/) merchant account with an API key and approved website

**Features**

* WooCommerce classic checkout and Cart/Checkout blocks
* High-Performance Order Storage (HPOS) compatible
* Virtual account / bank details on the thank-you page
* Webhook endpoint for automatic order status updates
* Optional “mark order completed” when payment is approved
* Thank-you page: check payment status and correct paid amount
* Test mode toggle for development
* Admin settings: webhook URL copy, website URL, live fee preview from CheckoutPay

This plugin does **not** add “powered by” links or promotional banners on your storefront. Links to CheckoutPay appear only in the WordPress admin settings screen (where you configure API credentials).

== Installation ==

1. Upload the `checkoutpay-gateway` folder to `/wp-content/plugins/` or install the ZIP from Plugins → Add New.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Install and activate **WooCommerce** if it is not already active.
4. Go to **WooCommerce → Settings → Payments**, enable **CheckoutPay**, and open **Manage**.
5. Enter your **API URL** (e.g. `https://check-outpay.com/api/v1`) and **API Key** from your CheckoutPay dashboard.
6. In CheckoutPay, register your store URL and webhook URL (shown in the plugin settings).
7. Save changes and place a test order.

== Frequently Asked Questions ==

= Where do I get an API key? =

Sign up at [CheckoutPay](https://check-outpay.com/), complete business setup, then create an API key under your dashboard settings.

= What is the webhook URL? =

The plugin displays your webhook URL on the CheckoutPay payment settings screen. It looks like:

`https://your-store.com/?wc-api=wc_checkoutpay_webhook`

Paste the same URL into your CheckoutPay business website settings.

= Does this work with WooCommerce blocks checkout? =

Yes. The plugin registers support for Cart/Checkout blocks as well as classic checkout.

= Does the plugin work without WooCommerce? =

No. WooCommerce must be installed and active. The plugin will not activate without WooCommerce.

= Is test mode supported? =

Yes. Enable **Test mode** in the gateway settings and use test API credentials from CheckoutPay.

== Screenshots ==

1. CheckoutPay enabled under WooCommerce → Settings → Payments
2. Gateway settings: API URL, API key, webhook URL, and charges preview
3. Thank-you page with bank transfer instructions and payment status actions

== External services ==

This plugin connects to **CheckoutPay** (`https://check-outpay.com`) to create and manage bank-transfer payments. No data is sent until you save an API URL and API key and a customer places an order (or you click “Refresh charges” in settings).

**Data sent**

* Order amount, customer name, order reference (`service`), store `website_url`, and `webhook_url`
* Optional developer-program partner business ID (if configured)
* API key in the `X-API-Key` header on each request
* For status checks: `transaction_id` and order-related amounts
* For amount correction: `transaction_id` and `new_amount`

**When it is sent**

* When checkout completes (create payment request)
* When the customer clicks “Check payment status” or “Update amount & check status” on the thank-you page
* When CheckoutPay sends a webhook to your site (incoming; no outbound call)
* When a store admin clicks “Refresh charges” in WooCommerce settings

**Terms and privacy**

* CheckoutPay terms and privacy: https://check-outpay.com/
* You are responsible for informing customers how payment data is processed under your privacy policy.

== Changelog ==

= 1.2.2 =
* WordPress.org readiness: GPL license file, standard readme, external services disclosure, directory index files.
* Security: nonce verification on thank-you page AJAX (status check and amount update).
* Activation requires WooCommerce; uninstall removes gateway settings option.
* Plugin headers: Requires Plugins (woocommerce), License GPL-2.0-or-later.

= 1.2.1 =
* Fixed: CheckoutPay site URL (https://check-outpay.com) shown in plugin settings with link to dashboard website settings.
* Fixed: API dashboard_websites_url now points to /dashboard/websites (was an invalid /business/websites path).

= 1.2.0 =
* New: Auto-complete orders option when CheckoutPay payment is approved.
* New: Charges panel in settings (loads live fee rules from GET /api/v1/integration/charge-settings).
* New: Split payment notice — configure installments in CheckoutPay dashboard, not in WooCommerce plugin.

= 1.1.1 =
* New: Webhook URL and website URL shown on WooCommerce CheckoutPay settings with one-click copy (for CheckoutPay business website setup).

= 1.1.0 =
* Fixed: CheckoutPay now appears on WooCommerce block-based checkout (Cart/Checkout blocks).
* Fixed: Gateway loads after WooCommerce payment classes are available (avoids silent registration failure).
* Fixed: Thank-you page JavaScript syntax error for payment status buttons.
* New: HPOS and block checkout compatibility declarations for WooCommerce 8+.
* New: Optional developer program partner Business ID setting.
* New: Sends website_url on payment-request for website matching.
* Improved: Admin warning when the gateway is enabled but API URL or API Key is missing.

= 1.0.1 =
* Fixed: Check payment status now uses transaction_id and GET /api/v1/payment/{transactionId}.
* Fixed: Webhook handler accepts API payload (event, transaction_id, status).
* New: “Paid a different amount?” on the thank-you page with amount-correction API.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.2 =
Recommended for WordPress.org directory compliance and thank-you page security (nonces).

= 1.2.0 =
Adds auto-complete orders and a charges preview synced from your CheckoutPay website settings.

= 1.1.0 =
Required if CheckoutPay does not show on checkout (especially with WooCommerce block checkout).
