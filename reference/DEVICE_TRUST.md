# Device trust (backend)

Consumer mobile API at `/api/v1/consumer/auth/*` implements WebAuthn passkeys, new-device step-up (BVN + OTP), and a 24-hour high-value transfer lock after binding a new trusted device.

Mobile contract (client setup, UI, checklist): [`checkoutnow/docs/native/DEVICE_TRUST.md`](../../checkoutnow/docs/native/DEVICE_TRUST.md).

---

## Implementation status

| Area | Status |
|------|--------|
| Passkey register / login | **Live** — `ConsumerDeviceAuthController`, `ConsumerWebAuthnService` |
| Step-up (BVN + OTP + bind) | **Live** — `ConsumerDeviceStepupService`, `ConsumerDeviceTrustService` |
| Transfer lock on bind | **Live** — `transfer_lock_until`, P2P + bank transfer enforcement |
| Device list / revoke | **Live** — `GET/DELETE auth/devices` |
| Push approval on step-up | **Live** — `ConsumerDeviceStepupPushService`, [`DEVICE_LOGIN_APPROVAL.md`](./DEVICE_LOGIN_APPROVAL.md) |

---

## API routes (`/api/v1/consumer/auth/`)

Public (no token):

| Method | Path | Controller method |
|--------|------|-------------------|
| POST | `passkey/login/options` | `passkeyLoginOptions` |
| POST | `passkey/login/verify` | `passkeyLoginVerify` |
| POST | `device/stepup/start` | `stepupStart` |
| POST | `device/stepup/push/request` | `stepupPushRequest` |
| GET | `device/stepup/push/status` | `stepupPushStatus` |
| POST | `device/stepup/bvn` | `stepupBvn` |
| POST | `device/stepup/otp/request` | `stepupOtpRequest` |
| POST | `device/stepup/otp/verify` | `stepupOtpVerify` |
| POST | `device/bind/options` | `bindOptions` |
| POST | `device/bind` | `bindDevice` |

Authenticated (`auth:sanctum`):

| Method | Path | Controller method |
|--------|------|-------------------|
| POST | `passkey/register/options` | `passkeyRegisterOptions` |
| POST | `passkey/register/verify` | `passkeyRegisterVerify` |
| POST | `device/stepup/push/approve` | `stepupPushApprove` |
| POST | `device/stepup/push/deny` | `stepupPushDeny` |
| GET | `devices` | `listDevices` |
| DELETE | `devices/{id}` | `revokeDevice` |

PIN/OTP login step-up: `POST auth/pin/verify` and `POST auth/otp/verify` return **403** with `data.stepup_required`, `stepup_session`, `other_device_label`, `channels` when another passkey device is active.

Wallet lock fields: `GET consumer/wallet` includes `transfer_lock_until`, `high_value_single_transfer_cap`, `high_value_transfer_blocked`.

---

## Tables

| Table | Purpose |
|-------|---------|
| `consumer_trusted_devices` | Bound device label/platform per `consumer_wallet_api_accounts` row |
| `consumer_passkey_credentials` | WebAuthn credential record JSON + counter |
| `consumer_device_stepup_sessions` | BVN/OTP step-up state between login and `device/bind` |
| `consumer_wallet_api_accounts.transfer_lock_until` | High-value transfer lock expiry |

---

## Config (`config/consumer_wallet.php`)

| Key | Default | Notes |
|-----|---------|-------|
| `device_trust_enabled` | `true` | Master switch |
| `webauthn_rp_id` | `check-outpay.com` | Must match mobile associated domains |
| `webauthn_rp_name` | `CheckoutNow` | RP display name |
| `high_value_single_transfer_cap` | `10000` | NGN; blocked while lock active |
| `transfer_lock_hours` | `24` | Set on `device/bind` with `revoke_others: true` |

Env: `CONSUMER_DEVICE_TRUST_ENABLED`, `CONSUMER_WEBAUTHN_RP_ID`, `CONSUMER_WEBAUTHN_RP_NAME`, `CONSUMER_HIGH_VALUE_SINGLE_TRANSFER_CAP`, `CONSUMER_TRANSFER_LOCK_HOURS`.

---

## Server rules

1. On `device/bind` with `revoke_others: true`: revoke other Sanctum tokens, passkeys, and FCM tokens on prior devices.
2. Set `transfer_lock_until = now + transfer_lock_hours`.
3. Reject P2P/bank transfers with `amount > high_value_single_transfer_cap` while lock active → **403** + lock metadata.
4. Passkey login on the **same** bound device must **not** reset the lock (`resetTransferLock: false` in `passkeyLoginVerify`).

---

## Services

- `ConsumerWebAuthnService` — register/login ceremonies (`web-auth/webauthn-lib`)
- `ConsumerDeviceStepupService` — BVN (`kyc_bvn`), OTP (`ConsumerWalletOtpService`)
- `ConsumerDeviceTrustService` — step-up detection, revoke tokens/FCM/passkeys, transfer lock enforcement

---

## Deploy

```bash
composer install --no-dev -o   # web-auth/webauthn-lib must be in vendor/
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
```

Passkey **500** on production almost always means `composer install` was skipped — see [PASSKEY_WEBAUTHN.md](./PASSKEY_WEBAUTHN.md).

Set `CONSUMER_WEBAUTHN_RP_ID=check-outpay.com` to match app associated domains / asset links.

Tests: `tests/Feature/Api/ConsumerDeviceTrustTest.php`, `tests/Feature/Api/ConsumerDeviceStepupPushTest.php`, `tests/Unit/Consumer/ConsumerDeviceTrustServiceTest.php`.
