# Business account onboarding — backend reference

CheckoutPay consumer API and admin workflow for **merchant business account** applications from CheckoutNow (Receive → Business → **Get business account**).

This is distinct from **business name registration (BNR)**, which only provisions a receive VA on the wallet. Onboarding creates a `businesses` row, links `whatsapp_wallets.linked_business_id`, and unlocks the merchant dashboard after admin approval + in-app password setup.

Mobile app contract: `checkoutnow/docs/BUSINESS_WALLET.md` (onboarding section).

---

## Endpoints

| Method | Path | Auth |
|--------|------|------|
| `GET` | `/api/v1/consumer/business-account/onboarding` | Bearer Sanctum (`consumer` ability) |
| `POST` | `/api/v1/consumer/business-account/onboarding` | Bearer + JSON or `multipart/form-data` (optional CAC doc) |
| `POST` | `/api/v1/consumer/business-account/onboarding/password` | Bearer + JSON |

Routes: `routes/api.php` → `ConsumerBusinessAccountOnboardingController`.

---

## Feature flag (`.env`)

```env
CONSUMER_BUSINESS_ACCOUNT_ONBOARDING_ENABLED=true
CONSUMER_BUSINESS_ACCOUNT_ONBOARDING_FEE=0
CONSUMER_BUSINESS_ACCOUNT_ONBOARDING_FEE_CURRENCY=NGN
CONSUMER_BUSINESS_ACCOUNT_ONBOARDING_COMING_SOON="Business account onboarding coming soon."
CONSUMER_BUSINESS_ACCOUNT_DASHBOARD_LOGIN_URL=/dashboard/login
```

Config: `config/consumer_wallet.php` → `business_account_onboarding.*`.

**Live rule:** `enabled=true`. Fee may be `0` (free) or a positive amount debited from wallet on submit.

When disabled:

- `GET` returns `config.available: false` and `coming_soon_message`
- `POST` returns **403** with the same message

After changing `.env`: `php artisan config:clear`.

---

## GET `consumer/business-account/onboarding`

### Response `data`

| Field | Type | Description |
|-------|------|-------------|
| `config` | object | Feature flags, fee, plans, categories |
| `applications` | array | All applications for wallet (newest first) |
| `active_application` | object \| null | In-progress application (`submitted`, `under_review`, `approved`, `awaiting_password`) |
| `can_apply` | boolean | `false` if linked business or blocking application exists |
| `linked_business` | object \| null | Linked merchant summary when `linked_business_id` set |
| `prefill` | object \| null | Prefill from latest approved BNR (name, email, phone, address) |

### `config` (live)

| Field | Type |
|-------|------|
| `available` | `true` |
| `fee_amount` | number |
| `fee_currency` | string (e.g. `NGN`) |
| `fee_label` | string \| null (formatted when fee > 0) |
| `account_plans` | array of `{ id, label, description }` |
| `service_categories` | array of `{ id, label }` |
| `dashboard_login_url` | string (absolute URL) |

### Application status values

| Status | Default `progress_percent` | Notes |
|--------|---------------------------|-------|
| `submitted` | 20 | User submitted; awaiting admin |
| `under_review` | 50 | Admin marked in review |
| `approved` | 75 | Legacy intermediate (approve flow skips to `awaiting_password`) |
| `awaiting_password` | 90 | Business created; user must set dashboard password in app |
| `active` | 100 | Password set; onboarding complete |
| `rejected` | 0 | Declined with `rejected_reason` |

---

## POST `consumer/business-account/onboarding`

### Body (JSON)

| Field | Required | Notes |
|-------|----------|-------|
| `account_plan` | yes | `payments_only` \| `payments_and_web` |
| `service_categories` | no | Required for web plan; `payments` always included |
| `business_name` | yes | min 3 chars |
| `email` | yes | Must not already exist on `businesses` |
| `phone` | no | |
| `address` | yes | |
| `website_url` | conditional | Required when `account_plan=payments_and_web` |
| `pin` | yes | 4-digit wallet PIN |
| `cac_document` | no | multipart only; jpeg/png/webp/pdf, max 5 MB |

### Guards

- Reject if wallet already has `linked_business_id`
- Reject duplicate active application
- Reject if fee > 0 and insufficient balance
- Invalid PIN → **422** `Invalid PIN`

### Fee debit

When `fee_amount > 0`, debits wallet with transaction type `business_account_onboarding_fee`.

---

## POST `consumer/business-account/onboarding/password`

After admin approval (`awaiting_password`):

| Field | Required |
|-------|----------|
| `password` | yes, min 8 |
| `password_confirmation` | yes, must match |

Sets hashed password on linked `Business`, marks application `active`, returns `dashboard_login_email` and `dashboard_login_url`.

---

## Admin workflow

Routes: `routes/admin.php` → `BusinessAccountApplicationAdminController`.

| Path | Purpose |
|------|---------|
| `/admin/business-account-applications` | Queue (filter by status, search) |
| `/admin/business-account-applications/{id}` | Review detail + CAC download |
| Approve action | Creates `Business`, optional `BusinessWebsite`, links wallet, sets `awaiting_password` |
| Reject action | Sets `rejected` + reason; clears `active_business_account_application_id` on wallet |

Service: `BusinessAccountOnboardingWorkflowService`.

On approve:

1. `Business::create` with random password (user sets via app)
2. `BusinessWebsite` if `website_url` provided (`is_approved=false`)
3. Copy BNR `business_pay_in_*` to business Rubies fields when present
4. `ConsumerBusinessWalletLedgerService::syncBalanceFromLinkedBusiness` → sets `linked_business_id`
5. Application → `awaiting_password`; send email verification

---

## Data model

Table: `business_account_applications`  
Model: `App\Models\BusinessAccountApplication`

Wallet FK: `whatsapp_wallets.active_business_account_application_id`

Business column: `businesses.service_categories` (JSON)

---

## Rollout

1. Deploy backend + admin UI with `CONSUMER_BUSINESS_ACCOUNT_ONBOARDING_ENABLED=false`
2. `php artisan migrate --force`
3. Enable on staging: `CONSUMER_BUSINESS_ACCOUNT_ONBOARDING_ENABLED=true`, fee `0`
4. Ship CheckoutNow mobile sheet + Receive button
5. Production: enable env, `php artisan config:clear`

Tests: `tests/Feature/Api/ConsumerBusinessAccountOnboardingTest.php`
