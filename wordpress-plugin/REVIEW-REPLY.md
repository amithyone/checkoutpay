# WordPress.org review — reply template (v1.4.4 / COPN)

**Before sending:** Verify **notify@check-outpay.com** on your [WordPress.org profile](https://profiles.wordpress.org/).

---

**Subject:** Re: External services disclosure — COPN Payment Gateway v1.4.4

Hello Plugins Team,

Thank you for the review feedback on external services documentation.

I have uploaded **version 1.4.4** with an expanded **== External services ==** section in readme.txt that documents CheckoutPay (our payment API), what data is sent and when, and direct links to:

* Terms of service: https://check-outpay.com/terms-and-conditions
* Privacy policy: https://check-outpay.com/privacy-policy

Previous slug alignment (**`copn-payment-gateway`**) remains in place from v1.4.2+:

- **Plugin folder / main file:** `copn-payment-gateway` / `copn-payment-gateway.php`
- **Text domain:** `copn-payment-gateway` (all translations and `__()` calls)
- **PHP prefix:** `copn_` functions and `COPN_*` constants (legacy `checkoutpay_* wrappers kept only where needed for WooCommerce gateway ID compatibility)
- **Display name:** COPN Payment Gateway for Nigerian Businesses

WooCommerce payment method ID remains `checkoutpay` so existing merchant stores keep their saved API settings and webhook URLs (`wc_checkoutpay_webhook`).

**Ownership:** Official CheckoutPay plugin. **notify@check-outpay.com** is verified on my WordPress.org profile. Author **CheckoutPay**, Author URI **https://check-outpay.com/**

Thank you.

Innocent Amithy  
CheckoutPay
