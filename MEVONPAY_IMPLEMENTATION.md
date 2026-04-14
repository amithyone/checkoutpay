# MevonPay API Documentation

## Overview

MevonPay provides APIs for:

- Virtual account creation (temporary and dynamic)
- Rubies personal account creation
- Rubies business account creation
- Bank list and account name enquiry
- Transfers/payouts
- Utility services (electricity and cable TV)
- Payment event webhooks

---

## Base URL

Use your assigned environment base URL:

- Sandbox: `https://sandbox-api.mevonpay.com` (example)
- Production: `https://api.mevonpay.com` (example)

---

## Authentication

Send your API key in `Authorization`.

```http
Authorization: YOUR_SECRET_KEY
Content-Type: application/json
Accept: application/json
```

Some endpoints may also accept `Bearer YOUR_SECRET_KEY` for backward compatibility.

---

## API Conventions

- Method: `POST` unless stated otherwise
- Body: JSON
- Bank codes: use 6-digit NIP format (for example `000058`)
- Success: HTTP `2xx`
- Validation error: HTTP `4xx`
- Provider/server error: HTTP `5xx`

---

## 1) Create Temporary Virtual Account

- Path: `/V1/createtempva.php`

### Request

```json
{
  "type": "rubies",
  "fname": "John",
  "lname": "Doe",
  "registration_number": "12345678901"
}
```

Alternative identity field (legacy):

```json
{
  "type": "rubies",
  "fname": "John",
  "lname": "Doe",
  "bvn": "12345678901"
}
```

### Success Response (Example)

```json
{
  "status": true,
  "message": "Account created successfully",
  "data": {
    "account_number": "1234567890",
    "account_name": "John Doe",
    "bank_name": "Rubies MFB",
    "bank_code": "000023"
  }
}
```

---

## 2) Create Dynamic Virtual Account

- Path: `/V1/createdynamic`

### Request

```json
{
  "amount": 15000,
  "currency": "NGN"
}
```

### Success Response (Example)

```json
{
  "status": true,
  "message": "Dynamic account created",
  "data": {
    "accountNumber": "1234567890",
    "accountName": "Checkout Reference Account",
    "bankName": "Rubies MFB",
    "bankCode": "000023",
    "expiresOn": "2026-04-20T12:00:00Z"
  }
}
```

---

## 3) Create Rubies Personal Account

- Path: `/V1/createrubies`

### Request

```json
{
  "action": "create",
  "account_type": "personal",
  "fname": "John",
  "lname": "Doe",
  "phone": "09087654321",
  "dob": "2010-05-20",
  "email": "john@example.com",
  "bvn": "12345678901"
}
```

Use `nin` if BVN is not used.

### Success Response (Example)

```json
{
  "status": true,
  "message": "Personal account created",
  "data": {
    "reference": "RUBY_12345",
    "account_number": "1234567890",
    "account_name": "JOHN DOE",
    "bank_name": "Rubies MFB",
    "bank_code": "000023"
  }
}
```

---

## 3.1) Create Rubies Business Account

- Path: `/V1/createrubies`

### Request

```json
{
  "action": "create",
  "account_type": "business",
  "cac": "RC123456",
  "phone": "09087654321",
  "dob": "2010-05-20",
  "email": "contact@business.com"
}
```

### Success Response (Example)

```json
{
  "status": true,
  "message": "Business account created",
  "data": {
    "reference": "RUBY_BIZ_12345",
    "account_number": "1234567890",
    "account_name": "BUSINESS NAME",
    "bank_name": "Rubies MFB",
    "bank_code": "000023"
  }
}
```

---

## 4) Bank Service API

- Path: `/V1/bank_service`

### 4.1 Get Bank List

```json
{
  "action": "getBankList"
}
```

### 4.2 Name Enquiry

```json
{
  "action": "nameEnquiry",
  "bankCode": "000058",
  "accountNumber": "0123456789"
}
```

### Name Enquiry Success Response (Example)

```json
{
  "status": true,
  "data": {
    "account_name": "JOHN DOE",
    "account_number": "0123456789",
    "bank_code": "000058"
  }
}
```

---

## 5) Create Transfer (Payout)

