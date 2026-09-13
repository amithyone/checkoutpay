# Xtrapay local API

Laravel wallet backend adapted from CheckoutPay for the Xtrapay React app.

## Start

```bash
cd /Users/amithy/Documents/xtrapaybackend
php artisan serve --host=127.0.0.1 --port=8000
```

Base URL: `http://127.0.0.1:8000/api/v1/xtrapay`

Env file is **`.error`** (project convention), SQLite at `database/xtrapay.sqlite`.

## Demo credentials

| Field | Value |
|-------|--------|
| Phone | `08034129981` |
| Password | `password` |
| PIN | `1234` |
| OTP (local) | `123456` |

## Key routes

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/v1/xtrapay` | Public status |
| POST | `/api/v1/xtrapay/auth/login` | Public |
| POST | `/api/v1/xtrapay/auth/register` | Public |
| POST | `/api/v1/xtrapay/auth/kyc` | Public |
| POST | `/api/v1/xtrapay/auth/otp/send` | Public |
| POST | `/api/v1/xtrapay/auth/otp/verify` | Public |
| GET | `/api/v1/xtrapay/bootstrap` | Bearer |
| GET | `/api/v1/xtrapay/wallets` | Bearer |
| GET | `/api/v1/xtrapay/transactions` | Bearer |
| POST | `/api/v1/xtrapay/transfers` | Bearer + PIN |
| GET | `/api/v1/xtrapay/limits` | Bearer |
| GET | `/api/v1/xtrapay/network/rails` | Bearer |

Full contract: see `BACKEND.md`.

## Login smoke test

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/xtrapay/auth/login \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"identifier":"08034129981","password":"password"}'
```

Then call bootstrap with `Authorization: Bearer <accessToken>`.

## Notes

- Existing CheckoutPay `/api/v1/consumer/*` wallet routes remain intact for the older app.
- Xtrapay tables are isolated (`xtrapay_*`) so local SQLite does not need the full MySQL schema.
- Next: wire the Vite app `VITE_API_URL=http://127.0.0.1:8000/api/v1/xtrapay`.
