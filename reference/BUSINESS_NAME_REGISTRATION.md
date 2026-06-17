# Business name registration — backend reference

CheckoutPay consumer API and admin workflow for **CAC business name registration** and the **business receive account** on CheckoutNow Receive Funds.

Mobile app contract: `checkoutnow/docs/BUSINESS_NAME_REGISTRATION.md`.

---

## Endpoints

| Method | Path | Auth |
|--------|------|------|
| `GET` | `/api/v1/consumer/business-name-registration` | Bearer Sanctum (`consumer` ability) |
| `POST` | `/api/v1/consumer/business-name-registration` | Bearer + `multipart/form-data` |
| `GET` | `/api/v1/consumer/wallet` | Includes `business_pay_in` after approval |

Routes: `routes/api.php` → `ConsumerBusinessNameRegistrationController`.

---

## Feature flag (`.env`)

```env
CONSUMER_BUSINESS_NAME_REGISTRATION_ENABLED=true
CONSUMER_BUSINESS_NAME_REGISTRATION_FEE=15000
CONSUMER_BUSINESS_NAME_REGISTRATION_FEE_CURRENCY=NGN
CONSUMER_BUSINESS_NAME_REGISTRATION_HOURS_MIN=12
CONSUMER_BUSINESS_NAME_REGISTRATION_HOURS_MAX=24
```

Config: `config/consumer_wallet.php` → `business_name_registration.*`.

**Live rule:** `enabled=true` **and** `fee_amount > 0`. Otherwise:

- `GET` returns `config.available: false` and `coming_soon_message`
- `POST` returns **403** with the same message

After changing `.env`: `php artisan config:clear`.

---

## GET `consumer/business-name-registration`

### Response `data`

| Field | Type | Description |
|-------|------|-------------|
| `config` | object | Feature flags, fee, requirements, SLA hours |
| `requests` | array | All applications for wallet (newest first) |
| `business_account` | object \| null | Active business VA (same shape as `pay_in`) |

### `config` (live)

| Field | Type |
|-------|------|
| `available` | `true` |
| `fee_amount` | number |
| `fee_currency` | string (e.g. `NGN`) |
| `fee_label` | string (formatted, e.g. `₦15,000.00`) |
| `requirements` | string[] |
| `estimated_completion_hours_min` | int (default 12) |
| `estimated_completion_hours_max` | int (default 24) |

### `config` (not live)

| Field | Type |
|-------|------|
| `available` | `false` |
| `coming_soon_message` | string |

### `requests[]` item

| Field | Type |
|-------|------|
| `id` | string (`bnr_*` public id) |
| `reference` | string (`BNR-YYYY-*`) |
| `proposed_name` | string |
| `alternate_name` | string \| null |
| `status` | see workflow below |
| `progress_percent` | 0–100 |
| `status_label` | string \| null |
| `submitted_at` | ISO 8601 \| null |
| `approved_at` | ISO 8601 \| null |
| `rejected_reason` | string \| null |
| `fee_amount` | number \| null |
| `fee_currency` | string \| null |
| `business_account` | object \| null (when approved for that row) |

### Status values

| Status | Default `progress_percent` |
|--------|---------------------------|
| `pending_payment` | 5 |
| `paid` | 15 |
| `processing` | 40 |
| `under_review` | 65 |
| `approved` | 100 |
| `rejected` | 0 |

---

## POST `consumer/business-name-registration`

**Content-Type:** `multipart/form-data`

| Field | Required | Rules |
|-------|----------|-------|
| `proposed_name` | yes | min 3 chars |
| `alternate_name` | no | max 200 |
| `owner_full_name` | yes | |
| `owner_phone` | yes | |
| `owner_email` | yes | valid email |
| `business_address` | yes | |
| `nature_of_business` | yes | |
| `id_type` | yes | `nin` \| `passport` \| `drivers_license` |
| `pin` | yes | 4 digits |
| `id_document` | yes | jpeg/png/webp, max 5 MB |

