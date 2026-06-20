# Business Utility — API reference (backend)

See native guide: `checkoutnow/docs/native/BUSINESS_UTILITY.md`

## Endpoint

```http
GET /api/v1/consumer/wallet/transactions?scope=business&business_view=full&from=YYYY-MM-DD&to=YYYY-MM-DD
```

| Param | Value |
|-------|--------|
| `scope` | `business` |
| `business_view` | **`full`** (default) |
| `from` / `to` | Recommended for Utility periods |
| `refresh` | `1` on pull-to-refresh |

## Included

All wallet `ledger_scope=business` rows plus merchant payments and withdrawals when merchant is linked.

## Implementation

`ConsumerBusinessActivityService` merges wallet txs + `payments` + `withdrawal_requests`.

Server cache TTL: 30 minutes (config `business_activity_cache_ttl_full`).

Paginated slices read from cached merged list (page 1 builds cache).
