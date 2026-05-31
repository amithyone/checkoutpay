=== COPN Payment Gateway for Nigerian Businesses ===
Contributors: amithyone
Tags: payment, payment gateway, bank transfer, nigeria, checkoutpay
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Official CheckoutPay Nigeria (COPN) bank-transfer gateway for Nigerian businesses — virtual accounts, webhooks, and automatic order updates.

== Description ==

**COPN** stands for **CheckoutPay Nigeria**. **COPN Payment Gateway for Nigerian Businesses** is the official extension that connects your online store to [CheckoutPay](https://check-outpay.com/) for Nigerian bank-transfer payments.

Install **WooCommerce** separately; this plugin adds CheckoutPay as a payment method. COPN is operated by CheckoutPay and is **not** affiliated with WooCommerce or Automattic.

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

Developed and maintained by **CheckoutPay** ([check-outpay.com](https://check-outpay.com/)). COPN is the CheckoutPay Nigeria product line for merchant bank-transfer checkout.

== Installation ==

= Phase 1 — Install in WordPress =

1. Upload the `copn-payment-gateway` folder to `/wp-content/plugins/`, or install the plugin ZIP via **Plugins → Add New → Upload Plugin**.
2. **Activate** the plugin through the **Plugins** screen. WooCommerce must be installed and active.
3. Go to **WooCommerce → Settings → Payments**, find **CheckoutPay**, toggle **Enable**, then click **Manage**.

= Phase 2 — Configure CheckoutPay =

1. Sign up or log in at [CheckoutPay](https://check-outpay.com/).
2. In your CheckoutPay dashboard, create or copy your **API key**.
3. In WooCommerce **CheckoutPay** settings, set **API URL** and **API Key**.
4. Register your store **Website URL** and **Webhook URL** in CheckoutPay (Dashboard → Websites).
5. Click **Refresh charges** to confirm the API connection.

= Phase 3 — Test and go live =

1. Place a **test order** and select CheckoutPay at checkout.
2. Confirm bank details on the thank-you page.
3. Disable **Test mode** when ready for live payments.

== Frequently Asked Questions ==

= What does COPN mean? =

COPN stands for **CheckoutPay Nigeria** — the official WooCommerce integration brand for CheckoutPay bank transfers.

= Does this work with block checkout? =

Yes. The plugin registers with WooCommerce Cart/Checkout blocks.

= Does the plugin work without WooCommerce? =

No. WooCommerce must be installed and active.

== Screenshots ==

1. CheckoutPay enabled under WooCommerce → Settings → Payments
2. Gateway settings with webhook URL and live charges preview
3. Thank-you page with bank transfer instructions

== External services ==

This plugin connects to **CheckoutPay** at https://check-outpay.com/

**Data sent**

* Order amount, customer name, order reference, store `website_url`, and `webhook_url`
* API key in the `X-API-Key` header on each request

**When it is sent**

* When checkout completes (create payment request)
* When the customer checks payment status on the thank-you page
* When CheckoutPay sends a webhook to your site
* When a store admin refreshes charges in settings

**Terms and privacy**

* CheckoutPay: https://check-outpay.com/

== Changelog ==

= 1.4.2 =
* WordPress.org slug alignment: folder `copn-payment-gateway`, main file `copn-payment-gateway.php`, text domain `copn-payment-gateway`, admin script handles prefixed with `copn-`.

= 1.4.1 =
* Plugin Check: literal text domain strings; COPN rebrand polish.

= 1.4.0 =
* Rebrand to COPN (CheckoutPay Nigeria): display name **COPN Payment Gateway for Nigerian Businesses**.

= 1.3.4 =
* WordPress.org: remove "WooCommerce" from plugin display name.

= 1.3.3 =
* WordPress.org review: ownership, webhook sanitization, enqueued scripts.

= 1.3.0 =
* WordPress.org review: enqueue scripts, sanitization, Checkoutpay_* class prefix.

== Upgrade Notice ==

= 1.4.2 =
Align plugin folder, text domain, and prefixes with WordPress.org slug `copn-payment-gateway`.

= 1.4.0 =
Rebrand to COPN display name (CheckoutPay Nigeria).
