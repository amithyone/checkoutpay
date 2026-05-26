# Submitting CheckoutPay Gateway to WordPress.org

This folder contains the **WordPress.org–ready** plugin source at `copn-payment-gateway/` (COPN = CheckoutPay Nigeria). The live site ships a ZIP from `public/downloads/copn-payment-gateway.zip`.

Merchant setup guide: [INSTALLATION.md](INSTALLATION.md) (repo folder `wordpress-plugin/`, not shipped inside the plugin ZIP).

## Plugin naming and ownership

WordPress.org requires a **brand-first** name (not generic titles like “Payment Gateway” alone).

| Item | Value |
|------|--------|
| **Directory display name** | COPN Payment Gateway for Nigerian Businesses |
| **Slug** (request on submit; do not change after approval) | `copn-payment-gateway` |
| **WordPress.org username** | `amithyone` |
| **Verified email** | `notify@check-outpay.com` (must be on your [wordpress.org profile](https://profiles.wordpress.org/) before submit) |
| **Brand / service** | CheckoutPay — https://check-outpay.com/ |

### WordPress.org account setup (do this first)

1. Log in as **[amithyone](https://profiles.wordpress.org/)**.
2. Open **Profile → Email** and add **notify@check-outpay.com**.
3. Complete email **verification** (reviewers match **CheckoutPay** to domain **check-outpay.com**).
4. Optional: set website to `https://check-outpay.com/` on your profile.
5. Do **not** imply WooCommerce/Automattic ownership — this is a third-party payment integration only.

## Submission form text (copy-paste)

Use when submitting at [Add your plugin](https://wordpress.org/plugins/developers/add/):

| Field | Text |
|-------|------|
| **Plugin name** | COPN Payment Gateway for Nigerian Businesses |
| **Short description** | Official CheckoutPay extension for WooCommerce: Nigerian bank-transfer checkout with virtual account details, webhooks, and automatic order updates. |
| **Plugin URL** | https://check-outpay.com/wordpress-plugin |
| **Author** | CheckoutPay |
| **Support** | https://check-outpay.com/support |
| **Notes to reviewer** | Official CheckoutPay plugin from the service operator at check-outpay.com. Submitted by WordPress.org user **amithyone** with verified email **notify@check-outpay.com**. WooCommerce payment gateway; merchants configure their own API key. No data is sent until API URL and key are saved. No “powered by” links on the storefront. |

## Compliance checklist

| Requirement | Status |
|-------------|--------|
| Brand-first plugin name | CheckoutPay – … |
| Contributor = WP.org username | `amithyone` in readme.txt |
| GPLv2 or later | `LICENSE.txt`, header `GPL-2.0-or-later` |
| `readme.txt` WordPress standard | Validate at [readme validator](https://wordpress.org/plugins/developers/readme-validator/) |
| No front-end “powered by” links | Admin settings only |
| External services documented | `== External services ==` in readme |
| WooCommerce dependency | `Requires Plugins: woocommerce` + activation check |
| Plugin Check | Run on test site before submit |

## WordPress.org review reply

After uploading v1.3.0+, send the email in [REVIEW-REPLY.md](REVIEW-REPLY.md) (verify profile email first).

## Before you submit

1. Verify **notify@check-outpay.com** on your WordPress.org profile.
2. Paste `readme.txt` into the [readme validator](https://wordpress.org/plugins/developers/readme-validator/).
3. Run [Plugin Check](https://wordpress.org/plugins/plugin-check/) on the `copn-payment-gateway` folder.
4. Upload a **ZIP** of the `copn-payment-gateway` folder only (slug must match folder name).
5. Prepare screenshots for SVN `assets/` after approval (see readme `== Screenshots ==`).

## Submission steps

1. Go to [Add your plugin](https://wordpress.org/plugins/developers/add/).
2. Upload the ZIP; wait for manual review (often 1–14 days).
3. After approval, use Subversion:
   - `trunk/` — plugin files
   - `tags/1.2.5/` — release tag
   - `assets/` — banners and screenshots (not inside plugin ZIP)

```bash
svn checkout https://plugins.svn.wordpress.org/checkoutpay-gateway
cp -r checkoutpay-gateway/* checkoutpay-gateway/trunk/
cd checkoutpay-gateway
svn cp trunk tags/1.2.5
svn commit -m "Release 1.2.5"
```

## Build ZIP for check-outpay.com downloads

```bash
cd wordpress-plugin
rm -f ../public/downloads/checkoutpay-gateway.zip
zip -r ../public/downloads/copn-payment-gateway.zip copn-payment-gateway \
  -x "*.DS_Store" \
  -x "*/.distignore" \
  -x "*/phpcs.xml.dist" \
  -x "*/.git*" \
  -x "*/tests/*" \
  -x "*/vendor/*"
```

Do **not** put `.distignore` or `phpcs.xml.dist` inside `copn-payment-gateway/` — Plugin Check rejects hidden and application files. PHPCS config lives at `wordpress-plugin/phpcs.xml.dist` in this repo only.

Bump version in: `copn-payment-gateway.php`, `readme.txt` Stable tag, `config/checkout.php` → `wordpress_plugin.version`.

## Notes for reviewers

- **External API**: Merchants opt in with their own API URL and key.
- **Webhooks**: Incoming POST to `?wc-api=wc_checkoutpay_webhook` only.
- **Thank-you AJAX**: Per-order nonces; no admin capability required for customers.
