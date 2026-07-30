# Checkout Broadcast verify API (production)

Native / POS base URL:

```
EXPO_PUBLIC_CHECKOUT_BROADCAST_API=https://check-outpay.com/api/v1/broadcast
```

CheckoutNow **Pay at shop** posts signed BLE packets to `POST /verify-broadcast`. Merchant POS terminals use **Ed25519**; the open `checkout_broadcast` SDK (v2.0) still supports **HMAC-SHA256**.

## Endpoints

| Endpoint | Method | Auth |
|----------|--------|------|
| `/api/v1/broadcast/health` | GET | Public |
| `/api/v1/broadcast/verify-broadcast` | POST | Public (rate-limited) |
| `/api/v1/broadcast/terminals/register` | POST | `X-Admin-Key` |
| `/api/v1/broadcast/terminals` | GET | `X-Admin-Key` |
| `/api/v1/broadcast/terminals/{id}` | GET | `X-Admin-Key` |

## Live deploy

```bash
cd ~/public_html
git pull
php artisan migrate --force
php artisan config:clear
```

Set in `.env`:

```env
BROADCAST_ADMIN_KEY=long-random-secret
BROADCAST_RATE_LIMIT_VERIFY=120
```

## Terminal credentials (CheckoutNow / POS)

When you register a terminal (`POST /terminals/register` with `signature_alg: ed25519`), the API returns the credentials to provision on the POS:

| Field | Description |
|-------|-------------|
| **terminal_id** | Unique POS identifier, e.g. `TERM-001` |
| **merchant_id** | Merchant identifier, e.g. `MCH-TERM-001` |
| **api_key** | Terminal API key (`bk_…`) for CheckoutPay integrations |
| **signing_key** | Ed25519 private key (base64) — **shown once** at registration; store on POS only |

The server stores only the **public key** for Ed25519 verification.

### Register a terminal (Ed25519 — CheckoutNow default)

```bash
curl -sS -X POST 'https://check-outpay.com/api/v1/broadcast/terminals/register' \
  -H 'Content-Type: application/json' \
  -H "X-Admin-Key: $BROADCAST_ADMIN_KEY" \
  -d '{
    "terminal_id": "TERM-001",
    "signature_alg": "ed25519",
    "merchant_name": "Amithy Store",
    "bank_name": "GTBank",
    "masked_account_suffix": "***1234",
    "account_number": "0123456789",
    "recipient_bank_code": "058"
  }'
```

Response includes `terminal_id`, `merchant_id`, `api_key`, and `signing_key` (one-time).

### Register a terminal (HMAC-SHA256 — checkout_broadcast SDK v2.0)

```bash
curl -sS -X POST 'https://check-outpay.com/api/v1/broadcast/terminals/register' \
  -H 'Content-Type: application/json' \
  -H "X-Admin-Key: $BROADCAST_ADMIN_KEY" \
  -d '{
    "terminal_id": "POS-DEMO-001",
    "signature_alg": "HMAC-SHA256",
    "signing_key": "your-terminal-secret-min-16-chars",
    "merchant_name": "Demo Shop",
    "bank_name": "CheckoutPay",
    "masked_account_suffix": "***1234",
    "account_number": "0123456789",
    "recipient_bank_code": "058"
  }'
```

## Verify-broadcast contract (CheckoutNow app)

**Request:** `POST /api/v1/broadcast/verify-broadcast` — no auth header.

```json
{
  "payload": {
    "protocol_version": 1,
    "timestamp_ms": 1738123456789,
    "session_uuid_v4": "550e8400-e29b-41d4-a716-446655440000",
    "terminal_id": "TERM-001",
    "transaction_details": {
      "currency_code": "NGN",
      "total_amount_ngn": 5000,
      "item_count": 3
    },
    "account_info_public_display": {
      "bank_name_hash": "sha256:…",
      "masked_account_suffix": "***1234"
    }
  },
  "signature_alg": "ed25519",
  "signature": "<base64-or-hex>"
}
```

**Success (200):**

```json
{
  "valid": true,
  "merchant_name": "Amithy Store",
  "amount_ngn": 5000,
  "masked_account_suffix": "***1234",
  "session_uuid": "550e8400-e29b-41d4-a716-446655440000",
  "terminal_id": "TERM-001",
  "recipient_account": "0123456789",
  "recipient_bank_code": "058"
}
```

**Failure:**

```json
{ "valid": false, "error": "Invalid signature" }
```

HTTP 429 when rate-limited.

## Monitoring (server logs)

Every `POST /verify-broadcast` attempt is written to:

```
storage/logs/broadcast-verify-YYYY-MM-DD.log
```

Each line includes client IP, `User-Agent`, `terminal_id`, `session_uuid`, `amount_ngn`, `valid`, and `error` (if any). Use `User-Agent` to distinguish the CheckoutNow native app from curl or web tests.

Successful verifications are also recorded in the `broadcast_used_sessions` table (replay protection).

```sql
SELECT session_uuid, terminal_id, FROM_UNIXTIME(used_at / 1000) AS used_at
FROM broadcast_used_sessions
ORDER BY used_at DESC
LIMIT 20;
```

## Security

- HTTPS only in production
- Keep `BROADCAST_ADMIN_KEY` and terminal `signing_key` out of git
- Verify endpoint is rate-limited per IP
- Session UUIDs are persisted in `broadcast_used_sessions` (replay protection)
- Timestamp window: 10 minutes

## CLI (HMAC terminals — checkout_broadcast repo)

```bash
export CHECKOUT_BANK_ADMIN_KEY="…"
export CHECKOUT_SIGNING_KEY="your-terminal-secret-min-16-chars"
PYTHONPATH="sdk/python:." python -m checkout_broadcast.cli register-terminal \
  --bank-url https://check-outpay.com/api/v1/broadcast
```
