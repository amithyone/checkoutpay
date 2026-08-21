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

# Common money path (wallets + balances + payments + activity…)
php artisan live-sync:push --entity=common --mode=missing --sync

# Or one entity
php artisan live-sync:push --entity=whatsapp_wallet --mode=missing --sync
```

Ongoing: configured models auto-push single-row changes when saved/deleted on Namecheap.
