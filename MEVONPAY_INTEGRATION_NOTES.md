# MevonPay Integration Notes (Internal)

## Purpose

This file is internal implementation reference for this codebase.
It is separate from public/provider API documentation.

---

## Core Integration Points

- `app/Services/MevonPayVirtualAccountService.php`
  - temp VA: `/V1/createtempva.php`
  - dynamic VA: `/V1/createdynamic`
- `app/Services/MevonRubiesVirtualAccountService.php`
  - Rubies personal create: `/V1/createrubies`
  - Rubies business create is available via test endpoint direct request
- `app/Http/Controllers/Api/MevonPayWebhookController.php`
  - handles `funding.success`
  - matches by `account_number`
- `app/Services/MevonPayBankService.php`
  - bank list + name enquiry via `/V1/bank_service`
- `app/Services/MavonPayTransferService.php`
  - payouts via `/V1/createtransfer`

---

## Current WhatsApp Tier 2 Status

- WhatsApp Tier 2 uses **Rubies personal** account creation.
- Business Rubies account onboarding is **not yet implemented** in WhatsApp flow.

Relevant flow:

- `app/Services/Whatsapp/WhatsappWalletUpgradeFlowHandler.php`

---

## Test Endpoint in This Project

Route:

- `POST /api/v1/test-meveon`
- `GET /api/v1/test-meveon`

Controller:

- `app/Http/Controllers/Api/TestMeveonController.php`

Utility actions currently supported:

- Rubies personal
- Rubies business
- Electricity (`getInfo`, `verify`, `buy`)
- Cable (`getInfo`, `verify`, `buy`)
- Bank list / name enquiry
- temp/dynamic VA
- transfer (guarded)

---

## Security / Config

Main env/config keys:

- `MEVONPAY_BASE_URL`
- `MEVONPAY_SECRET_KEY`
- `MEVONPAY_WEBHOOK_SECRET`
- `MEVONPAY_TIMEOUT_SECONDS`
- `MEVONPAY_CONNECT_TIMEOUT_SECONDS`
- `MEVONPAY_TEMP_VA_REGISTRATION_NUMBER`
- `MEVONPAY_TEST_SECRET`
- `MEVONPAY_TEST_ALLOW_TRANSFER`

Rubies:

- `MEVONRUBIES_BASE_URL` (optional fallback to MevonPay base URL)
- `MEVONRUBIES_SECRET_KEY` (optional fallback to MevonPay key)
- `MEVONRUBIES_CREATE_PATH`

---

## Known Behavior

- Provider may return mixed key casing (`account_number` and `accountNumber`).
- Name enquiry has timeout/empty-reply fallback handling in service.
- Transfer normalization maps response codes into success/pending/failed buckets.
- Webhook handler supports allowlist/secret checks when configured.
- Authorization header strategy is globally aligned to transfer service behavior:
  send `Authorization` exactly as configured in `MEVONPAY_SECRET_KEY` (no automatic Bearer prefixing).
