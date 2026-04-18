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

---

## Webhooks

On approval, your webhook receives a JSON POST (see public API docs for full payload). The `webhook_url` you send on create must be on an approved domain; you may also configure website- or business-level webhooks in the dashboard.
