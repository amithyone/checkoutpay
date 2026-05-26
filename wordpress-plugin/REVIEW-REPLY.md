# WordPress.org review — reply template (v1.3.4)

**Before sending:** Add and verify **`notify@check-outpay.com`** on your [WordPress.org profile](https://profiles.wordpress.org/) (Profile → Email). The review team will not accept Gmail for ownership proof.

**Do not** rename the plugin to include your personal WordPress username unless you are a third-party developer — this is the **official CheckoutPay** plugin. Ownership is proven by the **check-outpay.com** email domain.

Copy the text below into your reply to the Plugins Team email thread. Keep it short; do not paste a long AI essay.

---

**Subject:** Re: CheckoutPay plugin review — ownership + v1.3.4 update

Hello,

This is the **official CheckoutPay** integration from https://check-outpay.com/ (not a personal or third-party developer plugin).

**Ownership:** I have added and verified **notify@check-outpay.com** on my WordPress.org profile. Author is **CheckoutPay**; Author URI and Plugin URI use **check-outpay.com**. My WP.org login is only the account used to submit; it is not part of the plugin brand.

**Slug:** Please reserve **checkoutpay-gateway** as the plugin permalink (folder name and text domain already use `checkoutpay-gateway`). If a different slug was auto-assigned on submit, please move us to **checkoutpay-gateway** — I will not resubmit under a second account.

**Display name (v1.3.4):** **Bank Transfer Gateway for CheckoutPay** — “WooCommerce” removed from the Plugin Name line and readme title per your restricted-term notice. WooCommerce is still required via `Requires Plugins: woocommerce` and documented in the description.

**Technical (v1.3.4):**
- Admin and thank-you JavaScript use `wp_enqueue_script` / `wp_localize_script` (no inline `<script>` blocks).
- Webhook JSON is validated as an array, then `received_amount`, `charges`, and other fields are sanitized before order meta.
- Gateway classes use the `Checkoutpay_` prefix (`Checkoutpay_Gateway`, `Checkoutpay_Blocks`); they extend WooCommerce core classes only as required by the payment gateway API.

I uploaded the updated ZIP via **Add your plugin** while logged in to my WordPress.org account.

Thank you for reviewing.

---

## Your checklist

- [ ] **notify@check-outpay.com** verified on wordpress.org profile (not Gmail)
- [ ] Upload ZIP built from `checkoutpay-gateway/` only (see [WORDPRESS-ORG.md](WORDPRESS-ORG.md))
- [ ] Plugin Check on a test site
- [ ] Reply sent in the **same** email thread (do not open a second submission)
- [ ] Do **not** resubmit under another account — ask them to transfer ownership if needed
