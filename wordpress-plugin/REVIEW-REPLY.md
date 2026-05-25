# WordPress.org review — reply template

**Before sending:** Add and verify `notify@check-outpay.com` on your [WordPress.org profile](https://profiles.wordpress.org/amithyone/edit/group/1/?screen=email) (Profile → Email). The review team matches ownership to `check-outpay.com`.

Copy the text below into your reply to the Plugins Team email thread (keep it short).

---

Subject: Re: CheckoutPay plugin review — ownership + v1.3.0 update

Hello,

This is the official CheckoutPay integration from the service at https://check-outpay.com/. I submit as WordPress.org user **amithyone** with verified email **notify@check-outpay.com** on my profile (domain matches the plugin Author URI and Plugin URI).

**Slug:** Please reserve and use **checkoutpay-gateway** as the plugin permalink (folder name and text domain already use this). If you have already reserved a different slug, I am happy to align to what you allocated — please confirm in your reply.

**v1.3.0** addresses the technical items from your checklist:
- Inline admin and thank-you scripts moved to `wp_enqueue_script` / `wp_localize_script`
- Webhook `received_amount` and `charges` sanitized before order meta
- Gateway classes renamed from `WC_CheckoutPay_*` to `Checkoutpay_*` with a distinct plugin prefix

Display name in `readme.txt` and the main plugin header: **CheckoutPay – Bank Transfer Gateway for WooCommerce**.

I have uploaded the updated ZIP via Add your plugin while logged in as amithyone.

Thank you for reviewing.

---

## Checklist (you)

- [ ] `notify@check-outpay.com` verified on wordpress.org profile
- [ ] ZIP uploaded (see build command in [WORDPRESS-ORG.md](WORDPRESS-ORG.md))
- [ ] Plugin Check + readme validator run on test site
- [ ] Reply sent (do not resubmit under a second account)
