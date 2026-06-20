# Business utility & history — API reference

Companion to `checkoutnow/docs/`. Keep in sync when API behavior changes.

**Native integration guides (start here):**

- `checkoutnow/docs/native/BUSINESS_UTILITY.md` — Utility tab (`business_view=full`)
- `checkoutnow/docs/native/BUSINESS_HISTORY.md` — History tab (`business_view=account`)
- `checkoutnow/docs/BUSINESS_UTILITY_API.md` — overview + quick reference

**Base:** `/api/v1/consumer/...`  
**Auth:** Bearer consumer Sanctum token (`abilities: consumer`)

## Endpoints

| Method | Path | Notes |
|--------|------|-------|
| GET | `/consumer/wallet` | `business_wallet_enabled`, `business_balance`, `linked_business_*` |
| GET | `/consumer/wallet/transactions` | `scope`, `business_view`, `from`, `to`, `page`, `per_page`, `refresh` |

## `business_view`

| Value | Screen | Returns |
|-------|--------|---------|
| `full` (default) | Utility | All wallet business ledger + merchant payments + withdrawals |
| `account` | History | Pay-ins (`merchant_payment_in`, `business_rubies_in`) + `merchant_withdrawal_out` only |

`ConsumerBusinessActivityService` when `scope=business` and merchant resolved.

Without merchant link: wallet `ledger_scope=business` rows only (`includes_merchant_activity: false`).

Default date window when `from`/`to` omitted: last 12 months.

## Server cache

Merged rows cached in Laravel cache:

| View | Config key | Default TTL |
|------|------------|-------------|
| `full` | `consumer_wallet.business_activity_cache_ttl_full` | 1800s (30m) |
| `account` | `consumer_wallet.business_activity_cache_ttl_account` | 600s (10m) |

Query `refresh=1` on page 1 bypasses cache.

## Rate limit

`consumer_wallet` limiter: default **240/min** per user (`CONSUMER_WALLET_RATE_LIMIT_PER_MINUTE`).

## Business bank transfer sender

`ConsumerBusinessWalletLedgerService::resolveLedgerSenderName()` — business name on `from_ledger=business`.

## Services

| Service | Role |
|---------|------|
| `ConsumerBusinessActivityService` | Merge + server cache |
| `ConsumerBusinessWalletLedgerService` | Merchant link, balance |
| `ConsumerWalletSavingsService` | Personal vs business savings |
| `ConsumerWalletTransactionScope` | Personal history filter |
