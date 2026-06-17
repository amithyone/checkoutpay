# Utility page — backend reference

Companion to `checkoutnow/docs/UTILITY.md`.

---

## Consumer API used

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/consumer/wallet` | Ledger context, balances, `business_pay_in` |
| GET | `/api/v1/consumer/wallet/transactions` | `scope`, `from`, `to`, pagination |

No dedicated spending-summary endpoint — the app aggregates transaction rows client-side.

---

## Transaction list filters

**Controller:** `ConsumerWalletApiController::transactions`

### Query parameters

| Param | Default | Description |
|-------|---------|-------------|
| `scope` | `personal` | `personal` or `business` (`ConsumerWalletTransactionScope`) |
| `from` | — | Inclusive start date `YYYY-MM-DD` |
| `to` | — | Inclusive end date `YYYY-MM-DD` |
| `page` | `1` | Page number |
| `per_page` | `20` | Max `50` |

### Timezone (important)

Date filters use **`Africa/Lagos`** (same as `config('app.timezone')`):

- `from` → start of that calendar day in Lagos  
- `to` → end of that calendar day in Lagos  

Response `meta` echoes:

```json
{
  "scope": "personal",
  "from": "2025-12-16",
  "to": "2026-06-15",
  "timezone": "Africa/Lagos",
  "total": 42
}
```

The app computes `from` / `to` in **Lagos calendar** and re-filters rows client-side so period chips stay accurate.

---

## Period definitions (app Utility page)

All periods are **inclusive** Lagos calendar dates through **today**:

| Chip | `from` calculation |
|------|----------------------|
| Last 30 days | Today minus **29** days (30 calendar days including today) |
| Last 6 months | Same day-of-month **6 calendar months** ago (day clamped to month length) |
| Last 12 months | Same day-of-month **12 calendar months** ago |

Statement export uses the same rules for **6mo** and **12mo**.

---

## Business ledger transaction types

Included in `scope=business`:

- `bank_transfer_out`
- `business_rubies_in` — linked merchant Rubies VA deposit
- Other business-scoped credits not excluded by scope rules

Excluded: BNR `topup`, website checkout credits (`partner_merchant_pay`, etc.).

---

## Related services

| Service | Role |
|---------|------|
| `ConsumerBusinessWalletLedgerService` | Linked balance, `business_pay_in`, deposit history |
| `ConsumerWalletTransactionScope` | Personal vs business filter |
| `BusinessWhatsappWalletLinkService` | Merchant dashboard wallet link |

See also `reference/BUSINESS_WALLET.md`.

---

## Frontend (CheckoutNow)

| File | Role |
|------|------|
| `src/components/Utility.tsx` | Utility tab UI |
| `src/lib/utilityStats.ts` | `periodBounds()`, stats, Lagos date filter |
| `src/lib/accountStatement.ts` | PDF / CSV export |
| `src/lib/consumerApi.ts` | Paginated fetch with `from` / `to` |

Tab id in nav remains `services`; label shown as **Utility**.

---

## Not implemented

- Backend `GET /consumer/wallet/spending-summary`
- Email delivery of statements
- Historical backfill of pre-deploy merchant deposits
