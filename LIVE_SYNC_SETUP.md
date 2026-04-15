# Live Sync Secure API (Receiver)

This app now exposes a secure receiver endpoint for your live transmitter site:

- `POST /api/v1/sync/live`

Security controls:

- HMAC-SHA256 signature (`X-LiveSync-Signature`)
- key id check (`X-LiveSync-Key`)
- timestamp drift check (`X-LiveSync-Timestamp`)
- nonce replay protection (`X-LiveSync-Nonce`)
- optional source IP allowlist (`LIVE_SYNC_ALLOWED_IPS`)
- payload validation + strict entity/operation whitelist
- idempotency via `event_id` (`live_sync_events` table)

## 1) Receiver env setup

Set these in this app's `.env`:

```env
LIVE_SYNC_ENABLED=true
LIVE_SYNC_KEY_ID=live-site-1
LIVE_SYNC_SECRET=put-a-long-random-secret-here
LIVE_SYNC_MAX_DRIFT_SECONDS=300
LIVE_SYNC_NONCE_TTL_SECONDS=600
LIVE_SYNC_ALLOWED_IPS=
```

Then run:

```bash
php artisan config:clear
php artisan migrate
```

## 2) Required headers from transmitter

- `X-LiveSync-Key`
- `X-LiveSync-Timestamp` (unix seconds)
- `X-LiveSync-Nonce` (unique per request, e.g. UUID)
- `X-LiveSync-Signature` (hex hmac)
- `Content-Type: application/json`

## 3) Signature algorithm (must match receiver)

`body_hash = sha256(raw_json_body)`

`canonical = METHOD + "\n" + PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + body_hash`

Where:

- `METHOD` is uppercase (`POST`)
- `PATH` is exact path string with leading slash (`/api/v1/sync/live`)

`signature = HMAC_SHA256(canonical, LIVE_SYNC_SECRET)` as lowercase hex.

## 4) Payload format

```json
{
  "event_id": "c5ef0f57-e8a7-4f9e-88ea-ff8a5de06f61",
  "source": "live-site",
  "entity": "payment",
  "operation": "upsert",
  "sent_at": "2026-04-15T10:00:00Z",
  "data": {
    "transaction_id": "TX-123",
    "amount": 5000,
    "status": "approved",
    "business_id": 12
  }
}
```

Supported:

- `entity`: `payment`, `business`, `renter`
- `operation`: `upsert`, `delete`

Delete uses identifier fields:

- payment: `transaction_id`
- business: `business_id` or `email`
- renter: `email`

## 5) Node.js transmitter example

```js
import crypto from "crypto";

const url = "https://receiver-domain.com/api/v1/sync/live";
const method = "POST";
const path = "/api/v1/sync/live";
const keyId = process.env.LIVE_SYNC_KEY_ID;
const secret = process.env.LIVE_SYNC_SECRET;
const timestamp = String(Math.floor(Date.now() / 1000));
const nonce = crypto.randomUUID();

const payload = {
  event_id: crypto.randomUUID(),
  source: "live-site",
  entity: "payment",
  operation: "upsert",
  sent_at: new Date().toISOString(),
  data: {
    transaction_id: "TX-123",
    amount: 5000,
    status: "pending"
  }
};

const body = JSON.stringify(payload);
const bodyHash = crypto.createHash("sha256").update(body).digest("hex");
const canonical = [method, path, timestamp, nonce, bodyHash].join("\n");
const signature = crypto.createHmac("sha256", secret).update(canonical).digest("hex");

const res = await fetch(url, {
  method,
  headers: {
    "Content-Type": "application/json",
    "X-LiveSync-Key": keyId,
    "X-LiveSync-Timestamp": timestamp,
    "X-LiveSync-Nonce": nonce,
    "X-LiveSync-Signature": signature
  },
  body
});

console.log(await res.text());
```