- Path: `/V1/createtransfer`

### Request

```json
{
  "amount": 5000,
  "bankCode": "000058",
  "bankName": "GTBank",
  "creditAccountName": "John Doe",
  "creditAccountNumber": "0123456789",
  "debitAccountName": "Merchant Wallet",
  "debitAccountNumber": "9999999999",
  "narration": "Withdrawal payout",
  "reference": "TRF_20260413_0001",
  "sessionId": "optional-session-id",
  "currentPassword": "merchant-password"
}
```

### Response Code Interpretation

- `00` = successful
- `09`, `90`, `99` = pending
- others = failed

---

## 6) Electricity API

- Path: `/V1/electricity`

### 6.1 Get Providers/Plans

```json
{
  "action": "getInfo"
}
```

### 6.2 Verify Meter

```json
{
  "action": "verify",
  "meter": "45056532877",
  "providerCode": "abuja-electric",
  "planCode": "prepaid"
}
```

### Verify Response (Example)

```json
{
  "status": true,
  "message": "Success",
  "data": {
    "success": true,
    "message": "Verification was successful",
    "data": {
      "customerId": "45056532877",
      "customerName": "UZOMA GODEREY ONYEMA",
      "dueDate": null
    }
  }
}
```

### 6.3 Buy Token

```json
{
  "action": "buy",
  "meter": "45056532877",
  "providerCode": "abuja-electric",
  "planCode": "prepaid",
  "amount": 2000,
  "customerName": "UZOMA GODEREY ONYEMA",
  "phone": "08012345678"
}
```

---

## 7) Cable TV API

- Path: `/V1/cabletv`

### 7.1 Get Providers/Plans

```json
{
  "action": "getInfo"
}
```

### 7.2 Verify Smartcard

```json
{
  "action": "verify",
  "smartcard": "8061700508",
  "providerCode": "gotv",
  "planCode": "cwgotvsmallie"
}
```

### Verify Response (Example)

```json
{
  "status": true,
  "message": "Success",
  "data": {
    "success": true,
    "message": "Verification was successful",
    "data": {
      "customerId": "8061700508",
      "customerName": "JOSEPHAT ANENECHUKWU IKELIIQINSA",
      "dueDate": null
    }
  }
}
```

### 7.3 Buy Subscription

```json
{
  "action": "buy",
  "smartcard": "8061700508",
  "providerCode": "gotv",
  "planCode": "cwgotvsmallie",
  "amount": 1900,
  "customerName": "JOSEPHAT ANENECHUKWU IKELIIQINSA",
  "phone": "08012345678"
}
```

---

## 8) Webhooks

Event currently used by integrations:

- `funding.success`

### Example Payload

```json
{
  "event": "funding.success",
  "data": {
    "account_number": "1234567890",
    "amount": 25000,
    "reference": "FUND_20260413_001",
    "sender": "JOHN DOE",
    "bank_name": "GTBank",
    "timestamp": "2026-04-13T10:33:00Z"
  }
}
```

---

## 9) Dedicated Validation Endpoint (Project Helper)

This project provides a single testing endpoint for documentation QA:

- `POST /api/v1/test-meveon`
- `GET /api/v1/test-meveon`

Supported actions:

- `ping`
- `createtempva`
- `createdynamic`
- `getBankList`
- `nameEnquiry`
- `createtransfer`
- `createrubies_personal`
- `createrubies_business`
- `rubies_electricity_getInfo`
- `rubies_electricity_verify`
- `rubies_electricity_buy`
- `rubies_cable_getInfo`
- `rubies_cable_verify`
- `rubies_cable_buy`

Optional test secret:

- Set `MEVONPAY_TEST_SECRET`
- Send header `X-Test-Meveon-Secret: <secret>`

---

## 10) Contact / Support (Template)

- Integration support: `integrations@mevonpay.com`
- Incident support: `support@mevonpay.com`
- Status page: `https://status.mevonpay.com`
# MevonPay API Documentation (Draft Rewrite)

## Overview

MevonPay provides APIs for:

- Virtual account creation (temporary and dynamic)
- Bank directory and account name enquiry
- Transfers/payouts
- Payment event webhooks

