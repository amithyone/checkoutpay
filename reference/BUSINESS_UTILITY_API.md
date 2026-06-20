# Business utility & history — API reference

Companion to `checkoutnow/docs/BUSINESS_UTILITY_API.md` (keep in sync).

**Base:** `/api/v1/consumer/...`  
**Auth:** Bearer consumer Sanctum token (`abilities: consumer`)

## Endpoints

| Method | Path | Notes |
|--------|------|-------|
| GET | `/consumer/wallet` | `business_wallet_enabled`, `business_balance`, `linked_business_*` |
| GET | `/consumer/wallet/transactions` | `scope`, `from`, `to`, `page`, `per_page` |

## Business transactions merge

`ConsumerBusinessActivityService` when `scope=business` and merchant resolved:

- Wallet `ledger_scope=business` rows
- All `payments` for `business_id`
- All `withdrawal_requests` for `business_id`

Without merchant link: wallet `ledger_scope=business` rows only.

Default date window when `from`/`to` omitted: last 12 months.

## Business bank transfer sender

`ConsumerBusinessWalletLedgerService::resolveLedgerSenderName()` — business name on `from_ledger=business` bank transfers (transaction `sender_name`, MevonPay `debitAccountName`, narration).

## Savings vs business utility

Savings uses `ledger_scope` on goals and deposits (`ConsumerWalletSavingsService`) — separate from transaction history scope. Business savings debits `business_balance`; personal savings debits `balance`.

## Services

| Service | Role |
|---------|------|
| `ConsumerBusinessActivityService` | Utility / history merge |
| `ConsumerBusinessWalletLedgerService` | Merchant link, balance |
| `ConsumerWalletSavingsService` | Personal vs business savings |
| `ConsumerWalletTransactionScope` | Personal history filter |

Full native integration guide: `checkoutnow/docs/BUSINESS_UTILITY_API.md`
