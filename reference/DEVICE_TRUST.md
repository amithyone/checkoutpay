# Device trust (backend)

Consumer mobile API at `/api/v1/consumer/auth/*` implements WebAuthn passkeys, new-device step-up (BVN + OTP), and a 24-hour high-value transfer lock after binding a new trusted device.

## Tables

| Table | Purpose |
|-------|---------|
| `consumer_trusted_devices` | Bound device label/platform per `consumer_wallet_api_accounts` row |
| `consumer_passkey_credentials` | WebAuthn credential record JSON + counter |
| `consumer_device_stepup_sessions` | BVN/OTP step-up state between login and `device/bind` |
| `consumer_wallet_api_accounts.transfer_lock_until` | High-value transfer lock expiry |

## Config (`config/consumer_wallet.php`)

| Key | Default | Notes |
|-----|---------|-------|
| `device_trust_enabled` | `true` | Master switch |
| `webauthn_rp_id` | `check-outpay.com` | Must match mobile associated domains |
| `webauthn_rp_name` | `CheckoutNow` | RP display name |
| `high_value_single_transfer_cap` | `10000` | NGN; blocked while lock active |
| `transfer_lock_hours` | `24` | Set on `device/bind` with `revoke_others: true` |

Env overrides: `CONSUMER_DEVICE_TRUST_ENABLED`, `CONSUMER_WEBAUTHN_RP_ID`, `CONSUMER_WEBAUTHN_RP_NAME`, `CONSUMER_HIGH_VALUE_SINGLE_TRANSFER_CAP`, `CONSUMER_TRANSFER_LOCK_HOURS`.

## Services

- `ConsumerWebAuthnService` — register/login ceremonies via `WebauthnSerializerFactory` + `CeremonyStepManagerFactory`
- `ConsumerDeviceStepupService` — BVN check (`WhatsappWalletPinResetService` / `kyc_bvn`), OTP via `ConsumerWalletOtpService`
- `ConsumerDeviceTrustService` — step-up detection, revoke tokens/FCM/passkeys, transfer lock enforcement

## Behaviour

1. **First device** — PIN/OTP login issues token; client registers passkey while authenticated.
2. **Second device** — PIN/OTP returns `403` with `stepup_required`, `stepup_session`, `other_device_label`, `channels`.
3. **Step-up** — BVN → OTP → `POST auth/device/bind/options` (WebAuthn creation options) → passkey → `POST auth/device/bind` with `revoke_others: true` revokes prior device sessions and sets `transfer_lock_until`.
4. **Passkey login** — Same bound device does **not** reset transfer lock.
5. **Transfers** — `POST transfers/p2p` and `POST transfers/bank` reject `amount > cap` with `403` while lock is active.
6. **Wallet** — `GET wallet` includes `transfer_lock_until`, `high_value_single_transfer_cap`, `high_value_transfer_blocked`.

Mobile contract: [`checkoutnow/docs/native/DEVICE_TRUST.md`](../../checkoutnow/docs/native/DEVICE_TRUST.md).

## Deploy

```bash
composer install --no-dev -o   # web-auth/* already in composer.json
php artisan migrate --force
```

Set `CONSUMER_WEBAUTHN_RP_ID=check-outpay.com` (or staging host) to match app associated domains / asset links.
