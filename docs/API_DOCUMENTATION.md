# Checkout API reference

Base URL: `/api/v1` on your checkout host (example: `https://your-domain.com/api/v1`).

## GET /api/v1 (public, no API key)

Returns JSON including whether the **public webhook base URL** is considered active (HTTPS, non-local). The base URL is `WHATSAPP_APP_URL` if set, otherwise `APP_URL` (see `config/whatsapp.php` → `public_url`).

Example:

```json
{
  "success": true,
  "api_version": "v1",
  "webhook_base_url": "https://check-outnow.com",
  "webhook_base_url_active": true
}
```

`webhook_base_url_active` is `false` when the URL is missing, not HTTPS, or the host is `localhost` / `127.0.0.1` / `::1`.

---

All merchant endpoints below require authentication unless noted.

## Authentication

Send your API key on every request using **one** of:

- Header: `X-API-Key: <your_api_key>`
- JSON body field: `api_key` (only for `POST`/`PATCH` with a JSON body; prefer the header in production)

Inactive or unknown keys return `401` with `success: false` and message `Invalid or inactive API key`. Missing key returns `401` with `API key is required`.

## HTTP methods

Endpoints expect the documented HTTP method only. For example, **`POST /payment-request`** must be called with `POST` (e.g. from your server, Postman, or curl). Opening `/api/v1/payment-request` in a browser issues **GET** and Laravel responds with **405 Method Not Allowed**.

Use `Content-Type: application/json` for JSON bodies.

---

## POST /payment-request

Creates a pending payment and returns bank account details for the customer.

**Required JSON fields**

| Field | Type | Notes |
|-------|------|--------|
| `amount` | number | Minimum `0.01` |
| `webhook_url` | string (URL) | Max 500 characters. Host must match an **approved website** domain for your business (including subdomains of that host). |
| `payer_name` **or** `name` | string | At least one required; non-empty. Both map to payer name. |

**Optional JSON fields**

| Field | Type | Notes |
|-------|------|--------|
| `fname`, `lname` | string | |
| `bank` | string | |
| `bvn` | string | |
| `registration_number` | string | |
| `service` | string | Label/description |
| `transaction_id` | string | Must be unique among payments if provided |
| `business_website_id` | integer | Must exist in your `business_websites` |
| `website_url` | string (URL) | Helps identify website / charging context |

**Success:** `201 Created`

```json
{
  "success": true,
  "message": "Payment request created successfully",
  "data": {
    "transaction_id": "...",
    "amount": 5000.0,
    "payer_name": "...",
    "account_number": "...",
    "account_name": "...",
    "bank_name": "...",
    "status": "pending",
    "expires_at": "...",
    "created_at": "...",
    "charges": {
      "percentage": 0.0,
      "fixed": 0.0,
      "total": 0.0,
      "paid_by_customer": false,
      "amount_to_pay": 5000.0,
      "business_receives": 0.0
    },
    "website": { "id": 1, "url": "https://example.com" }
  }
}
```

**Validation errors:** `422 Unprocessable Entity` — JSON includes `message` (summary, often the first error) and `errors` (object keyed by field with string arrays). There is typically no `success` field on validation responses.

**Webhook domain rejected:** `400` — `Webhook URL must be from your approved website domain.`

---

## GET /payment/{transactionId}

Returns one payment for your business. **Requires** `X-API-Key`.

---

## PATCH /payment/{transactionId}/amount

Body: `{ "new_amount": 7500.0 }` — `new_amount` required, numeric, minimum `1`.

Only **pending**, non-expired payments owned by your business. Success: `200` with updated payment payload (same general shape as GET).

---

## GET /payments

List payments with optional query parameters: `status`, `from_date`, `to_date` (YYYY-MM-DD), `website_id`, `per_page` (default 15). Paginated `meta` is included in the JSON response.

---

## Rate limiting

The `api` middleware group applies a limit of **60 requests per minute** (per authenticated user id when resolved, otherwise per IP). Excessive traffic may receive **429 Too Many Requests**.

