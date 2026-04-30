# Checkout Payment Gateway Exhaustive Summary

## Big Picture

The system is a bank-transfer-first checkout gateway with two completion rails:

- External rail: Virtual accounts created through provider APIs (primarily MevonPay, plus MevonRubies for rental-specific flows), then confirmed by provider webhook.
- Internal rail: Transfers matched from inbound bank-alert emails and approved asynchronously.

Both rails converge into a single event flow:

- Payment approved -> merchant balance credited -> `PaymentApproved` event -> merchant webhook notification + domain listeners (invoice/rental/membership/tickets).

## Core Entry Points

- API payment creation: `POST /api/v1/payment-request` in `routes/api.php` -> `Api\PaymentController@store`
- Hosted checkout submit: `POST /pay` in `routes/web.php` -> `CheckoutController@store`
- Provider funding webhook: `/api/v1/webhook/mevonpay` (+ aliases) -> `Api\MevonPayWebhookController@receive`
- Email ingest webhook: `/api/v1/email/webhook` -> `Api\EmailWebhookController@receive`

## Orchestration and Data Model

- `app/Services/PaymentService.php` is the main orchestrator (`createPayment`):
  - Validates/guards webhook URL against approved business website setup.
  - Chooses external vs internal account strategy based on business provider mode.
  - Creates pending payment with source metadata.
- `app/Models/Payment.php` defines:
  - Lifecycle states (`pending`, `approved`, etc.).
  - Source tagging (`internal`, `external_mevonpay` and aliases).
  - `approve()` / `reject()` settlement state mutation helpers.
- `app/Models/Business.php` holds provider mode logic:
  - `external_only`, `hybrid`, `internal_only`
  - VA mode behavior (`dynamic` vs `temp`)

## External Gateway Path (MevonPay / MevonRubies)

### MevonPay Integration

- Config in `config/services.php` (`mevonpay.*` keys for base URL, secret keys, webhook secret)
- Client in `app/Services/MevonPayVirtualAccountService.php`
  - `createDynamicVa`
  - `createTempVa`
- Webhook handler in `app/Http/Controllers/Api/MevonPayWebhookController.php`
  - Validates sender allowlist and bearer secret.
  - Accepts `funding.success`.
  - Locates pending payment by account number.
  - Approves payment and credits merchant.

### MevonRubies Integration (Rental Flows)

- Config in `config/services.php` (`mevonrubies.*`)
- Client in `app/Services/MevonRubiesVirtualAccountService.php`
- Used by rental payment service in `app/Services/RentalPaymentService.php`

## Internal Matching Path (Email-Driven)

- Inbound parsing in `app/Http/Controllers/Api/EmailWebhookController.php`
  - Supports Zapier payload and raw email variants.
  - Secret + sender whitelist checks.
- Matching engine in `app/Services/PaymentMatchingService.php`
  - `matchEmail`, `matchPayment`, reverse search helpers.
  - Matches on amount/name/time/account constraints; tracks mismatch reasons.
- Async approval workers:
  - `app/Jobs/CheckPaymentEmails.php`
  - `app/Jobs/ProcessEmailPayment.php` (with retry/backoff)
- On successful match:
  - Payment approved
  - Merchant credited
  - `PaymentApproved` emitted

## WhatsApp Transfer Feature (Major Product Capability)

This is a first-class payment capability, not just an add-on. It enables merchants to trigger wallet-based payments and customer messaging through WhatsApp-powered flows.

### Merchant API Surface (Authenticated)

Defined in `routes/api.php` under API-key auth and throttling:

- `POST /api/v1/whatsapp-wallet/lookup` -> wallet summary lookup
- `POST /api/v1/whatsapp-wallet/ensure` -> ensure wallet exists for a phone number
- `POST /api/v1/whatsapp-wallet/send-message` -> merchant-composed WhatsApp message dispatch
- `POST /api/v1/whatsapp-wallet/topup/virtual-account` -> issue wallet top-up VA (tier-aware)
- `POST /api/v1/whatsapp-wallet/pay/start` -> start partner pay intent

Core controller:

- `app/Http/Controllers/Api/WhatsappWalletApiController.php`

Feature gating:

- Merchant must have `whatsapp_wallet_api_enabled` enabled on their business account.

### Partner Pay Flow (WhatsApp Transfer + PIN Confirmation)

Core service:

- `app/Services/Whatsapp/WhatsappWalletPartnerPayIntentService.php`

Flow:

1. Merchant calls `pay/start` with amount, order reference/summary, payer name, webhook URL, and idempotency key.
2. System validates business webhook URL policy and creates/reuses a pay intent.
3. Customer receives a WhatsApp message with a secure confirmation link (`/wallet/partner-pay/{token}`).
4. Customer enters wallet PIN on the hosted confirmation page.
5. System verifies PIN and settles wallet debit -> merchant credit.
6. Approved payment is persisted and normal `PaymentApproved` event/webhook fanout runs.
7. Customer receives a WhatsApp success receipt with updated wallet balance.

