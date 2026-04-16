# MevonPay Integration Notes (Internal - Tested Implementation)

## Purpose

This document reflects the current, tested, and working MevonPay implementation in this codebase.
It is an internal engineering reference and not public provider documentation.

---

## Scope Covered

- Merchant checkout VA creation (dynamic and temp)
- Webhook approval flow for external payments
- Bank list sync and name enquiry validation
- Transfer/payout flow normalization
- Business/provider assignment and fallback behavior
- WhatsApp wallet and rentals extensions using Mevon/MevonRubies

---

## Core Integration Components

### Payment + account creation

- `app/Services/PaymentService.php`
  - decides `external_only` / `hybrid` / `internal_only` per business + service
  - creates pending payments with `payment_source = external_mevonpay` for external flow
- `app/Services/MevonPayVirtualAccountService.php`
  - temp VA: `POST /V1/createtempva.php`
  - dynamic VA: `POST /V1/createdynamic`
  - handles mixed auth header behavior where required
- `app/Services/AccountNumberService.php`
  - selects external accounts where enabled
  - falls back to internal pool/business accounts where applicable

### Webhook and event handling

- `app/Http/Controllers/Api/MevonPayWebhookController.php`
  - processes only `funding.success`
  - resolves by `data.account_number`
  - approves pending payments and dispatches merchant-facing approval events
  - attempts WhatsApp wallet top-up resolution when no checkout payment match exists

### Bank + transfer services

- `app/Services/MevonPayBankService.php`
  - bank list / name enquiry via `POST /V1/bank_service`
- `app/Services/MavonPayTransferService.php`
  - payouts via `POST /V1/createtransfer`
  - normalized statuses: `successful`, `pending`, `failed`

### Provider assignment controls

- `app/Models/Business.php`
  - provider mode: `external_only`, `hybrid`, `internal_only`
  - VA generation mode: `dynamic`, `temp`
- `app/Http/Controllers/Admin/ExternalApiController.php`
  - per-business provider assignment, service scoping, mode/VA mode management

---

## Active Endpoints in This Project

### Webhooks (`routes/api.php`)

- `POST /api/v1/webhook/mevonpay`
- `POST /api/v1/webhooks/mevonpay`
- `POST /api/v1/webhook/sla`
- `POST /api/v1/webhooks/sla`
- `POST /api/v1/webhook/mavonpay`
- `POST /api/v1/webhooks/mavonpay`

All routes above are handled by `MevonPayWebhookController::receive`.

### Admin/Test utility endpoints

- `POST /api/v1/test-meveon`
- `GET /api/v1/test-meveon`
- `POST /admin/test-transaction/mevonpay-temp-va`
- `POST /admin/test-transaction/mevonpay-dynamic-va`
- `GET /admin/external-apis`
- `PUT /admin/external-apis/{externalApi}/businesses`
- `GET /admin/external-apis/mevonpay/webhook-sources`

Test controller: `app/Http/Controllers/Api/TestMeveonController.php`.

---

## Configuration and Environment Variables

### Core

- `MEVONPAY_BASE_URL`
- `MEVONPAY_SECRET_KEY`

### Webhook security

- `MEVONPAY_WEBHOOK_SECRET`
  - fallback chain: `SLA_WEBHOOK_SECRET` -> `MAVONPAY_WEBHOOK_SECRET`
- `MEVONPAY_WEBHOOK_ALLOWED_IPS`
- `MEVONPAY_WEBHOOK_ALLOWED_DOMAINS`

### Transfers

- `MEVONPAY_DEBIT_ACCOUNT_NAME`
- `MEVONPAY_DEBIT_ACCOUNT_NUMBER`
- `MEVONPAY_CURRENT_PASSWORD`

### Timeouts and logs

- `MEVONPAY_TIMEOUT_SECONDS` (default `20`)
- `MEVONPAY_CONNECT_TIMEOUT_SECONDS` (default `3`)
- `MEVONPAY_ACCOUNT_LOGS_ENABLED`

### Temp VA

- `MEVONPAY_TEMP_VA_REGISTRATION_NUMBER`

### Rubies (WhatsApp/Rentals)

- `MEVONRUBIES_BASE_URL` (optional fallback to `MEVONPAY_BASE_URL`)
- `MEVONRUBIES_SECRET_KEY` (optional fallback to `MEVONPAY_SECRET_KEY`)
- `MEVONRUBIES_TIMEOUT_SECONDS`
- `MEVONRUBIES_CREATE_PATH` (default `/V1/createrubies`)
- `MEVONRUBIES_DEFAULT_GENDER`
- `MEVONRUBIES_RENTER_PLACEHOLDER_DOB`

---

## Verified Runtime Behavior

### Checkout payment creation

1. `PaymentService::createPayment` checks business provider mode + VA mode.
2. External enabled:
   - `temp` -> `createTempVa`
   - `dynamic` -> `createDynamicVa`
3. External success:
   - upsert external `account_numbers` (`is_external = true`, provider `mevonpay`)
   - create pending payment with external source
4. External failure:
   - `hybrid` -> fallback to internal account flow
   - `external_only` -> return failure

### Webhook approval

1. `MevonPayWebhookController::receive` validates source/secret if configured.
2. Ignores non-`funding.success` events safely.
3. Matches by `data.account_number`.
4. If pending payment exists:
   - approve payment
   - store reference
   - increment business balance
   - dispatch approval event
5. If no pending payment:
   - attempt WhatsApp top-up handlers
   - return success response to avoid unnecessary retries

### Transfer normalization

- `00` -> `successful`
- `09`, `90`, `99` -> `pending`
- other codes -> `failed`
- success fallback also supports success-message-only and empty-2xx provider edge cases

---

## Database / Assignment Structures

Primary migrations:

- `database/migrations/2026_03_24_230001_create_external_apis_tables.php`
- `database/migrations/2026_03_24_230002_backfill_mevonpay_business_assignments.php`
- `database/migrations/2026_03_26_000001_add_va_generation_mode_to_business_external_api_table.php`

Relevant `Payment` sources include:

- `external_mevonpay`
- `external_sla`
- `external_mavonpay` (legacy)
- `whatsapp_wallet`

---

## WhatsApp and Rentals Status

- WhatsApp Tier 1 top-up uses temp VA flow and pending top-up records.
- WhatsApp Tier 2/permanent VA webhook flow is active and credits wallets appropriately.
- Rentals flow uses `MevonRubiesVirtualAccountService` and supports external-only/hybrid behavior with fallback.

---

## Known Resiliency Implemented

- Mixed auth/header format handling
- Optional webhook allowlist + secret validation
- Safe ignore for non-target webhook events
- Name enquiry timeout/empty-reply fallback handling
- Bank code normalization/fallback handling
- Hybrid-mode fallback to internal accounts on external VA failure
- Rubies response parser support for root-level and nested `data` payload shapes

---

## Operations Checklist

- Bank sync: `php artisan banks:sync`
- Strict bank sync: `php artisan banks:sync --no-fallback`
- Rubies test command:
  - `php artisan mevon:rubies-test-initiate --fname=John --lname=Doe --phone=08012345678 --dob=1990-01-01 --email=test@example.com --bvn=12345678901`
- Validate admin assignment and VA mode settings in external API admin page
- Confirm webhook source diagnostics at:
  - `GET /admin/external-apis/mevonpay/webhook-sources`

---

## Current Status

The tested implementation is working as a full provider subsystem (not a single endpoint hookup), covering VA creation, webhook-driven approval, transfers, bank resolution, assignment controls, and WhatsApp/rentals extensions, with production-oriented fallback and resiliency handling in place.