**WhatsApp wallet merchant routes** (`POST …/whatsapp-wallet/lookup`, `ensure`, `pay/start`) are additionally limited to **30 requests per minute** per the same identity.

---

## Webhooks

When a payment is approved, Checkout **POST**s JSON to the `webhook_url` you supplied (bank `payment-request` flow) or the `webhook_url` on **`pay/start`** (wallet flow). The URL must match an **approved** website or business webhook configured in Checkout.

Use `transaction_id` as the stable payment identifier. When present, `external_reference` carries your own reference (for wallet **`pay/start`**, this is your `order_reference`). Wallet partner approvals typically use `transaction_id` values prefixed with **`WLT-PARTNER-`**.

Example **`payment.approved`** body (fields may be `null` where not applicable):

```json
{
  "event": "payment.approved",
  "transaction_id": "WLT-PARTNER-ABCDEFGHIJKLMNOPQR",
  "external_reference": "ORDER-ABC-123",
  "status": "approved",
  "amount": 2500.0,
  "received_amount": 2500.0,
  "payer_name": "Ada",
  "bank": null,
  "payer_account_number": null,
  "account_number": null,
  "is_mismatch": false,
  "mismatch_reason": null,
  "charges": {
    "percentage": 0.0,
    "fixed": 0.0,
    "total": 0.0,
    "business_receives": 2500.0
  },
  "timestamp": "2026-04-26T12:00:00.000000Z",
  "email_data": {}
}
```

---

## WhatsApp wallet (merchant API)

**Admin:** Checkout must enable **WhatsApp wallet merchant API** on your business record. Otherwise all calls below return **`403`** with `success: false` and a message to contact support.

**Auth:** Same as other merchant routes — `X-API-Key` (or `api_key` in JSON for POST).

**Base path:** `/api/v1/whatsapp-wallet/...`

**Security:** There is **no** server-side or PIN-less debit API. The customer must open the **secure link** from WhatsApp and enter their **wallet PIN only on Checkout’s web page** — never collect the wallet PIN in your app or send it to this API.

**Rate limit:** **30 requests per minute** on these wallet merchant routes (shared bucket), in addition to the global API limit above.

### POST /whatsapp-wallet/lookup

Returns balance and metadata for a **valid Nigerian mobile** number in Checkout’s canonical `234…` form. Does **not** create a wallet.

**Body (JSON)**

| Field | Type | Required | Notes |
|-------|------|----------|--------|
| `phone` | string | Yes | Min length 10 after validation |

**Success `200`**

If no wallet exists yet for that number, `wallet_id`, `tier`, and `status` may be `null`, `balance` is `0`, and `has_pin` is `false`.

```json
{
  "success": true,
  "data": {
    "phone_e164": "2348012345678",
    "wallet_id": 12,
    "balance": 1500.5,
    "has_pin": true,
    "tier": 1,
    "status": "active"
  }
}
```

**Errors:** `422` — invalid Nigerian number or validation failure (`success: false`, `message`).

### POST /whatsapp-wallet/ensure

Creates the wallet row if it does not exist (Tier 1 shell). Same `phone` rules as lookup. Does **not** set a wallet PIN and does **not** debit funds; customers set PIN through WhatsApp wallet flows.

**Success `200`**

```json
{
  "success": true,
  "data": {
    "wallet_id": 12,
    "phone_e164": "2348012345678",
    "renter_id": null
  }
}
```

**Errors:** `422` — invalid number or validation failure.

### POST /whatsapp-wallet/send-message

Deliver a **plain-text WhatsApp** you compose (e.g. a login OTP message) to a Nigerian customer number via Checkout’s Evolution integration. Same **`X-API-Key`** and **`whatsapp_wallet_api_enabled`** gate as the other wallet merchant routes — **no** separate Checkout `.env` secret per integrator.

**Body (JSON)**

