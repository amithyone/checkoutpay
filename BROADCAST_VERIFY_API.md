# Checkout Broadcast verify API (production)

Native / POS base URL:

```
EXPO_PUBLIC_CHECKOUT_BROADCAST_API=https://check-outpay.com/api/v1/broadcast
```

Laravel-native implementation of the open `checkout_broadcast` bank_api (Option B).

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
php artisan migrate --force --path=database/migrations/2026_07_18_140000_create_broadcast_terminals_tables.php
```

Set in `.env` (then `php artisan config:clear`):

```env
BROADCAST_ADMIN_KEY=long-random-secret
BROADCAST_RATE_LIMIT_VERIFY=120
```

## Register a test terminal

```bash
curl -sS -X POST 'https://check-outpay.com/api/v1/broadcast/terminals/register' \
  -H 'Content-Type: application/json' \
  -H "X-Admin-Key: $BROADCAST_ADMIN_KEY" \
  -d '{
    "terminal_id": "POS-DEMO-001",
    "signing_key": "your-terminal-secret-min-16-chars",
    "merchant_name": "Demo Shop",
    "bank_name": "CheckoutPay",
    "masked_account_suffix": "***1234",
    "account_number": "0123456789",
    "recipient_bank_code": "058",
    "business_id": null
  }'
```

Optional: set `business_id` to link the terminal to an existing `businesses` row.

## CLI (from checkout_broadcast repo)

```bash
export CHECKOUT_BANK_ADMIN_KEY="…"
export CHECKOUT_SIGNING_KEY="your-terminal-secret-min-16-chars"
PYTHONPATH="sdk/python:." python -m checkout_broadcast.cli register-terminal \
  --bank-url https://check-outpay.com/api/v1/broadcast

PYTHONPATH="sdk/python:." python -m checkout_broadcast.cli demo-send \
  --amount 2500 --bank-url https://check-outpay.com/api/v1/broadcast
```

## Security

- HTTPS only in production
- Keep `BROADCAST_ADMIN_KEY` and terminal `signing_key` out of git
- Verify endpoint is rate-limited per IP
- Session UUIDs are persisted in `broadcast_used_sessions` (replay protection)
