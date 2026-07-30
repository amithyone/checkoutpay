# Pay at shop — POS integration checklist

This document is the single source of truth for **what the POS must do** vs what the **mobile app** and **CheckoutPay server** handle.

## The loop

```
┌─────────────┐   signed BLE (hash + suffix only)   ┌──────────────┐
│  POS / till │ ──────────────────────────────────► │ Customer app │
└─────────────┘                                     └──────┬───────┘
       ▲                                                   │
       │ poll GET /sessions/{uuid}                        │ POST /verify-broadcast
       │ until paid                                       ▼
       │                                            ┌──────────────┐
       └────────────────────────────────────────────│ CheckoutPay  │
                                                    └──────────────┘
                                                           │
                                                    customer bank transfer
                                                    (idempotency_key = session_uuid)
```

1. **POS** broadcasts amount, `terminal_id`, `bank_name_hash`, `masked_account_suffix`, `session_uuid_v4`, signed with **Ed25519**.
2. **Phone** reads BLE GATT and POSTs the **unchanged** signed packet to verify.
3. **Server** returns merchant name, full settlement account, bank code, `session_status: open`.
4. **Customer** pays in CheckoutNow (transfer uses `idempotency_key` = `session_uuid`).
5. **POS** polls session status until `paid` or cashier cancels.

## What goes in BLE (POS credentials)

| Field | Source | Notes |
|-------|--------|-------|
| `terminal_id` | Pay at shop dashboard | e.g. `CP-1RK8Z` |
| `signature_alg` | Always `ed25519` | Not HMAC |
| Signing key | Dashboard (shown once) | Ed25519 private key — keep secret |
| `bank_name_hash` | `sha256:` + SHA256(lowercase bank name) | Bank name from dashboard, e.g. `RUBIES MFB` |
| `masked_account_suffix` | Dashboard | e.g. `***4863` — last 4 digits only |
| `timestamp_ms` | POS clock | Set **before** signing; refresh on each broadcast |
| `session_uuid_v4` | POS generates once per checkout | Reuse until paid/cancelled |

## What must NOT be in BLE

- Full account number
- Merchant name in plaintext (server returns it after verify)
- NIP bank code (server returns it after verify)

## Server endpoints

Base URL: `https://check-outpay.com/api/v1/broadcast`

| Endpoint | Who | Auth |
|----------|-----|------|
| `POST /verify-broadcast` | Mobile app | None (rate-limited) |
| `GET /sessions/{uuid}?terminal_id=…` | POS | `X-Terminal-Api-Key: bk_…` |
| `POST /sessions/cancel` | POS | Body: `{ session_uuid_v4, terminal_id }` |

### Verify success (mobile app receives)

```json
{
  "valid": true,
  "merchant_name": "MIDAS AGRO",
  "recipient_account_name": "MIDAS AGRO",
  "amount_ngn": 1500,
  "bank_name": "RUBIES MFB",
  "masked_account_suffix": "***4863",
  "session_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "session_status": "open",
  "terminal_id": "CP-1RK8Z",
  "recipient_account": "1000004863",
  "recipient_bank_code": "090175"
}
```

### Session status (POS polls)

```bash
curl -sS "https://check-outpay.com/api/v1/broadcast/sessions/SESSION-UUID?terminal_id=CP-1RK8Z" \
  -H "X-Terminal-Api-Key: bk_your_api_key"
```

| `session_status` | Meaning |
|------------------|---------|
| `awaiting_scan` | No customer has verified yet |
| `open` | Customer saw payment screen, not paid yet |
| `paid` | Transfer completed — close sale on POS |
| `cancelled` | Cashier cancelled — start new session UUID |

## Common POS mistakes

| Symptom | Fix |
|---------|-----|
| `Bank name hash mismatch` | Set bank name in POS to **exact** dashboard value (`RUBIES MFB`, not `kuda` or `CheckoutPay`) |
| `Invalid signature` | Use `ed25519` + dashboard signing key; sign canonical JSON payload |
| `Missing timestamp_ms` | Set `timestamp_ms` before signing |
| Wrong account on phone | Do not embed account in BLE — server reads business settlement account |
| POS never sees paid | Poll `GET /sessions/{uuid}` with API key; ensure app sends `idempotency_key` = session UUID |
| `Session already paid` | Generate new `session_uuid_v4` for next sale |

## Session UUID rules

- Generate one UUID when cashier starts checkout.
- Reuse the **same** UUID while broadcasting (refresh `timestamp_ms` only via `refreshCheckout()`).
- After `paid` or `cancelled`, generate a **new** UUID for the next customer.

## Example: MIDAS AGRO (terminal CP-1RK8Z)

| Setting | Value |
|---------|-------|
| Terminal ID | `CP-1RK8Z` |
| Signature alg | `ed25519` |
| Bank name (for hash) | `RUBIES MFB` |
| Bank name hash | `sha256:37380fe7d914f88b21086ad36cd0a5d7116bb6fbc49daa8730255bbc202eca4d` |
| Masked suffix | `***4863` |
| Settlement account | `1000004863` / NIP `090175` (server-side only) |

## POS SDK notes (checkout_broadcast)

- `sendCheckout()` — new session or reuse open session
- `refreshCheckout()` — new timestamp, same session UUID
- `cancelCheckout()` — calls `/sessions/cancel`
- Poll session status with terminal API key until `paid`

See also: `BROADCAST_VERIFY_API.md` for full API contract.
