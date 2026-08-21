# Checkout Ops Sentinel

Office wall-clock / tray monitor polls Contabo and Namecheap via a dedicated ops surface.

## Server setup

On each host (in `.error` / env — never commit real keys):

```env
OPS_MONITOR_KEY=generate-a-long-random-secret
OPS_HOST_ROLE=primary   # Contabo
# OPS_HOST_ROLE=relay   # Namecheap
OPS_MONITOR_ALLOWED_IPS=   # optional comma-separated client IPs
```

Deploy the Laravel changes, then clear config cache:

```bash
php artisan config:clear
php artisan route:clear
```

## Curl proof (from laptop)

```bash
export OPS_MONITOR_KEY='your-secret'
export BASE='https://check-outnow.com'

curl -sS -H "X-Ops-Key: $OPS_MONITOR_KEY" "$BASE/ops/v1/ping" | jq .
curl -sS -H "X-Ops-Key: $OPS_MONITOR_KEY" "$BASE/ops/v1/security" | jq .
curl -sS -H "X-Ops-Key: $OPS_MONITOR_KEY" "$BASE/ops/v1/health" | jq .
curl -sS -H "X-Ops-Key: $OPS_MONITOR_KEY" "$BASE/ops/v1/activity" | jq .
curl -sS -H "X-Ops-Key: $OPS_MONITOR_KEY" "$BASE/ops/v1/balances" | jq .
```

Bearer form also works: `Authorization: Bearer $OPS_MONITOR_KEY`.

## Endpoints

| Path | Purpose |
|------|---------|
| `GET /ops/v1/ping` | Liveness, role, versions |
| `GET /ops/v1/security` | Quarantine + DB allowlist flags (no passwords) |
| `GET /ops/v1/health` | Health + last cron + queue depth |
| `GET /ops/v1/activity` | Curated office activity feed |
| `GET /ops/v1/balances` | Mevon snapshot (primary only) |

These routes stay reachable during quarantine so the wall can report `quarantine.active`.

## Desktop app

Install `checkout-ops-sentinel` on office Windows PCs. Configure servers + keys in `%APPDATA%/Checkout Ops Sentinel/config.json`. Use **Wall Clock** fullscreen on the office monitor, or the **tray widget** on laptops.
