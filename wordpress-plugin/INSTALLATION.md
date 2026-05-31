# CheckoutPay – Installation guide for WooCommerce merchants

This guide walks you through installing and configuring the **CheckoutPay – Bank Transfer Gateway for WooCommerce** plugin on your store.

**Support:** [https://check-outpay.com/support](https://check-outpay.com/support) · **Email:** notify@check-outpay.com

---

## Requirements

- WordPress 5.8 or later
- WooCommerce 7.0 or later
- A [CheckoutPay](https://check-outpay.com/) merchant account
- HTTPS on your store (recommended for webhooks)

---

## Phase 1 — Install in WordPress

1. **Download** the plugin ZIP (`copn-payment-gateway.zip`) from [CheckoutPay](https://check-outpay.com/) or upload the `copn-payment-gateway` folder.
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**, choose the ZIP, and click **Install Now**, then **Activate**.
   - Or upload the `copn-payment-gateway` folder to `wp-content/plugins/` via FTP/SFTP and activate under **Plugins**.
3. Confirm **WooCommerce** is installed and active. The plugin cannot run without it.
4. Go to **WooCommerce → Settings → Payments**.
5. Find **CheckoutPay**, turn **Enable** on, and click **Manage**.

> **Screenshot:** WooCommerce → Settings → Payments — CheckoutPay row with Enable toggle and Manage link.

---

## Phase 2 — Configure CheckoutPay

### 2.1 Create your CheckoutPay account

1. Sign up or log in at [https://check-outpay.com/](https://check-outpay.com/).
2. Complete business verification if required.
3. Open **Settings** or **API** in the dashboard and create an **API key**. Copy it somewhere safe.

### 2.2 Connect your store in WooCommerce

On the **CheckoutPay** payment settings screen:

| Field | Value |
|-------|--------|
| **API URL** | `https://check-outpay.com/api/v1` |
| **API Key** | Your key from CheckoutPay |
| **Test mode** | Enable while testing; disable for live sales |

### 2.3 Register your website URL

1. In plugin settings, copy the **Website URL** (your store’s home URL, e.g. `https://your-store.com`).
2. In CheckoutPay, go to **Dashboard → Websites** and add or approve that exact URL.

### 2.4 Set up the webhook

1. In plugin settings, copy the **Webhook URL**. It looks like:

   ```
   https://your-store.com/?wc-api=wc_checkoutpay_webhook
   ```

2. In CheckoutPay **Dashboard → Websites**, paste this URL for your store.
3. Save WooCommerce gateway settings.

### 2.5 Verify API connection

1. Click **Refresh charges** in the gateway settings.
2. If fees load in the panel, your API URL and key are correct.
3. If you see an error, double-check the API key and that your website URL is approved in CheckoutPay.

---

## Phase 3 — Test and go live

1. Enable **Test mode** and use test credentials from CheckoutPay if available.
2. Place a **test order** on your store; select **CheckoutPay** at checkout.
3. On the **thank-you page**, confirm bank name, account number, and amount are shown.
4. Complete or simulate payment in CheckoutPay.
5. Confirm the WooCommerce order moves to **Processing** or **Completed** (depending on your “auto complete” setting).
6. Disable **Test mode** when you are ready for real customers.
7. Confirm live webhooks: after a real transfer, CheckoutPay sends `payment.approved` and the order status updates without manual action.

---

## Optional settings

- **Title / Description** — What customers see at checkout (default title: “CheckoutPay”).
- **Mark order completed when payment is approved** — Automatically set order to Completed instead of Processing.
- **Developer program partner business ID** — Only if you were given a partner ID by CheckoutPay.

---

## Troubleshooting

### CheckoutPay does not appear at checkout

- Go to **WooCommerce → Settings → Payments** and ensure CheckoutPay is **enabled**.
- Open **Manage** and confirm **API URL** and **API Key** are filled in and saved.
- Check for an admin notice: the gateway hides from checkout if enabled without credentials.

### Webhook not updating orders

- Webhook URL in CheckoutPay must **exactly** match the URL in plugin settings (including `https` and no trailing slash issues on your server).
- Your site must be reachable from the internet (not localhost-only).
- Check that your store uses the same **website URL** registered in CheckoutPay.
- Temporarily use **Check payment status** on the thank-you page to confirm the payment ID is linked to the order.

### Block checkout issues

- This plugin supports **WooCommerce Cart/Checkout blocks** and classic checkout.
- Update WooCommerce and the plugin to the latest version.
- Clear any page/cache plugin cache after enabling the gateway.

### Thank-you page missing bank details

- Ensure the order was paid with CheckoutPay (payment method on the order).
- Check WooCommerce logs and PHP error log if the API call to create the payment failed.
- Verify API key and website URL in CheckoutPay.

### “Refresh charges” fails

- Invalid or revoked API key.
- Website URL not approved in CheckoutPay.
- Server cannot reach `https://check-outpay.com` (firewall/outbound HTTPS).

---

## Uninstall

Deactivating the plugin stops new CheckoutPay checkouts. Uninstalling removes the gateway settings option from the database (`woocommerce_checkoutpay_settings`). Existing orders and their meta are not deleted.

---

## Getting help

- **Support portal:** [https://check-outpay.com/support](https://check-outpay.com/support)
- **Email:** notify@check-outpay.com
- Include your store URL, WooCommerce version, plugin version, and whether you use block or classic checkout.
