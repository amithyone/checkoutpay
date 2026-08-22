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

## Three workflows

| Goal | Command |
|------|---------|
| **One-time backfill** (first run / re-sync entity) | `live-sync:fill-gaps --until-done --sync` |
| **Incremental** (new rows only, uses saved cursor) | `live-sync:incremental --sync` or cron |
| **Balance refresh** (overwrite float) | `live-sync:push --entity=float --mode=recent` |

### Per-table checkpoints

Namecheap stores **`live_sync_outbound_cursors`** — one row per entity with `last_origin_id` and `status` (`backfill` \| `caught_up`).

- **First backfill**: scans from id 0, probes Contabo for missing rows, saves cursor after each page.
- **After `caught_up`**: next run starts at saved cursor, **no probe**, pushes only `id > cursor` (insert-only on Contabo).
- **Real-time observers** also advance the cursor when a row is pushed.

Check progress:

```bash
php artisan live-sync:status
```

### One-time backfill — `live-sync:fill-gaps`

Run on **Namecheap** only. `--entity=common` skips float balance tables (use `live-sync:push --entity=float` first).

```bash
php artisan live-sync:push --entity=float --mode=recent --force-all --limit=500 --sync --chunk=25
php artisan live-sync:fill-gaps --entity=common --until-done --sync
```

| Flag | Meaning |
|------|---------|
| `--until-done` | Page until table end; marks entity `caught_up` |
| `--reset-cursor` | Restart entity from id 0 |
| `--cursor=N` | Override saved cursor for this run |
| `--no-probe` | Skip Contabo probe (faster backfill; receiver insert-only skips dupes) |
| `--batch-size` | Rows per page (default 500) |
| `--chunk` | Events per HTTP batch (default 25) |

Re-backfill one entity:

```bash
php artisan live-sync:fill-gaps --entity=payment --reset-cursor --until-done --sync
```

### Incremental — `live-sync:incremental`

For cron or quick manual runs after backfill:

```bash
php artisan live-sync:incremental --sync
```

Only processes rows with `id > saved cursor`. No full-table re-scan.

Enable cron on Namecheap (Laravel scheduler):

```env
LIVE_SYNC_INCREMENTAL_CRON=true
LIVE_SYNC_INCREMENTAL_CRON_MINUTES=5
```

### HTTP cron URL (cPanel / external cron on Namecheap)

Same token as other cron jobs (`CRON_EMAIL_FETCH_TOKEN`):

```bash
# Incremental — new rows only (recommended every 5–15 min)
curl -sS "https://check-outpay.com/cron/live-sync?token=YOUR_CRON_TOKEN"

# Incremental + refresh bank float
curl -sS "https://check-outpay.com/cron/live-sync?token=YOUR_CRON_TOKEN&float=1"

# Heavy full backfill (manual only)
curl -sS "https://check-outpay.com/cron/live-sync?token=YOUR_CRON_TOKEN&mode=full&no-probe=1"
```

| Query param | Default | Meaning |
|-------------|---------|---------|
| `token` | required | `CRON_EMAIL_FETCH_TOKEN` |
| `mode` | `incremental` | `incremental` or `full` |
| `float` | off | `1` = also upsert float balances |
| `entity` | `common` | Entity preset |
| `no-probe` | off | `1` = skip probe on full fill |

### Payment timestamps

Sync preserves live `created_at`, `updated_at`, and `matched_at` (`LIVE_SYNC_PRESERVE_TIMESTAMPS=true`).

Rows already on Contabo with today's date (from earlier insert-only sync) can be repaired:

```bash
php artisan live-sync:push --entity=payment --mode=recent --force-all --sync --chunk=25
```

### Push / refresh — `live-sync:push`

Catch-up for **recent window** (48h/watermark) or **balance upserts**:

```bash
php artisan live-sync:push --entity=float --mode=recent --force-all --limit=500 --sync --chunk=25
php artisan live-sync:push --entity=common --mode=missing --sync --chunk=25
```

`--mode=recent` **overwrites** existing rows (needed for balances). Id-cursor incremental does **not** re-push updated old rows — use `--mode=recent --since=` for that.

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
LIVE_SYNC_INCREMENTAL_CRON=true
LIVE_SYNC_INCREMENTAL_CRON_MINUTES=5
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

php artisan live-sync:status
```

Ongoing: configured models auto-push single-row changes when saved/deleted on Namecheap.
