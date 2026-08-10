# Legacy payment import (checzspw_payment)

Serialized from `database/backups/checzspw_payment.sql` → `transactions`.

Prepared CSVs live on the server at `storage/app/payment-imports/` (not committed — too large).

| File | Rows |
|------|------|
| `checzspw_transactions_all.csv` | 115,382 |
| `checzspw_transactions_approved.csv` | 52,891 (legacy `success`) |
| `checzspw_transactions_pending.csv` | 62,491 |
| `checzspw_transactions_sample_100.csv` | 100 approved (safe test) |
| `checzspw_transactions_approved.csv.gz` | gzipped approved set (~3.6 MB) |

## Status mapping

- `pending` → `pending`
- `success` → `approved`
- `failed` → `rejected`

## Admin

`/enter0/payments/import` — pick a prepared file or upload CSV / CSV.GZ.

Do **not** tick “credit balances” unless you intentionally want to increase the merchant wallet for historical approved rows.
