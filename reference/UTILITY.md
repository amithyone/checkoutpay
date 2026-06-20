# Utility page — backend reference

Companion to `checkoutnow/docs/UTILITY.md`.

---

## Consumer API used

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/consumer/wallet` | Ledger context, balances, `business_pay_in` |
| GET | `/api/v1/consumer/wallet/transactions` | `scope`, `from`, `to`, pagination |
| POST | `/api/v1/consumer/wallet/statement/email` | Email CSV/PDF statement to `kyc_email` |

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

When `scope=business` and **`from` / `to`** are supplied, the API returns **all** business activity for Utility analysis:

1. **Every** wallet row with `ledger_scope=business` (transfers, Rubies in, bills paid from business balance, fees, etc.)
2. **Every** merchant `payments` row for that business (website, API, checkout — all statuses in `meta.status`)
3. **Every** `withdrawal_requests` row (all statuses in `meta.status`)

Only **approved** payments and **completed** withdrawals count toward money in/out totals on the app; pending/rejected rows still appear in the list and statements for status visibility.

Response `meta.includes_merchant_activity: true` and `meta.business_id` when merged.

Without `from` / `to`, business scope returns wallet ledger rows only (legacy pagination for History).

When merged, wallet activity uses **all** rows with `ledger_scope=business` (no subset filter).

Synthetic types for merchant records:

| Type | Role |
|------|------|
| `merchant_payment_in` | All `payments` for the business (`meta.status`, `meta.website_url`, `meta.label`) |
| `merchant_withdrawal_out` | All `withdrawal_requests` (`meta.status`, `meta.status_label`) |

Wallet examples: `bank_transfer_out`, `business_rubies_in`, business-scoped `vtu_*`, `business_name_registration_fee`.

Dedup: payments already recorded as `business_rubies_in` on the wallet (same `meta.payment_id`) are omitted from the `payments` list.

---

## Related services

| Service | Role |
|---------|------|
| `ConsumerAccountStatementBuilder` | CSV/HTML matching shared `accountStatement.ts` |
| `ConsumerWalletStatementService` | Load txs, build attachment, send mail |
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

## Email statement delivery

`POST /api/v1/consumer/wallet/statement/email` (Sanctum consumer token).

- Resolves wallet from token; requires `kyc_email` on `whatsapp_wallets` (422 if missing).
- Loads transactions for `ledger_scope` + `from`/`to` (Lagos). Business uses `ConsumerBusinessActivityService::VIEW_FULL`.
- Builds CSV via `ConsumerAccountStatementBuilder::statementCsvContent()` (same columns as `packages/shared/src/accountStatement.ts`) or PDF via DomPDF from matching HTML.
- Sends `WalletStatementMail` with attachment; response includes masked email.

---

## Not implemented

- Backend `GET /consumer/wallet/spending-summary`
- Historical backfill of pre-deploy merchant deposits
