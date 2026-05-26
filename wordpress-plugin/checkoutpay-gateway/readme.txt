=== Bank Transfer Gateway for CheckoutPay and WooCommerce ===
Contributors: amithyone
Tags: woocommerce, payment, payment gateway, bank transfer, nigeria
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.3.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Official CheckoutPay extension for WooCommerce: Nigerian bank-transfer checkout with virtual account details, webhooks, and automatic order updates.

== Description ==

**Bank Transfer Gateway for CheckoutPay and WooCommerce** is the official CheckoutPay extension that connects your WooCommerce store to [CheckoutPay](https://check-outpay.com/) for Nigerian bank-transfer payments. This plugin is a third-party integration for WooCommerce and is not affiliated with WooCommerce or Automattic.

= How it works =

1. Your customer selects **CheckoutPay** at checkout and places the order.
2. The plugin creates a payment request on your CheckoutPay business account and shows **bank transfer instructions** on the order thank-you page (account number, bank name, amount).
3. When CheckoutPay confirms the incoming transfer, the order updates automatically via **webhook**, or the customer can use **Check payment status** on the thank-you page.

= Who it is for =

Store owners in Nigeria who want reliable bank-transfer checkout with virtual account details, fee transparency, and automatic order status updates — without custom code.

= Requirements =

* WordPress 5.8 or later
* WooCommerce 7.0 or later
* A [CheckoutPay](https://check-outpay.com/) merchant account with an API key and an approved website URL

= Features =

* WooCommerce **classic checkout** and **Cart/Checkout blocks**
* **HPOS** (High-Performance Order Storage) compatible
* Virtual account / bank details on the thank-you page
* **Webhook** endpoint for automatic order status updates
* Optional **mark order completed** when payment is approved
* Thank-you page: check payment status and correct paid amount if the customer transferred a different sum
* **Test mode** for development
* Admin settings: copy webhook URL and website URL, live **fee preview** from CheckoutPay

This plugin does **not** add “powered by” links or promotional banners on your storefront. Links to CheckoutPay appear only in the WordPress **admin** settings screen where you configure API credentials.

= Ownership =

Developed and maintained by **CheckoutPay** ([check-outpay.com](https://check-outpay.com/)). This plugin integrates with WooCommerce as a payment method; it is not affiliated with WooCommerce or Automattic beyond that standard integration.

== Installation ==

= Phase 1 — Install in WordPress =

1. Upload the `checkoutpay-gateway` folder to `/wp-content/plugins/`, or install the plugin ZIP via **Plugins → Add New → Upload Plugin**.
2. **Activate** the plugin through the **Plugins** screen. WooCommerce must be installed and active (the plugin will not activate without it).
3. Go to **WooCommerce → Settings → Payments**, find **CheckoutPay**, toggle **Enable**, then click **Manage**.

= Phase 2 — Configure CheckoutPay =

1. Sign up or log in at [CheckoutPay](https://check-outpay.com/).
2. In your CheckoutPay dashboard, create or copy your **API key** (Settings / API).
3. In WooCommerce **CheckoutPay** settings, set:
   * **API URL:** `https://check-outpay.com/api/v1`
   * **API Key:** your key from CheckoutPay
4. Copy the **Website URL** shown in the plugin settings and register the same URL in CheckoutPay under **Dashboard → Websites**.
5. Copy the **Webhook URL** from the plugin settings (format: `https://your-store.com/?wc-api=wc_checkoutpay_webhook`) and paste it into CheckoutPay for that website.
6. Click **Refresh charges** in the gateway settings to confirm the API connection and view your fee rules.
7. Enable **Test mode** if you are testing; use test credentials from CheckoutPay.

= Phase 3 — Test and go live =

1. Place a **test order** on your store and select CheckoutPay at checkout.
2. On the thank-you page, confirm bank details are shown.
3. Complete or simulate payment in CheckoutPay; confirm the order moves to **Processing** or **Completed** (depending on your settings).
4. Disable **Test mode** when ready for live sales.

For troubleshooting, see the [CheckoutPay support](https://check-outpay.com/support) page.

== Frequently Asked Questions ==

= Where do I get an API key? =

Sign up at [CheckoutPay](https://check-outpay.com/), complete business setup, then create an API key in your CheckoutPay dashboard under API / Settings.

= What is the webhook URL? =

The plugin shows your webhook URL on the CheckoutPay payment settings screen. It looks like:

`https://your-store.com/?wc-api=wc_checkoutpay_webhook`

Paste the exact URL into CheckoutPay under **Dashboard → Websites** for your store domain.

= Does this work with WooCommerce blocks checkout? =

Yes. The plugin supports Cart/Checkout blocks as well as classic checkout.

= Does the plugin work without WooCommerce? =

No. WooCommerce must be installed and active.

= Is test mode supported? =

Yes. Enable **Test mode** in the gateway settings and use test API credentials from CheckoutPay.

= Why is CheckoutPay missing at checkout? =

Ensure the gateway is **enabled**, and that **API URL** and **API Key** are both saved. An admin warning appears if the gateway is enabled but credentials are missing.

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

* CheckoutPay: https://check-outpay.com/
* You are responsible for informing customers how payment data is processed under your store privacy policy.

== Changelog ==

= 1.3.3 =
* WordPress.org review: display name clarifies third-party WooCommerce integration; Author URI on check-outpay.com; webhook payload sanitization; admin fields use enqueued JS only (no inline handlers).

= 1.3.2 =
* Plugin Check: remove .distignore and phpcs.xml.dist from plugin package (dev files stay in git repo only, not in upload ZIP).

= 1.3.1 =
* Plugin Check: remove phpcs.xml.dist from plugin package (dev config lives in repo only); add .distignore for WordPress.org distribution.

= 1.3.0 =
* WordPress.org review: enqueue admin and thank-you scripts; sanitize webhook charges and received_amount; rename gateway classes to Checkoutpay_* prefix.

= 1.2.9 =
* Plugin Check: align Plugin Name with directory slug for text domain; add phpcs.xml.dist; document WC_ gateway class naming.

= 1.2.8 =
* Plugin Check: removed extra markdown files from plugin root; limited readme tags to five.

= 1.2.7 =
* Author URI updated to https://profile.amithyone.com/

= 1.2.6 =
* Plugin URI points to https://check-outpay.com/wordpress-plugin (distinct from Author URI).

= 1.2.5 =
* WordPress.org: brand-first display name, contributor amithyone, expanded description and installation guide, INSTALLATION.md.

= 1.2.4 =
* Plugin Check: phpcs disable block for webhook order meta lookup (slow query warnings).

= 1.2.3 =
* Plugin Check: escaped thank-you page output, filter_input for AJAX params, languages folder, Tested up to 7.0.

= 1.2.2 =
* WordPress.org readiness: GPL license file, standard readme, external services disclosure, directory index files.
* Security: nonce verification on thank-you page AJAX (status check and amount update).
* Activation requires WooCommerce; uninstall removes gateway settings option.

= 1.2.1 =
* Fixed: CheckoutPay site URL shown in plugin settings with link to dashboard website settings.

= 1.2.0 =
* New: Auto-complete orders option when CheckoutPay payment is approved.
* New: Charges panel in settings (loads live fee rules from CheckoutPay API).

= 1.1.0 =
* Fixed: CheckoutPay on WooCommerce block checkout; HPOS and block compatibility.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.3.0 =
WordPress.org review compliance (script enqueue, sanitization, class prefixes).

= 1.2.9 =
Plugin Check text domain and PHPCS alignment.

= 1.2.8 =
WordPress.org Plugin Check compliance (readme tags, plugin file layout).

= 1.2.7 =
Author link updated.

= 1.2.6 =
Plugin URI updated for WordPress.org (dedicated plugin page).

= 1.2.5 =
Updated display name and documentation for WordPress.org directory submission.

= 1.2.4 =
Fixes Plugin Check slow meta query warnings on webhook lookup.