This document is written in a provider-facing style so merchants and integrators can build against MevonPay quickly and consistently.

---

## Base URL

Use your assigned environment base URL:

- **Sandbox:** `https://sandbox-api.mevonpay.com` (example)
- **Production:** `https://api.mevonpay.com` (example)

> Replace with your official MevonPay hostnames.

---

## Authentication

Pass your secret key in the `Authorization` header.

```http
Authorization: YOUR_SECRET_KEY
Content-Type: application/json
Accept: application/json
```

Some legacy integrations send `Bearer YOUR_SECRET_KEY`. To reduce support issues, MevonPay should support both and document one canonical format (recommended: raw secret key).

---

## API Conventions

- Content type: `application/json`
- Currency: `NGN` unless otherwise stated
- Time format: ISO 8601 where applicable
- Success: HTTP `2xx`
- Client error: HTTP `4xx`
- Server/provider error: HTTP `5xx`

---

## 1) Create Temporary Virtual Account  

Creates a temporary account for incoming payment.

- **Method:** `POST`
- **Path:** `/V1/createtempva.php`

### Request Body

```json
{
  "type": "rubies",
  "fname": "John",
  "lname": "Doe",
  "registration_number": "12345678901"
}
```

### Alternative Identity Field (Legacy)

If `registration_number` is not available, some clients may send:

```json
{
  "type": "rubies",
  "fname": "John",
  "lname": "Doe",
  "bvn": "12345678901"
}
```

### Success Response (Example)

```json
{
  "status": true,
  "message": "Account created successfully",
  "data": {
    "account_number": "1234567890",
    "account_name": "John Doe",
    "bank_name": "Rubies MFB",
    "bank_code": "000023"
  }
}
```

---

## 2) Create Dynamic Virtual Account

Creates a dynamic account for a specified amount.

- **Method:** `POST`
- **Path:** `/V1/createdynamic`

### Request Body

```json
{
  "amount": 15000,
  "currency": "NGN"
}
```

### Success Response (Example)

```json
{
  "status": true,
  "message": "Dynamic account created",
  "data": {
    "accountNumber": "1234567890",
    "accountName": "Checkout Reference Account",
    "bankName": "Rubies MFB",
    "bankCode": "000023",
    "expiresOn": "2026-04-20T12:00:00Z"
  }
}
```

> Note: integrators may receive either snake_case or camelCase keys. MevonPay should standardize to one shape in a versioned API.

---

## 2.1) Create Rubies Personal Account

Creates a Rubies personal account profile through MevonPay (used by WhatsApp Tier 2 wallet flow).

- **Method:** `POST`
- **Path:** `/V1/createrubies`

### Request Body

```json
{
  "action": "create",
  "account_type": "personal",
  "fname": "John",
  "lname": "Doe",
  "phone": "09087654321",
  "dob": "2010-05-20",
  "email": "john@example.com",
  "bvn": "12345678901"
}
```

### Success Response (Example)

```json
{
  "status": true,
  "message": "Personal account created",
  "data": {
    "reference": "RUBY_12345",
    "account_number": "1234567890",
    "account_name": "JOHN DOE",
    "bank_name": "Rubies MFB",
    "bank_code": "000023"
  }
}
```

---

## 3) Bank Service API

Single endpoint with action-based payload.

- **Method:** `POST`
- **Path:** `/V1/bank_service`

### 3.1 Get Bank List

#### Request

```json
{
  "action": "getBankList"
}
```

#### Response (Example)

```json
{
  "status": true,
  "data": [
    {
      "bankCode": "000058",
      "bankName": "GTBank"
    },
    {
      "bankCode": "000044",
      "bankName": "Access Bank"
    }
  ]
}
```

### 3.2 Name Enquiry

#### Request

```json
{
  "action": "nameEnquiry",
  "bankCode": "000058",
  "accountNumber": "0123456789"
}
```

#### Response (Example)

```json
{
  "status": true,
  "data": {
    "account_name": "JOHN DOE",
    "account_number": "0123456789",
    "bank_code": "000058"
  }
}
```

---

## 4) Create Transfer (Payout)

