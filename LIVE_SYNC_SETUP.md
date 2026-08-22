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

## Two commands (pick the right one)

| Goal | Command |
|------|---------|
| **Missing rows only** (manual gap-fill, never overwrite Contabo) | `live-sync:fill-gaps` |
| **Recent changes / balance refresh** (upsert, including float) | `live-sync:push` |

### Manual gap-fill — `live-sync:fill-gaps` (recommended for “data on live not on Contabo”)

Insert-only: probes Contabo, pushes rows that are **absent**, skips everything already there. Uses **batch HTTP** (25 events/request by default) and **id cursor** pagination so you do not re-run the same command 50 times.

`--entity=common` syncs the money path **except** renter/business/whatsapp_wallet balances (run `live-sync:push --entity=float` for those first).

Run on **Namecheap** only:

```bash
# Full money-path gap scan (safe, insert-only)
php artisan live-sync:fill-gaps --entity=common --until-done --sync

# One entity, optional date window
php artisan live-sync:fill-gaps --entity=payment --since=2026-01-01 --until-done --sync

# Resume after interrupt (use last id from output)
php artisan live-sync:fill-gaps --entity=payment --cursor=12000 --until-done --sync

# Dry-run: count missing without sending
php artisan live-sync:fill-gaps --entity=common --until-done --dry-run
```

Options:

| Flag | Default | Meaning |
|------|---------|---------|
| `--batch-size` | 500 | Rows scanned per page |
| `--chunk` | 25 | Events per HTTP batch (max 50) |
| `--cursor` | 0 | Start after this id |
| `--until-done` | off | Keep paging until exhausted |
| `--since` | (none) | Optional time filter; omit = full table |

**Does not** refresh balances. For float, use `live-sync:push` below.

### Push / refresh — `live-sync:push`

Catch-up for **recent window** (48h/watermark) or **balance upserts**:

```bash
# Bank float first (required for Ops site-float ≈ live)
php artisan live-sync:push --entity=float --mode=recent --force-all --limit=500 --sync --chunk=25

# Recent missing rows (48h window, batch HTTP)
php artisan live-sync:push --entity=common --mode=missing --sync --chunk=25

# Large backlog with pagination
php artisan live-sync:push --entity=payment --mode=missing --force-all --until-done --sync --chunk=25
```

`--mode=missing` skips rows Contabo already has. `--mode=recent` **overwrites** (needed for balances).

**Rate limits:** Contabo sync uses a dedicated `live_sync` limiter (600/min by HMAC key). Batch mode sends up to 25 rows per request, so effective throughput is much higher than row-by-row.

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

Receiver endpoints: `POST /api/v1/sync/live`, `/probe`, `/batch`.

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

Optional batch tuning:

```env
LIVE_SYNC_BATCH_CHUNK_SIZE=25
LIVE_SYNC_BATCH_MAX_EVENTS=50
```

```bash
php artisan migrate --force
php artisan config:clear
php artisan queue:work   # if QUEUE=true

# Typical manual run after deploy:
php artisan live-sync:push --entity=float --mode=recent --force-all --limit=500 --sync --chunk=25
php artisan live-sync:fill-gaps --entity=common --until-done --sync
```

Ongoing: configured models auto-push single-row changes when saved/deleted on Namecheap.
