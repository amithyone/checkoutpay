# Live Sync (Namecheap → Contabo)

Namecheap is the **live source of truth**. Contabo receives signed upserts so its DB stays aligned.

- Receiver (Contabo): `POST /api/v1/sync/live`
- Transmitter (Namecheap): model observers + `php artisan live-sync:push`

Entities: `payment`, `business`, `renter`  
Operations: `upsert`, `delete`

## Contabo (receiver) — already this host

In `.error`:

```env
LIVE_SYNC_ENABLED=true
LIVE_SYNC_TRANSMIT_ENABLED=false
LIVE_SYNC_KEY_ID=live-site-1
LIVE_SYNC_SECRET=<same-long-secret>
LIVE_SYNC_MAX_DRIFT_SECONDS=300
LIVE_SYNC_NONCE_TTL_SECONDS=600
# Optional: Namecheap server egress IP(s)
# LIVE_SYNC_ALLOWED_IPS=
```

Then:

```bash
php artisan config:clear
```

Probe (must be 401 without headers):

```bash
curl -sS -X POST https://check-outnow.com/api/v1/sync/live -H 'Content-Type: application/json' -d '{}'
```

Watch ingest:

```bash
php artisan tinker --execute='echo App\Models\LiveSyncEvent::count()."\n"; echo optional(App\Models\LiveSyncEvent::latest("id")->first())->created_at;'
```

## Namecheap (transmitter) — after you `git pull`

In Namecheap `.error` / `.env`:

```env
LIVE_SYNC_ENABLED=false
LIVE_SYNC_TRANSMIT_ENABLED=true
LIVE_SYNC_RECEIVER_URL=https://check-outnow.com/api/v1/sync/live
LIVE_SYNC_RECEIVER_PATH=/api/v1/sync/live
LIVE_SYNC_KEY_ID=live-site-1
LIVE_SYNC_SECRET=<same-secret-as-contabo>
LIVE_SYNC_SOURCE_NAME=namecheap-live
LIVE_SYNC_QUEUE=true
LIVE_SYNC_TIMEOUT_SECONDS=15
```

Then:

```bash
php artisan config:clear
# Ensure a queue worker is running if LIVE_SYNC_QUEUE=true
php artisan queue:work --queue=default
```

### Catch-up (run on Namecheap) — missing only, not the whole DB

Default mode probes Contabo and **only upserts keys Contabo does not have**.  
Window defaults to last watermark (or last 48h). Cap with `--limit` (default 200).

```bash
# Safe catch-up (missing payments only)
php artisan live-sync:push --entity=payment --mode=missing --sync

php artisan live-sync:push --entity=business --mode=missing --sync
php artisan live-sync:push --entity=renter --mode=missing --sync

# Explicit window
php artisan live-sync:push --entity=payment --mode=missing --since=2026-08-01 --limit=500 --sync

# Re-upsert recent changes (not just missing)
php artisan live-sync:push --entity=payment --mode=recent --sync
```

Ongoing: saving/deleting Payment, Business, or Renter on Namecheap queues a **single-row** push automatically (never a full scan).

## Security (headers)

- `X-LiveSync-Key`
- `X-LiveSync-Timestamp` (unix seconds)
- `X-LiveSync-Nonce` (UUID)
- `X-LiveSync-Signature` (HMAC-SHA256 hex)

Canonical string:

```text
POST
/api/v1/sync/live
{timestamp}
{nonce}
{sha256(raw_json_body)}
```

`signature = HMAC_SHA256(canonical, LIVE_SYNC_SECRET)` lowercase hex.

## Do not

- Enable `LIVE_SYNC_TRANSMIT_ENABLED` on Contabo (echo loop risk).
- Point Namecheap receiver URL at itself.
- Expect wallet / Mevon ledger / VTU tables to sync yet — only payment, business, renter today.