Initiates bank transfer to a beneficiary account.

- **Method:** `POST`
- **Path:** `/V1/createtransfer`

### Request Body

```json
{
  "amount": 5000,
  "bankCode": "000058",
  "bankName": "GTBank",
  "creditAccountName": "John Doe",
  "creditAccountNumber": "0123456789",
  "debitAccountName": "Merchant Wallet",
  "debitAccountNumber": "9999999999",
  "narration": "Withdrawal payout",
  "reference": "TRF_20260413_0001",
  "sessionId": "optional-session-id",
  "currentPassword": "merchant-password"
}
```

### Success Response (Example)

```json
{
  "status": true,
  "responseCode": "00",
  "responseMessage": "Transfer successful",
  "reference": "TRF_20260413_0001"
}
```

### Pending Response (Example)

```json
{
  "status": true,
  "responseCode": "09",
  "responseMessage": "Transaction pending",
  "reference": "TRF_20260413_0001"
}
```

### Response Code Interpretation

- `00` -> Successful
- `09`, `90`, `99` -> Pending (client should reconcile)
- Other codes -> Failed

---

## 5) Webhooks

MevonPay sends event notifications to merchant webhook URLs.

### Event Type

- `funding.success`

### Recommended Merchant Endpoint

- `POST https://merchant-domain.com/webhook/mevonpay`

### Header

```http
Authorization: WEBHOOK_SECRET
Content-Type: application/json
```

### Payload (Example)

```json
{
  "event": "funding.success",
  "data": {
    "account_number": "1234567890",
    "amount": 25000,
    "reference": "FUND_20260413_001",
    "sender": "JOHN DOE",
    "bank_name": "GTBank",
    "timestamp": "2026-04-13T10:33:00Z"
  }
}
```

### Merchant Validation Rules

Merchants should validate:

1. Authorization secret
2. Event equals `funding.success`
3. `data.account_number` is present
4. `data.amount` is positive (if required by merchant flow)

### Recommended Webhook Response

On successful receipt:

```json
{
  "success": true
}
```

Use HTTP `200` when processed/accepted; use `401` for bad secret; use `422` for invalid payload.

---

## 6) Error Response Format (Recommended Standard)

To make integrations stable, MevonPay should standardize all errors in one shape:

```json
{
  "status": false,
  "code": "VALIDATION_ERROR",
  "message": "bankCode is required",
  "errors": {
    "bankCode": [
      "The bankCode field is required."
    ]
  },
  "request_id": "req_abc123"
}
```

---

## 7) Idempotency and Reliability (Recommended)

For payout and VA creation endpoints:

- Accept `Idempotency-Key` header
- Return same result when same key is retried within defined window
- Include `request_id` in all responses/logs for support tracing

For webhooks:

- Retry on non-2xx responses
- Include delivery attempt metadata (optional headers)

---

## 8) Quick Integration Checklist

1. Generate and store MevonPay secret key securely.
2. Integrate one VA endpoint (`createdynamic` or `createtempva`) first.
3. Save `account_number` and internal transaction reference.
4. Implement webhook endpoint and secret verification.
5. On `funding.success`, match by `account_number` (and reference if available).
6. Add bank list + name enquiry for account validation UX.
7. Add payout transfer integration if required.
8. Log raw request/response for support diagnostics.

---

## Dedicated Validation Endpoint (Test Meveon)

To help documentation QA and partner validation, this project now includes a single dedicated endpoint:

- `POST /api/v1/test-meveon`
- `GET /api/v1/test-meveon` (for quick checks)

### Security

- Optional shared secret via `MEVONPAY_TEST_SECRET`
- Send secret with header: `X-Test-Meveon-Secret: <secret>`
- Endpoint is rate-limited

### Supported actions

- `ping`
- `createtempva`
- `createdynamic`
- `getBankList`
- `nameEnquiry`
- `createtransfer` (guarded by `MEVONPAY_TEST_ALLOW_TRANSFER=true`)
- `createrubies_personal`
- `createrubies_business`
- `rubies_electricity_getInfo`
- `rubies_electricity_verify`
- `rubies_electricity_buy`
- `rubies_cable_getInfo`
- `rubies_cable_verify`
- `rubies_cable_buy`

