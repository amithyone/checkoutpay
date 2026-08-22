# Live Sync (Namecheap → Contabo)

Namecheap is the **live source of truth**. Contabo receives signed upserts for the **common money path**.

## What syncs (`--entity=common`)

| Area | Entities |
|------|----------|
| Core | `renter`, `business`, `business_website`, `payment`, `account_number` |
| Wallets | `whatsapp_wallet` (balances), `whatsapp_wallet_transaction` |
| Payouts | `withdrawal_request`, `business_transaction`, `business_activity_log` |
| Payroll | `business_employee`, `business_disbursement_batch`, `business_disbursement_item`, `business_withdrawal_account` |
| Cards / Mevon | `virtual_card_request`, `mevon_pay_ledger_entry` |
| Savings | `wallet_savings_setting`, `wallet_savings_goal`, `wallet_savings_lock` |

Still **not** synced: admins, sessions, cache, jobs, nigtax, rentals catalog, desktop telemetry, chat, etc.

Catch-up is **missing-only** by default (probe Contabo, push gaps only, 48h/watermark window, `--limit`).

### Bank float / Ops (`/enter0/audits`)

Site float = non-exempt `business` + `whatsapp_wallet` + `renter` balances.  
**`--mode=missing` does not refresh those** if Contabo already has the rows (old dump = stale ₦3.1M while live is ~₦3.7M).

On **Namecheap**, force a balance upsert:

```bash
php artisan live-sync:push --entity=float --mode=recent --force-all --limit=500 --sync
```

Then re-check Contabo `/enter0/audits` (site float should track live).

**Rate limits:** Contabo sync uses a dedicated `live_sync` limiter (600/min by HMAC key), not the generic API 60/min. If you still see `Too Many Attempts`, wait 1 minute and re-run, or use `--delay-ms=100`.

## Contabo (receiver)

```env
LIVE_SYNC_ENABLED=true
LIVE_SYNC_TRANSMIT_ENABLED=false
LIVE_SYNC_KEY_ID=live-site-1
LIVE_SYNC_SECRET=<same-secret>
```

```bash
php artisan migrate --force
php artisan config:clear
```

## Namecheap (transmitter)

```env
LIVE_SYNC_ENABLED=false
LIVE_SYNC_TRANSMIT_ENABLED=true
LIVE_SYNC_RECEIVER_URL=https://check-outnow.com/api/v1/sync/live
LIVE_SYNC_RECEIVER_PATH=/api/v1/sync/live
LIVE_SYNC_KEY_ID=live-site-1
LIVE_SYNC_SECRET=<same-secret>
LIVE_SYNC_SOURCE_NAME=namecheap-live
LIVE_SYNC_QUEUE=true
```

```bash
php artisan migrate --force
php artisan config:clear
php artisan queue:work   # if QUEUE=true

# Bank float first (required for Ops site-float ≈ live)
php artisan live-sync:push --entity=float --mode=recent --force-all --limit=500 --sync

# Then other money-path gaps (accounts, payments, ledger…)
php artisan live-sync:push --entity=common --mode=missing --sync
```

Ongoing: configured models auto-push single-row changes when saved/deleted on Namecheap.  
Balance changes on Namecheap only land on Contabo if transmitters + observers are enabled there.

