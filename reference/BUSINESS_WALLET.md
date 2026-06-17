# Business wallet — backend reference

Companion to `checkoutnow/docs/BUSINESS_WALLET.md` (app contract).

---

## Database

| Column / table | Purpose |
|----------------|---------|
| `whatsapp_wallets.business_balance` | Separate business ledger |
| `whatsapp_wallets.linked_business_id` | FK → `businesses.id` |
| `whatsapp_wallet_transactions.ledger_scope` | `personal` \| `business` |

Migrations:

- `2026_06_15_140000_add_business_wallet_fields_to_whatsapp_wallets_table.php`
- `2026_06_15_140001_add_ledger_scope_to_whatsapp_wallet_transactions_table.php`

---

## Consumer API

| Method | Path | Notes |
|--------|------|-------|
| GET | `/api/v1/consumer/wallet` | Adds `business_balance`, `business_wallet_enabled`, `linked_business_*` |
| GET | `/api/v1/consumer/wallet/transactions?scope=` | `ConsumerWalletTransactionScope` filter |
| POST | `/api/v1/consumer/transfers/bank` | `from_ledger=personal\|business` |

Controller: `ConsumerWalletApiController`  
Transfer service: `ConsumerWalletTransferService` (scoped bank debit)  
Scope filter: `App\Services\Consumer\ConsumerWalletTransactionScope`

---

## Linking wallet ↔ merchant business

### Admin

`PUT /admin/whatsapp-wallet/wallets/{wallet}/link-business`  
Form field: `linked_business_id`

### Business dashboard (merchant self-service)

`POST /dashboard/whatsapp-wallet/link`  
Fields: `wallet_phone`, `wallet_pin` (4 digits)

`POST /dashboard/whatsapp-wallet/unlink`

Controller: `BusinessWhatsappWalletController`  
Service: `BusinessWhatsappWalletLinkService`

Verifies wallet PIN via `ConsumerWalletPinVerifier` before setting `linked_business_id`. Copies the merchant `businesses.balance` onto the wallet and keeps it in sync on wallet API reads.

---

## Business history filter (app)

`scope=business` excludes:

- `type = topup` (business VA inflows)
- `partner_merchant_pay` / website payment credits (`meta.payment_id`, etc.)

Includes withdrawals and non-website credits on `ledger_scope = business`.

---

## Not yet implemented

- Webhook credit to `business_pay_in_account_number` → `business_balance` (BNR-only wallets)
- P2P from business ledger