### Example: Ping

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{"action":"ping"}'
```

### Example: Temp VA test

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{
    "action":"createtempva",
    "fname":"John",
    "lname":"Doe",
    "registration_number":"12345678901"
  }'
```

### Example: Dynamic VA test

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{
    "action":"createdynamic",
    "amount":15000,
    "currency":"NGN"
  }'
```

### Example: Name enquiry test

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{
    "action":"nameEnquiry",
    "bankCode":"000058",
    "accountNumber":"0123456789"
  }'
```

### Example: Transfer test (live money movement)

Only enabled when `MEVONPAY_TEST_ALLOW_TRANSFER=true`:

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{
    "action":"createtransfer",
    "amount":5000,
    "bankCode":"000058",
    "bankName":"GTBank",
    "creditAccountName":"John Doe",
    "creditAccountNumber":"0123456789",
    "reference":"TRF_TEST_001",
    "confirm_live_transfer":true
  }'
```

### Example: Rubies personal account creation test

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{
    "action":"createrubies_personal",
    "fname":"John",
    "lname":"Doe",
    "phone":"09087654321",
    "dob":"2010-05-20",
    "email":"john@example.com",
    "bvn":"12345678901"
  }'
```

Maps to provider endpoint:

- `POST /V1/createrubies`
- payload:
  - `action=create`
  - `account_type=personal`
  - `fname`, `lname`, `phone`, `dob`, `email`, `bvn` (or `nin`)

### Example: Rubies business account creation test

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{
    "action":"createrubies_business",
    "cac":"RC123456",
    "phone":"09087654321",
    "dob":"2010-05-20",
    "email":"contact@business.com"
  }'
```

Maps to provider endpoint:

- `POST /V1/createrubies`
- payload:
  - `action=create`
  - `account_type=business`
  - `cac`, `phone`, `dob`, `email`

### Rubies electricity tests

Get providers/plans:

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{"action":"rubies_electricity_getInfo"}'
```

Verify meter:

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{
    "action":"rubies_electricity_verify",
    "meter":"45056532877",
    "providerCode":"abuja-electric",
    "planCode":"prepaid"
  }'
```

Buy electricity token:

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{
    "action":"rubies_electricity_buy",
    "meter":"45056532877",
    "providerCode":"abuja-electric",
    "planCode":"prepaid",
    "amount":2000,
    "customerName":"UZOMA GODEREY ONYEMA",
    "phone":"08012345678"
  }'
```

### Rubies cable TV tests

Get providers/plans:

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{"action":"rubies_cable_getInfo"}'
```

Verify smartcard:

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{
    "action":"rubies_cable_verify",
    "smartcard":"8061700508",
    "providerCode":"gotv",
    "planCode":"cwgotvsmallie"
  }'
```

Buy cable subscription:

```bash
curl -X POST "https://your-domain.com/api/v1/test-meveon" \
  -H "Content-Type: application/json" \
  -H "X-Test-Meveon-Secret: your-test-secret" \
  -d '{
    "action":"rubies_cable_buy",
    "smartcard":"8061700508",
    "providerCode":"gotv",
    "planCode":"cwgotvsmallie",
    "amount":1900,
    "customerName":"JOSEPHAT ANENECHUKWU IKELIIQINSA",
    "phone":"08012345678"
  }'
```

Provider endpoints used by these actions:

- Electricity: `POST /V1/electricity` with `action` = `getInfo|verify|buy`
- Cable TV: `POST /V1/cabletv` with `action` = `getInfo|verify|buy`

---

## 9) Versioning Recommendation

Current paths are action/legacy mixed (`/V1/createtempva.php`, `/V1/createdynamic`, `/V1/bank_service`).

Recommended next version (example):

- `POST /v2/virtual-accounts/temp`
- `POST /v2/virtual-accounts/dynamic`
- `GET /v2/banks`
- `POST /v2/banks/name-enquiry`
- `POST /v2/transfers`

This makes the API easier for third-party developers to adopt.

---

## 10) Contact / Support (Template)

- Integration support email: `integrations@mevonpay.com`
- Incident support: `support@mevonpay.com`
- Status page: `https://status.mevonpay.com`

