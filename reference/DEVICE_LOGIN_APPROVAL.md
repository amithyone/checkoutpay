# Device login push approval (backend)

Trusted device approves a **new phone sign-in** via FCM push instead of BVN + OTP on the new device.

Mobile contract: extend step-up flow in [`checkoutnow/docs/native/DEVICE_TRUST.md`](../../checkoutnow/docs/native/DEVICE_TRUST.md).

---

## Flow

1. New device: PIN/OTP → `403` with `stepup_required` + `push_approval_available: true` (when trusted device has FCM token).
2. New device: `POST device/stepup/push/request` → push sent to trusted device.
3. Trusted device: user opens notification → `POST device/stepup/push/approve` with wallet PIN (or `deny`).
4. New device: polls `GET device/stepup/push/status` until `approved` → receives `stepup_token`.
5. New device: `device/bind/options` + `device/bind` (same as BVN+OTP path).

---

## API (`/api/v1/consumer/auth/`)

| Method | Auth | Path | Body / query |
|--------|------|------|----------------|
| POST | No | `device/stepup/push/request` | `{ stepup_session }` |
| GET | No | `device/stepup/push/status` | `?stepup_session=` |
| POST | Sanctum | `device/stepup/push/approve` | `{ approval_id, pin }` |
| POST | Sanctum | `device/stepup/push/deny` | `{ approval_id }` |

### Step-up start / PIN / OTP responses (extra fields)

```json
{
  "push_approval_available": true,
  "push_approval_expires_at": "2026-06-22T12:30:00Z"
}
```

`push_approval_available` is `true` when the account has an active passkey device **and** a registered native FCM token.

### Push request response

```json
{
  "sent": true,
  "approval_id": "appr_…",
  "expires_at": "2026-06-22T12:05:00Z",
  "polling_interval_seconds": 3
}
```

### Status response

| `status` | Meaning |
|----------|---------|
| `not_requested` | Client has not called push/request yet |
| `pending` | Waiting for trusted device |
| `approved` | Includes `stepup_token` for bind flow |
| `denied` | Trusted device rejected |
| `expired` | TTL elapsed; use BVN+OTP or request again |

### FCM data payload (trusted device)

```json
{
  "type": "device_login_approval",
  "screen": "device_approval",
  "approval_id": "appr_…",
  "wallet_id": "123"
}
```

---

## Config (`config/consumer_wallet.php`)

| Key | Default |
|-----|---------|
| `device_stepup_push_enabled` | `true` |
| `device_stepup_push_ttl_minutes` | `5` |
| `device_stepup_push_poll_seconds` | `3` |
| `device_stepup_push_title` | `New sign-in attempt` |
| `device_stepup_push_channel` | `wallet_alerts` |

Env: `CONSUMER_DEVICE_STEPUP_PUSH_ENABLED`, `CONSUMER_DEVICE_STEPUP_PUSH_TTL_MINUTES`, `CONSUMER_DEVICE_STEPUP_PUSH_POLL_SECONDS`.

---

## Tables

| Table | Purpose |
|-------|---------|
| `consumer_device_login_approvals` | Pending/resolved approval rows linked to `consumer_device_stepup_sessions` |

---

## Services

- `ConsumerDeviceStepupPushService` — request, poll, approve, deny
- Uses `PushNotificationService::PROFILE_CHECKOUTNOW` for FCM
- Approve verifies wallet PIN and issues `stepup_token` on the step-up session (skips BVN+OTP)

Tests: `tests/Feature/Api/ConsumerDeviceStepupPushTest.php`
