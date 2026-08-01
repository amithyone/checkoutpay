# Wallet support staff API (CheckoutNow)

Backend support for wallet support staff using CheckoutNow with the same phone as their admin `whatsapp_e164`.

## Detect mode

`GET /api/v1/consumer/support/context` (Sanctum)

- `mode: "customer"` — normal support flow (category picker + chat)
- `mode: "staff"` — render staff inbox UI

Same `mode` is included on `GET /api/v1/consumer/support/options`.

## Staff inbox

| Method | Route |
|--------|-------|
| GET | `/api/v1/consumer/support/staff/inbox` |
| GET | `/api/v1/consumer/support/staff/tickets/{id}/messages?after_id=N` |
| POST | `/api/v1/consumer/support/staff/tickets/{id}/reply` `{ "message": "..." }` |
| POST | `/api/v1/consumer/support/staff/tickets/{id}/status` `{ "status": "resolved" }` |

Poll every `poll_interval_seconds` (default 5).

## Customer wallet support

1. Category **Wallet support**
2. Pick issue type where `queue === "wallet"`
3. `POST /api/v1/consumer/support/conversations` with `issue_type`, `consent_accepted`, `link_whatsapp_wallet: true` — no bank session ID

## FCM

Push type: `wallet_support_ticket`  
Data: `ticket_id`, `ticket_number`, `screen: support_staff`

## Admin setup

Staff role **Wallet support** → WhatsApp number must match CheckoutNow wallet → enable **Handle wallet support in CheckoutNow app**.