> Replace placeholders with official MevonPay contact channels.
# MevonPay Integration Documentation

## Scope

This document summarizes the current MevonPay-related implementation in this codebase, including:

- Merchant checkout virtual account creation (dynamic + temp)
- Webhook processing and payment approval
- External API assignment per business/service
- MevonPay bank list and name enquiry integrations
- Payout transfers through `createtransfer`
- Rentals + WhatsApp wallet flows that also rely on Mevon/MevonRubies
- Known resiliency fixes and troubleshooting guidance

---

## 1) Integration Components

### Core services

- `app/Services/MevonPayVirtualAccountService.php`
  - Creates temporary VAs via `POST /V1/createtempva.php`
  - Creates dynamic VAs via `POST /V1/createdynamic`
  - Handles mixed authorization header formats (`Bearer <token>` vs raw token)
- `app/Http/Controllers/Api/MevonPayWebhookController.php`
  - Handles `funding.success` webhook events
  - Matches by `account_number` as source of truth
  - Approves pending external payments or routes to WhatsApp wallet top-up handlers
- `app/Services/MevonPayBankService.php`
  - `action=getBankList` via `POST /V1/bank_service`
  - `action=nameEnquiry` for account verification
- `app/Services/MavonPayTransferService.php`
  - Sends payouts using `POST /V1/createtransfer`
  - Normalizes provider responses into `successful`, `pending`, or `failed`

### Assignment + business control

- `app/Models/Business.php`
  - Reads external provider assignment mode per service:
    - `external_only`
    - `hybrid`
    - `internal_only`
  - Reads VA generation mode:
    - `dynamic` (createdynamic)
    - `temp` (createtempva)
- `app/Http/Controllers/Admin/ExternalApiController.php`
  - Admin assignment of provider to businesses
  - Service scoping for provider usage (invoice, membership, rental, etc.)
  - Toggle + save per-business mode and VA type

### Payment creation and account assignment

- `app/Services/PaymentService.php`
  - Decides external vs internal flow using business provider config
  - Creates external account records (`is_external = true`) on successful VA generation
  - Falls back to internal pool in hybrid mode if provider call fails
- `app/Services/AccountNumberService.php`
  - Supports external-only and preferred external account selection
  - Falls back to internal business/pool accounts where applicable

---

## 2) API Endpoints

### Public webhook routes

Defined in `routes/api.php`:

- `POST /api/v1/webhook/mevonpay`
- `POST /api/v1/webhooks/mevonpay` (alias)
- `POST /api/v1/webhook/sla` (backward compatibility)
- `POST /api/v1/webhooks/sla` (alias)
- `POST /api/v1/webhook/mavonpay` (legacy/backward compatibility)
- `POST /api/v1/webhooks/mavonpay` (alias)

All routes above are handled by `MevonPayWebhookController::receive`.

### Admin utility/test routes

Defined in `routes/admin.php`:

- `POST /admin/test-transaction/mevonpay-temp-va`
- `POST /admin/test-transaction/mevonpay-dynamic-va`
- `GET /admin/external-apis`
- `PUT /admin/external-apis/{externalApi}/businesses`
- `GET /admin/external-apis/mevonpay/webhook-sources`

---

## 3) Database Structures

### External API assignment

Migrations:

- `database/migrations/2026_03_24_230001_create_external_apis_tables.php`
  - Creates:
    - `external_apis`
    - `business_external_api` pivot
  - Seeds provider `mevonpay`
- `database/migrations/2026_03_24_230002_backfill_mevonpay_business_assignments.php`
  - Backfills businesses with `uses_external_account_numbers = true` into pivot
- `database/migrations/2026_03_26_000001_add_va_generation_mode_to_business_external_api_table.php`
  - Adds `va_generation_mode` (`dynamic` or `temp`)

### Payment source constants

`app/Models/Payment.php` includes:

- `external_mevonpay`
- `external_sla`
- `external_mavonpay` (legacy)
- `whatsapp_wallet` (wallet top-up accounting rows)

---

## 4) Configuration and Environment Variables

