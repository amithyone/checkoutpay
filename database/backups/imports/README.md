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

---

## Form Responses → wallet sterilize / seed

Source: `use data - Form Responses 1.csv`

```bash
# 1) Sterilize + confirm names via bank name enquiry
php artisan wallet:sterilize-form-csv
# optional: --skip-name-enquiry  --limit=20

# Outputs under database/backups/imports/sterilized/
#   form-responses-sterilized.jsonl
#   form-responses-report.csv
#   form-responses-summary.json

# 2) Dry-run seed (skip existing phones; Tier 1 + KYC/BVN stored)
php artisan wallet:seed-form-sterilized --dry-run

# 3) Apply on Contabo after reviewing the report
php artisan wallet:seed-form-sterilized --apply

# 4) Optional later: queue Mevon Tier-2 VA for BVN rows
php artisan wallet:seed-form-sterilized --apply --provision-tier2
```

Bank aliases: `config/wallet_import_banks.php`.

### Gradual Tier-2 VA (cron)

After seeding, do **not** run `--provision-tier2` on the full set. Use the batch command twice daily:

```bash
# Dry-run
php artisan wallet:provision-tier2-batch --limit=8

# Queue up to 8 Mevon personal VAs this run
php artisan wallet:provision-tier2-batch --limit=8 --apply
```

Suggested Contabo crontab (Africa/Lagos): **8 wallets at 09:00 and 18:00** ≈ 16/day → ~408 ready BVN rows in ~26–30 days:

```cron
0 9,18 * * * cd /var/www/checkout && php artisan wallet:provision-tier2-batch --limit=8 --apply >> storage/logs/tier2-batch.log 2>&1
```

Use `--limit=5` for slower pace (~10/day) or `--limit=10` for faster (~20/day).

### cron-job.org (HTTP)

Same token as other cron URLs (`CRON_EMAIL_FETCH_TOKEN` in `.env`). Replace `YOUR_CRON_TOKEN` and use your live host (`check-outpay.com` or `check-outnow.com`).

**Morning — queue up to 8 Tier-2 VAs (09:00 Africa/Lagos)**

```
https://check-outpay.com/cron/wallet/provision-tier2-batch?token=YOUR_CRON_TOKEN&limit=8&apply=1
```

**Evening — same run (18:00 Africa/Lagos)**

```
https://check-outpay.com/cron/wallet/provision-tier2-batch?token=YOUR_CRON_TOKEN&limit=8&apply=1
```

**Process the KYC queue after each batch** (queues jobs; this endpoint runs the worker):

```
https://check-outpay.com/cron/process-kyc-queue?token=YOUR_CRON_TOKEN
```

Optional dry-run (no Mevon calls):

```
https://check-outpay.com/cron/wallet/provision-tier2-batch?token=YOUR_CRON_TOKEN&limit=8&apply=0
```

On cron-job.org: create two jobs (09:00 and 18:00), timezone **Africa/Lagos**, method **GET**, timeout **120s**. Run the KYC queue URL ~2 minutes after each batch job.
