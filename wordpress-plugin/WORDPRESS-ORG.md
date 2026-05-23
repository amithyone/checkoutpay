# Submitting CheckoutPay Gateway to WordPress.org

This folder contains the **WordPress.org–ready** plugin source at `checkoutpay-gateway/`. The live site also ships a ZIP from `public/downloads/checkoutpay-gateway.zip` (see below).

## Compliance checklist

| Requirement | Status |
|-------------|--------|
| GPLv2 or later | `LICENSE.txt`, plugin header `License: GPL-2.0-or-later` |
| `readme.txt` WordPress standard | `checkoutpay-gateway/readme.txt` — validate at [wordpress.org/plugins/developers/readme-validator](https://wordpress.org/plugins/developers/readme-validator/) |
| No front-end “powered by” links | Storefront only shows payment instructions; CheckoutPay links are **admin settings only** |
| External services documented | `== External services ==` section in readme |
| WooCommerce dependency | `Requires Plugins: woocommerce` + activation check |
| Security | ABSPATH guards, escaping, thank-you AJAX nonces |
| SVN hosting | You must upload to the SVN repo WordPress assigns after approval |

## Before you submit

1. **WordPress.org account** — [https://wordpress.org/support/register.php](https://wordpress.org/support/register.php)
2. **Contributors** — In `readme.txt`, change `Contributors: checkoutpay` to your **WordPress.org username** (comma-separated if multiple).
3. **Validate readme** — Paste `readme.txt` into the [readme validator](https://wordpress.org/plugins/developers/readme-validator/).
4. **Plugin Check** (recommended) — Install the [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin on a test site and run it against this folder.
5. **Screenshots** — Add PNGs to `/assets/` in SVN after approval (see [plugin assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)): `screenshot-1.png`, etc., matching the `== Screenshots ==` section in readme.

## Submission steps

1. Go to [Add your plugin](https://wordpress.org/plugins/developers/add/).
2. Upload a **ZIP** of the `checkoutpay-gateway` folder only (folder name must match slug `checkoutpay-gateway`).
3. Wait for manual review (often 1–14 days). Fix any feedback by email.
4. After approval, use the **Subversion** URL WordPress provides:
   - `trunk/` — development copy (upload plugin files here first)
   - `tags/1.2.2/` — copy of release version
   - `assets/` — banners and screenshots (not in the plugin ZIP)

```bash
# Example after svn checkout https://plugins.svn.wordpress.org/checkoutpay-gateway
cp -r checkoutpay-gateway/* /path/to/svn/checkoutpay-gateway/trunk/
cd /path/to/svn/checkoutpay-gateway
svn cp trunk tags/1.2.2
svn commit -m "Release 1.2.2"
```

## Build ZIP for your Laravel site (optional)

From the project root:

```bash
cd wordpress-plugin
zip -r ../public/downloads/checkoutpay-gateway.zip checkoutpay-gateway \
  -x "*.DS_Store" -x "*/.git/*"
```

Bump version in:

- `checkoutpay-gateway/checkoutpay-gateway.php` (`Version` + `CHECKOUTPAY_VERSION`)
- `checkoutpay-gateway/readme.txt` (`Stable tag` + changelog)
- `config/checkout.php` → `wordpress_plugin.version`

## Notes for reviewers

- **External API**: Merchants opt in by entering their own API URL and key; no tracking without configuration.
- **Webhooks**: Incoming POST only to `?wc-api=wc_checkoutpay_webhook`; no outbound calls on page load.
- **Thank-you AJAX**: Protected with per-order nonces; no admin capability required for paying customers.
