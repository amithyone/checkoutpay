# Business History — API reference (backend)

See native guide: `checkoutnow/docs/native/BUSINESS_HISTORY.md`

## Endpoint

```http
GET /api/v1/consumer/wallet/transactions?scope=business&business_view=account
```

| Param | Value |
|-------|--------|
| `scope` | `business` |
| `business_view` | **`account`** (required for History semantics) |
| `from` / `to` | Optional; default last 12 months |
| `refresh` | `1` on pull-to-refresh |

## Included types

- `merchant_payment_in`
- `business_rubies_in`
- `bank_transfer_out` (`ledger_scope=business` — app sends from business balance)
- `merchant_withdrawal_out`

## Excluded (use `business_view=full` for Utility)

- `vtu_*`, p2p, fees, savings locks, personal-ledger transfers, etc.

## Implementation

`ConsumerBusinessActivityService::paginate()` with `VIEW_ACCOUNT` filters wallet query to `business_rubies_in` and `bank_transfer_out` on business ledger; still merges merchant payments and withdrawals.

Server cache TTL: 10 minutes (config `business_activity_cache_ttl_account`).
