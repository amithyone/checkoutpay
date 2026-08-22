# Consumer transfer fee quote

`POST /api/v1/consumer/transfers/fee-quote` (Bearer consumer token, **no PIN**).

Used by the CheckoutNow amount screen for a live fee line under the amount field.

## Rules (must match debit)

| Kind | Fee today |
|------|-----------|
| `p2p` | Always **₦0** |
| `bank` → someone else | **₦0** |
| `bank` → own account (self) | Admin **self bank transfer fee** (percent + optional fixed, capped). Taken **from** the send amount (recipient gets less). Wallet still debits the typed `amount`. |
| Internal CheckoutNow VA / business→own personal | **₦0** |

Admin: `/admin/whatsapp-wallet/settings` → **Self bank transfer fee**. Defaults are **0%** (no charge) until ops raises them.

## Request

```json
{
  "kind": "bank",
  "amount": 5000,
  "ledger_scope": "personal",
  "bank_code": "058",
  "account_number": "0123456789"
}
```

P2P: `{ "kind": "p2p", "amount": 2000, "to_phone": "08012345678" }` (`to_phone` optional for quoting).

## Response `data`

| Field | Meaning |
|-------|---------|
| `amount` | Typed send amount |
| `fee_amount` | Fee in NGN (`0` when free) |
| `total_debit` | What leaves the wallet (= `amount`) |
| `payout_amount` | What the bank/recipient receives (`amount − fee`) |
| `fee_mode` | Always `from_amount` for current rules |
| `fee_label` / `total_label` | Pre-formatted UI strings |
| `self_transfer` | Bank only |
| `fee_waived` | Self detected but fee is 0 while feature enabled |
| `reason` | e.g. `self_transfer`, `internal_wallet` |

Name enquiry results are cached ~2 minutes per account for cheap debounce.