### Transfer Settlement, Idempotency, and Reliability

Settlement service:

- `app/Services/Whatsapp/WhatsappWalletPartnerApiService.php`

Key controls:

- Strong idempotency at settlement level (`idempotency_key`, duplicate replay and in-flight duplicate protection).
- Wallet balance lock/update in transaction with daily transfer tracking.
- Merchant settlement through standard `Payment` creation (`SOURCE_PARTNER_WALLET_API`) and balance credit.
- PIN brute-force controls (failed-attempt counter and temporary lockout).
- On success, system emits `PaymentApproved`, so existing merchant webhook delivery pipeline is reused.

### Top-Up Transfer Support (Virtual Accounts)

The WhatsApp wallet can also receive funds via bank transfer:

- `issueTopupVirtualAccount` in `WhatsappWalletApiController`
- Tier behavior:
  - Tier 1: fresh temporary VA issuance
  - Tier 2+: dedicated permanent account reuse

This allows continuous wallet funding and smoother future transfer conversions.

### Why It Matters for Marketing

- Adds a conversational payment journey: merchant initiates, customer confirms via WhatsApp.
- Reduces checkout friction for repeat users with wallet balances.
- Supports trust-building UX via in-chat confirmations and payment receipts.
- Expands coverage beyond standard transfer checkout into messaging-led commerce.

## Post-Approval Fanout (Common for Both Rails)

- Event: `app/Events/PaymentApproved.php`
- Listener: `app/Listeners/SendPaymentWebhook.php`
- Sender job: `app/Jobs/SendWebhookNotification.php`
  - Builds `payment.approved` payload.
  - Resolves destination URL(s) (website-first/legacy fallback).
  - Persists webhook attempts/status/errors.

Also wired in `app/Providers/EventServiceProvider.php` for domain-specific listeners (invoice/rental/membership/tickets).

## Lifecycle (End-to-End)

1. Merchant/client creates payment.
2. `PaymentService` decides external/internal mode and assigns account/VA.
3. Payment remains `pending`.
4. Approval happens through one of:
   - MevonPay funding webhook, or
   - Email matching job pipeline, or
   - Partner wallet settlement path (WhatsApp partner API service).
5. `Payment::approve()` persists final state + metadata.
6. Merchant balance increment logic executes.
7. `PaymentApproved` event fires.
8. Outbound merchant webhook delivery runs (sync + queued fallback).

## Idempotency, Retries, and Recovery

Strongest idempotency controls are in partner-wallet services:

- `app/Services/Whatsapp/WhatsappWalletPartnerApiService.php` (`idempotency_key`)
- `app/Services/Whatsapp/WhatsappWalletPartnerPayIntentService.php` (`client_idempotency_key`)

Webhook/retry controls:

- `SendWebhookNotification` HTTP retry + persisted attempt/error state.
- Recovery/re-drive via:
  - `app/Services/PendingWebhookDispatchService.php`
  - `app/Console/Commands/SendPendingWebhooksCommand.php`
  - `app/Console/Commands/ResendPaymentWebhookCommand.php`
  - Admin resend endpoints in `app/Http/Controllers/Admin/PaymentController.php`
  - Cron controller `app/Http/Controllers/Cron/WebhookCronController.php`

Manual remediation exists for mismatches/ops workflows:

- `manualApprove`, `manualVerify`, `checkMatch`, `markAsExpired`, and amount correction paths.

## Security Posture (What Is in Place)

- Webhook auth/verification:
  - Shared secrets (`hash_equals`) for MevonPay, VTU, WhatsApp.
  - Sender/IP allowlist checks in multiple webhook controllers.
  - Dedicated HMAC middleware in `app/Http/Middleware/VerifyLiveSyncSignature.php` (timestamp + nonce replay protection).
- Input/domain protections:
  - Callback URL-domain checks before payment creation.
  - SQLi-focused payload middleware and DB query guard integration.
- Sensitive data handling:
  - Email account passwords encrypted at rest (`EmailAccount` model).
  - Payment email data sanitized before persistence in `Payment::sanitizeEmailData`.
  - Flow is transfer/account based (no obvious PAN/CVV card handling paths).

## Risks and Gaps Observed

- Logging in several controllers/jobs captures raw payloads and error bodies that may include sensitive payment/email details.
- Exception logging in some payment paths includes SQL + bindings (possible data exposure in logs).
- Test coverage appears limited mainly to amount correction (`tests/Feature/Api/PaymentAmountCorrectionTest.php`), with little visible automated coverage for webhook auth/signature and security/redaction behavior.

## Important Config and Schema Surface

- Provider/env config:
  - `config/services.php`
  - `config/payment.php`
  - `config/vtu.php`
- External provider assignment schema/modes:
  - `database/migrations/2026_03_24_230001_create_external_apis_tables.php`
  - `database/migrations/2026_03_24_231000_add_mode_and_services_to_business_external_api_table.php`
  - `database/migrations/2026_03_26_000001_add_va_generation_mode_to_business_external_api_table.php`
  