Configured in `config/services.php` under `mevonpay` and `mevonrubies`.

### Required for MevonPay core

- `MEVONPAY_BASE_URL`
- `MEVONPAY_SECRET_KEY`

### Webhook security / source filtering

- `MEVONPAY_WEBHOOK_SECRET`
  - Fallback chain: `SLA_WEBHOOK_SECRET` -> `MAVONPAY_WEBHOOK_SECRET`
- `MEVONPAY_WEBHOOK_ALLOWED_IPS` (comma-separated)
- `MEVONPAY_WEBHOOK_ALLOWED_DOMAINS` (comma-separated)

### Transfer payout config

- `MEVONPAY_DEBIT_ACCOUNT_NAME`
- `MEVONPAY_DEBIT_ACCOUNT_NUMBER`
- `MEVONPAY_CURRENT_PASSWORD`

### Timeouts and logging

- `MEVONPAY_TIMEOUT_SECONDS` (default `20`)
- `MEVONPAY_CONNECT_TIMEOUT_SECONDS` (default `3`)
- `MEVONPAY_ACCOUNT_LOGS_ENABLED` (bool, request/response payload logging for VA creation)

### Temp VA support

- `MEVONPAY_TEMP_VA_REGISTRATION_NUMBER`

### Rubies (rentals/WhatsApp Tier 2)

- `MEVONRUBIES_BASE_URL` (optional; falls back to `MEVONPAY_BASE_URL`)
- `MEVONRUBIES_SECRET_KEY` (optional; falls back to `MEVONPAY_SECRET_KEY`)
- `MEVONRUBIES_TIMEOUT_SECONDS`
- `MEVONRUBIES_CREATE_PATH` (default `/V1/createrubies`)
- `MEVONRUBIES_DEFAULT_GENDER`
- `MEVONRUBIES_RENTER_PLACEHOLDER_DOB`

---

## 5) Main Runtime Flows

### A) Merchant checkout payment creation

1. Request enters `PaymentService::createPayment`.
2. Business config determines provider mode (`external_only`, `hybrid`, `internal_only`) for service type.
3. If external is enabled:
   - VA mode `temp` -> call `createTempVa`.
   - VA mode `dynamic` -> call `createDynamicVa`.
4. On success:
   - Upsert `account_numbers` row with `is_external = true`, `external_provider = mevonpay`.
   - Create pending `payments` row with `payment_source = external_mevonpay`.
   - Set `expires_at` if provider returned `expiresOn`.
5. If external fails in `hybrid` mode:
   - Fallback to internal account assignment.
6. If external fails in `external_only` mode:
   - Fail request (no internal fallback).

### B) Webhook approval flow

1. Webhook enters `MevonPayWebhookController::receive`.
2. Guard checks:
   - Allowlist (IP/domain) if configured.
   - Authorization secret if configured.
3. Event filter:
   - Processes only `event = funding.success`.
4. Resolves by `data.account_number`.
5. If matching pending payment exists:
   - Approves payment
   - Stores reference
   - Increments business balance (with charges)
   - Dispatches `PaymentApproved` event (webhook fan-out to merchant)
6. If no payment match:
   - Attempts WhatsApp top-up handlers (Tier 1 pending VA and Tier 2 permanent VA)
   - Otherwise returns success with `"No pending payment"` to avoid endless provider retries for unknown references

### C) Bank sync and account validation

- `MevonPayBankService::getBankList` fetches provider bank list.
- `BankDirectorySyncService` upserts into `banks` table.
- Fallback to local config (`config/banks.php` and `config/nigerian_banks_fallback.php`) when provider list is unavailable.
- Admin account validation tries Mevon `nameEnquiry` first before NUBAN for speed and reliability.

### D) Payout transfer flow

- `MavonPayTransferService::createTransfer` calls `POST /V1/createtransfer`.
- Response normalization:
  - `00` -> `successful`
  - `09`, `90`, `99` -> `pending`
  - otherwise `failed`
- Additional success fallbacks:
  - Success inferred from success message even when code missing
  - 2xx + empty body treated as success (provider edge case)

---

## 6) WhatsApp + Rentals Extensions

### WhatsApp Tier 1 top-up (temporary VAs)

