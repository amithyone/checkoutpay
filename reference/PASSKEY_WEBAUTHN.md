# Passkey / WebAuthn backend

Mobile shows *“Checkout is temporarily unavailable”* when passkey endpoints return **5xx**. This doc covers server setup and verification.

---

## Symptom

| Client action | API | Expected | Broken server |
|---------------|-----|----------|---------------|
| Profile → passkey ON | `POST consumer/auth/passkey/register/options` | 200 | **500** or **503** |
| Login → passkey | `POST consumer/auth/passkey/login/options` | 200 | **500** or **503** |
| New device bind | `POST consumer/auth/device/bind/options` | 200 | **500** or **503** |

Example error when Composer packages missing:

```text
Class "Webauthn\AttestationStatement\AttestationStatementSupportManager" not found
```

---

## Fix on production

```bash
cd /path/to/checkout   # Laravel app root
composer install --no-dev -o
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

Required packages (already in `composer.json`):

- `web-auth/webauthn-lib` (^5.3)
- `web-auth/cose-lib` (^4.5)
- `symfony/serializer`, `symfony/property-info`, `phpdocumentor/reflection-docblock`

**.env:**

```env
CONSUMER_DEVICE_TRUST_ENABLED=true
CONSUMER_WEBAUTHN_RP_ID=check-outpay.com
CONSUMER_WEBAUTHN_RP_NAME=CheckoutNow
```

---

## Code behaviour (after fix)

- `ConsumerWebAuthnService` **lazy-loads** WebAuthn (no crash in constructor if vendor missing).
- Missing packages → **503** JSON with explicit `message` (not uncaught 500).
- Packages installed → 200 with WebAuthn options JSON.

---

## Verify

```bash
BASE="https://check-outpay.com/api/v1"

curl -sS -X POST "$BASE/consumer/auth/passkey/login/options" \
  -H "Content-Type: application/json" \
  -d '{"phone":"08012345678"}' | jq .
```

With no passkey registered: **422** *“No passkey registered”* (good — WebAuthn works).  
With packages missing: **503** *“Passkeys are not configured…”*  
Must **not** return **500**.

---

## Related

- [DEVICE_TRUST.md](./DEVICE_TRUST.md) — step-up, bind, transfer lock  
- [DEVICE_LOGIN_APPROVAL.md](./DEVICE_LOGIN_APPROVAL.md) — push approval on new device  

Mobile contract: [`checkoutnow/docs/native/DEVICE_TRUST.md`](../../checkoutnow/docs/native/DEVICE_TRUST.md)
