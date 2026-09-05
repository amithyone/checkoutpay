# Meta WhatsApp Cloud API setup (Checkout)

Use this guide when switching from Evolution API to the **official Meta WhatsApp Cloud API**.

The app supports both providers via `WHATSAPP_PROVIDER`:
- `evolution` — legacy self-hosted Evolution API (default)
- `cloud` — Meta WhatsApp Cloud API

---

## 1. Facebook / Meta Developer app

1. Go to [Meta for Developers](https://developers.facebook.com/) and sign in.
2. **My Apps → Create App** → choose **Business** (or **Other** if Business is not available).
3. App name e.g. `CheckoutNow WhatsApp`, connect your Business Portfolio if prompted.
4. In the app dashboard, click **Add product** → **WhatsApp** → **Set up**.

---

## 2. WhatsApp Business Account (WABA)

1. Open **WhatsApp → API Setup** in the left menu.
2. Meta creates a **test** WhatsApp Business Account and test phone number.
3. For production, complete **Business verification** and add your real business phone number under **WhatsApp Manager**.

Copy from **API Setup**:
- **Phone number ID** → `WHATSAPP_CLOUD_PHONE_NUMBER_ID`
- **WhatsApp Business Account ID** → `WHATSAPP_CLOUD_WABA_ID` (optional, for your records)
- **Temporary access token** (for testing) → `WHATSAPP_CLOUD_ACCESS_TOKEN`

For production, create a **System User** in Business Settings with `whatsapp_business_messaging` permission and generate a **permanent token**.

---

## 3. Webhook callback URL & verify token

In Meta Developer → **WhatsApp → Configuration** (Webhook):

| Field | Value |
|--------|--------|
| **Callback URL** | `https://check-outpay.com/api/v1/whatsapp/webhook` |
| **Verify token** | A random secret string you choose (e.g. `checkout-wa-verify-2026`) |

Use your live domain (`WHATSAPP_APP_URL` base + `/api/v1/whatsapp/webhook`).

Set the **same verify token** in `.env`:

```env
WHATSAPP_PROVIDER=cloud
WHATSAPP_APP_URL=https://check-outpay.com
WHATSAPP_CLOUD_VERIFY_TOKEN=checkout-wa-verify-2026
WHATSAPP_CLOUD_ACCESS_TOKEN=EAAxxxx...
WHATSAPP_CLOUD_APP_SECRET=your-app-secret-from-meta-basic-settings
WHATSAPP_CLOUD_PHONE_NUMBER_ID=123456789012345
```

Then on the server:

```bash
php artisan config:clear
```

Click **Verify and save** in Meta. Meta sends:

```
GET /api/v1/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=…&hub.challenge=…
```

The app echoes `hub.challenge` when the verify token matches.

---

## 4. Subscribe to webhook fields

Under **Webhook fields**, subscribe at minimum to:

- **messages** — inbound user messages (required for the bot)

Optional (not required for basic bot):
- `message_template_status_update`
- `account_update`

---

## 5. App Secret (signature validation)

Meta signs POST webhooks with **X-Hub-Signature-256**.

1. Meta Developer → **App settings → Basic**
2. Copy **App secret** → `WHATSAPP_CLOUD_APP_SECRET`

The app validates every inbound POST when `WHATSAPP_PROVIDER=cloud`.

---

## 6. Multiple WhatsApp numbers (wallet + rentals)

If you have separate Meta phone numbers:

```env
WHATSAPP_CLOUD_PHONE_NUMBER_ID=111…          # default / main
WHATSAPP_CLOUD_PHONE_NUMBER_ID_WALLET=111…   # wallet bot replies (optional if same as default)
WHATSAPP_CLOUD_PHONE_NUMBER_ID_RENTALS=222… # rentals-only line (optional)
```

Keep Evolution-style instance names in env for routing logic:

```env
WHATSAPP_EVOLUTION_INSTANCE=Checkout
WHATSAPP_EVOLUTION_INSTANCE_WALLET=Checkout
WHATSAPP_EVOLUTION_INSTANCE_RENTALS=Rentals
```

The app maps Meta `phone_number_id` ↔ these logical instance names internally.

---

## 7. Deploy checklist

```bash
cd ~/public_html
git pull origin main
php artisan config:clear
```

Test:
1. Meta **Verify and save** on webhook — should succeed.
2. Send `Hi` to your WhatsApp business number — bot should reply.
3. Check `storage/logs/laravel.log` for `whatsapp.cloud` or `whatsapp.inbound` errors.

---

## 8. Login OTP without the user messaging you first

Free-form WhatsApp text (`type: text`) is **blocked** by Meta until the user opens a 24-hour session by messaging your business number. That is why OTP only arrived after they texted you.

Fix: send OTP as an **Authentication** template via Cloud API (`type: template`). The app does this automatically (`sendAuthenticationOtp`) when `WHATSAPP_OTP_TEMPLATE_NAME` is set.

### Create the template in Meta

1. [WhatsApp Manager](https://business.facebook.com/latest/whatsapp_manager) → **Message templates** → **Create template**
2. **Category:** Authentication (not Marketing / Utility — Meta rejects custom OTP wording)
3. **Name:** `checkoutnow_login_otp` (must match `.env`)
4. **Language:** English (`en`)
5. Use Meta’s fixed auth body (code as `{{1}}`). Add the **Copy code** button if offered.
6. Submit. Authentication templates are usually approved quickly.

```env
WHATSAPP_OTP_TEMPLATE_NAME=checkoutnow_login_otp
WHATSAPP_OTP_TEMPLATE_LANGUAGE=en
WHATSAPP_OTP_TEMPLATE_BUTTON=true
```

If your template has **no** copy-code button, set `WHATSAPP_OTP_TEMPLATE_BUTTON=false`.

Then:

```bash
php artisan config:clear
```

The login API sends the template first. If Meta rejects the name/language, it falls back to session text (only works after they messaged you), then email.

---

## 9. Proactive notifications (templates)

Meta only allows **template messages** outside the 24-hour customer service window.

- Interactive bot replies (user messaged first) work as normal text.
- Proactive alerts (top-up, P2P, inactive reminders) need **approved message templates** in WhatsApp Manager when using Cloud API.

Keep `WHATSAPP_PROACTIVE_NOTIFICATIONS_ENABLED=false` until templates are approved, or migrate alerts to template sends.

---

## 10. Switching back to Evolution

```env
WHATSAPP_PROVIDER=evolution
```

```bash
php artisan config:clear
php artisan whatsapp:configure-webhook --url='https://check-outpay.com/api/v1/whatsapp/webhook?secret=YOUR_SECRET'
```

---

## Quick reference

| Item | Where |
|------|--------|
| Callback URL | `https://{WHATSAPP_APP_URL host}/api/v1/whatsapp/webhook` |
| Verify token | `.env` `WHATSAPP_CLOUD_VERIFY_TOKEN` = Meta Configuration field |
| Access token | WhatsApp → API Setup (temp) or System User (prod) |
| Phone number ID | WhatsApp → API Setup |
| App secret | App settings → Basic |
| Full docs | [Meta Cloud API webhooks](https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/components) |