- Service: `app/Services/Whatsapp/WhatsappWalletTier1TopupVaService.php`
- Uses Mevon `createtempva` to issue short-lived VA.
- Creates linked pending records:
  - `whatsapp_wallet_pending_topups`
  - `payments` row (`payment_source = whatsapp_wallet`, pending)
- Webhook fulfills top-up by account number and credits wallet.

### WhatsApp Tier 2 / permanent VA

- Wallet stores:
  - `mevon_virtual_account_number`
  - `mevon_bank_name`
  - `mevon_bank_code`
  - `mevon_reference`
- Permanent VA webhook credits wallet and creates an approved admin-visible payment record.

### Rentals external flow

- Service: `app/Services/RentalPaymentService.php`
- Uses `MevonRubiesVirtualAccountService` for renter-facing reusable Rubies VA.
- Supports external-only/hybrid behavior with internal fallback.
- Handles `temp` mode by avoiding duplicate pending internal rows.

---

## 7) Troubleshooting and Resiliency Already Implemented

The following issues have explicit handling in code:

- **Mixed auth formats:** supports both raw token and `Bearer` token depending on endpoint behavior.
- **Webhook spoofing risks:** optional secret validation + sender allowlist (IP/domain).
- **Unknown webhook payloads:** non-`funding.success` events are safely ignored.
- **Missing amount in webhook:** top-up flow records metadata for zero-amount events instead of crashing.
- **No pending payment match:** tries wallet top-up fulfillment before final `"No pending payment"` response.
- **Provider non-2xx responses:** logs warning and surfaces clear runtime errors.
- **Empty reply/timeouts on name enquiry:** `cURL error 52` fallback marks verification as timeout fallback instead of hard-failing.
- **Bank code format mismatches:** normalizer supports legacy/NIP mapping and fallback variants.
- **Transfer response inconsistencies:** missing code + successful message / empty 2xx body normalized to success.
- **External VA creation failure in hybrid mode:** automatic fallback to internal pool prevents merchant checkout breakage.
- **Rubies payload shape drift:** parser supports root-level and nested `data` fields; emits detailed debug snapshots when parsing fails.

---

## 8) Operations and Verification

### Bank directory sync

- Command: `php artisan banks:sync`
- Strict mode: `php artisan banks:sync --no-fallback`

### Rubies connectivity test

- Command: `php artisan mevon:rubies-test-initiate --fname=John --lname=Doe --phone=08012345678 --dob=1990-01-01 --email=test@example.com --bvn=12345678901`

### Admin checks

- Open External APIs page and verify:
  - Provider enabled
  - Business assignment enabled
  - Correct mode (`external_only` / `hybrid` / `internal_only`)
  - Correct VA generation mode (`dynamic` / `temp`)
  - Correct service scoping
- Review webhook source diagnostics:
  - `GET /admin/external-apis/mevonpay/webhook-sources`

### Logs to inspect

- Application logs for:
  - `MevonPay ... non-2xx response`
  - `MEVONPAY webhook blocked ...`
  - `MavonPay createtransfer normalized result`
  - `whatsapp.wallet...` top-up logs

---

## 9) Recommended Deployment Checklist

1. Set all `MEVONPAY_*` variables in production (including webhook secret and timeout values).
2. If using source filtering, populate IP/domain allowlists correctly to avoid blocking legitimate provider webhooks.
3. Configure business-provider assignment in admin panel per service.
4. Run bank sync (`banks:sync`) and verify `banks` table population.
5. Test both VA modes:
   - dynamic (`createdynamic`)
   - temp (`createtempva`)
6. Trigger real/sandbox `funding.success` webhook and verify:
   - payment status goes `pending -> approved`
   - balance increment occurs
   - merchant webhook event is emitted
7. Validate payout flow with small amount and confirm normalization bucket is correct.

---

## 10) Current Status Summary

The integration is not a single endpoint hookup; it is a full provider subsystem across payment creation, webhook approval, payout transfers, bank resolution, admin assignment controls, and WhatsApp/rentals wallet extensions. The codebase already includes multiple production hardening fixes for provider inconsistencies and fallback behavior.