| Field | Type | Required | Notes |
|-------|------|----------|--------|
| `phone` | string | Yes | Min length 10; normalized to `234…` |
| `message` | string | Yes | Max **4000** characters (your full text, including any code) |

**Success `200`**

```json
{
  "success": true,
  "data": { "sent": true }
}
```

**Errors:** `422` — invalid phone or validation; `502` — Evolution could not send.

### POST /whatsapp-wallet/pay/start

Starts the **only** supported charge flow: Checkout sends the customer a **WhatsApp** message with your **order summary**, amount, and a **secure link**. They open the link and enter their **4-digit wallet PIN** on Checkout. After a correct PIN and successful debit, **your business balance is credited** and a **`payment.approved`** webhook is POSTed to `webhook_url`.

**Prerequisites (typical failures otherwise):**

- A **wallet** must already exist for the number (use **`ensure`** if you need to create the shell record).
- The customer must have **set a wallet PIN** in WhatsApp (`has_pin` from **lookup**). **`pay/start`** fails if PIN is not set.

**Link lifetime:** Configurable with `WHATSAPP_WALLET_PARTNER_PAY_INTENT_TTL_MINUTES` (clamped between **5** and **120** minutes; default **30**).

**Body (JSON)**

| Field | Type | Required | Notes |
|-------|------|----------|--------|
| `phone` | string | Yes | Customer Nigerian mobile |
| `amount` | number | Yes | Minimum `0.01` |
| `order_reference` | string | Yes | Max 120 chars; stored as payment `external_reference` and in the webhook |
| `order_summary` | string | Yes | Human-readable lines (what they pay for); max 8000 chars |
| `payer_name` | string | Yes | Max 120 chars |
| `webhook_url` | string (URL) | Yes | Max 500 chars. **Must exactly match** (after trim) your saved **business** webhook URL or an **approved business website** webhook URL in Checkout. |
| `idempotency_key` | string | Yes | 8–80 characters; scoped to your business |

**Success `201 Created`** — all successful responses use **`201`**, including when you replay the same `idempotency_key` for an intent still **pending** PIN (same `confirm_url` and expiry fields).

**Pending PIN** (`data.status` is `pending_pin`):

```json
{
  "success": true,
  "data": {
    "pay_intent_id": 1,
    "status": "pending_pin",
    "confirm_url": "https://your-checkout.example.com/wallet/partner-pay/xxxxxxxx",
    "expires_at": "2026-04-26T12:00:00.000000Z",
    "expires_in_minutes": 30,
    "order_reference": "ORDER-ABC-123"
  }
}
```

**Already completed** (same `idempotency_key` after the customer finished PIN and settlement):

```json
{
  "success": true,
  "data": {
    "status": "already_completed",
    "payment_id": 99,
    "transaction_id": "WLT-PARTNER-ABCDEFGHIJKLMNOPQR"
  }
}
```

If a previous intent for that key **failed** or **expired**, a new **`pay/start`** with the same key may reset the intent and send WhatsApp again.

**Errors (non-exhaustive)**

| HTTP | Typical cause |
|------|----------------|
| `400` | Bad `webhook_url`, wallet missing, PIN not set, insufficient balance / limits, invalid amount |
| `403` | WhatsApp wallet merchant API disabled for the business |
| `422` | Laravel validation (missing/invalid fields) |
| `502` | Checkout could not send the WhatsApp message (Evolution / messaging configuration) |

Error body shape: `{ "success": false, "message": "…" }`.

**curl example**

```bash
curl -sS -X POST "https://your-checkout.example.com/api/v1/whatsapp-wallet/pay/start" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: pk_your_api_key" \
  -d '{
    "phone": "08012345678",
    "amount": 2500.00,
    "order_reference": "ORDER-ABC-123",
    "order_summary": "2x Jollof\n1x Zobo\nDelivery: Surulere",
    "payer_name": "Ada",
    "webhook_url": "https://yourapp.com/api/webhooks/checkout/payment",
    "idempotency_key": "order-abc-123-wallet-001"
  }'
```