### Submit flow

1. Verify wallet PIN (`ConsumerWalletPinVerifier`)
2. Check balance ≥ fee
3. Store ID on `local` disk (private)
4. Debit wallet; ledger type `business_name_registration_fee`
5. Create `business_name_registrations` row: `status: paid`, `progress_percent: 15`

### Errors

| Case | HTTP | Message (example) |
|------|------|-------------------|
| Feature off | 403 | Business name registration coming soon. |
| Wrong PIN | 422 | Invalid PIN |
| PIN locked | 423 | PIN locked. Try later. |
| Insufficient balance | 422 | Insufficient wallet balance |
| Missing/invalid file | 422 | validation message |

### Success `data`

| Field | Type |
|-------|------|
| `reference` | string |
| `status` | string (`paid`) |
| `proposed_name` | string |
| `progress_percent` | number (15) |
| `fee_amount` | number |
| `fee_currency` | string |
| `estimated_completion_hours_min` | number |
| `estimated_completion_hours_max` | number |

---

## GET `consumer/wallet` — `business_pay_in`

After admin approval, wallet payload includes:

```json
"business_pay_in": {
  "kind": "permanent",
  "account_number": "9876543210",
  "account_name": "Acme Ventures Ltd",
  "bank_name": "Rubies MFB",
  "bank_code": "090175",
  "expires_at": null
}
```

`null` until approved. Same shape as personal `pay_in`.

---

## Admin workflow

| URL | Purpose |
|-----|---------|
| `/admin/business-name-registrations` | Queue / list |
| `/admin/business-name-registrations/{id}` | Review, update status, enter business VA |
| `/admin/business-name-registrations/{id}/id-document` | Download ID (admin auth) |

Controller: `BusinessNameRegistrationAdminController`  
Workflow: `BusinessNameRegistrationWorkflowService`

On **`approved`**: set business VA fields on the registration; copies to `whatsapp_wallets.business_pay_in_*`.

Linked from WhatsApp wallet admin nav and sidebar.

---

## Database

| Table / column | Purpose |
|----------------|---------|
| `business_name_registrations` | Applications (multiple per wallet) |
| `whatsapp_wallets.business_pay_in_account_number` | Active business receive VA |
| `whatsapp_wallets.business_pay_in_account_name` | Registered business name |
| `whatsapp_wallets.business_pay_in_bank_name` | Bank name |
| `whatsapp_wallets.business_pay_in_bank_code` | Bank code |

Migrations:

- `2026_06_15_120000_create_business_name_registrations_table.php`
- `2026_06_15_120001_add_business_pay_in_to_whatsapp_wallets_table.php`

Model: `App\Models\BusinessNameRegistration`

---

## Code map

| File | Role |
|------|------|
| `ConsumerBusinessNameRegistrationController.php` | GET index, POST store |
| `ConsumerBusinessNameRegistrationService.php` | Config, index, submit, serialize |
| `BusinessNameRegistrationWorkflowService.php` | Admin status transitions |
| `ConsumerWalletApiController.php` | `business_pay_in` on wallet GET |
| `BusinessNameRegistrationAdminController.php` | Admin UI |

---

## Tests

```bash
php artisan test --filter=ConsumerBusinessNameRegistrationTest
```

Covers: feature off/on, submit + debit, insufficient balance, wrong PIN, admin approval → `business_pay_in`.

---

## Deploy checklist (production API)

1. `git pull` on API server (`check-outpay.com`)
2. `php artisan migrate --force`
3. Set `.env` vars above; `php artisan config:clear`
4. Confirm `GET /api/v1/consumer/business-name-registration` with valid token returns `config.available: true`
5. Admin can review at `/admin/business-name-registrations`

CheckoutNow web: rebuild with `VITE_CHECKOUT_API_BASE=https://check-outpay.com/api/v1`.
