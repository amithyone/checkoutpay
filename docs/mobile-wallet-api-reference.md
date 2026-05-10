# Mobile Wallet API Reference (Consumer + WhatsApp Merchant)

This document tracks what has been implemented so far for mobile wallet banking features, current endpoints, and practical possibilities for Android/iOS integration.

## 1) What Has Been Built

### Consumer API layer (mobile-first)
- New consumer route namespace: `/api/v1/consumer/*`
- Phone OTP auth + bearer token login flow
- Wallet bootstrap, summary, transaction history
- Top-up virtual account issuance:
  - Tier 1: temporary VA
  - Tier 2: permanent VA
- PIN setup/change and PIN-gated debit operations
- P2P transfer and bank transfer
- VTU (airtime + data)
- Tier-2 KYC (personal/business)

### Shared domain reuse (no service-folder fork)
- Consumer endpoints delegate to existing wallet domain services (WhatsApp + Mevon/Rubies + VTU paths)
- Existing merchant WhatsApp API remains available and unchanged

### Documentation and routing
- Consumer routes are registered in `routes/api.php`
- Business API docs include consumer API section in the blade docs page

---

## 2) Authentication Model (Consumer)

### OTP + token flow
1. `POST /api/v1/consumer/auth/otp/request` with phone
2. `POST /api/v1/consumer/auth/otp/verify` with phone + code
3. Receive bearer token
4. Use `Authorization: Bearer <token>` for protected consumer endpoints

### Session mapping
- Token is tied to a `ConsumerWalletApiAccount` record
- Account maps to `whatsapp_wallet_id` / `phone_e164`

### Sensitive actions
- Transfers and VTU purchases require PIN input

---

## 3) Endpoint List

## 3.1 Consumer Mobile API (`/api/v1/consumer`)

### Auth
- `POST /auth/otp/request`
- `POST /auth/otp/verify`
- `POST /auth/logout`

### Wallet Core
- `GET /wallet`
- `POST /wallet/ensure`
- `GET /wallet/transactions`
- `POST /wallet/topup/virtual-account`
- `POST /wallet/pin`
- `PUT /wallet/pin`
- `PATCH /profile/sender-name`

### Transfers
- `POST /transfers/p2p`
- `POST /transfers/bank`
- `GET /banks/name-enquiry`

### VTU
- `GET /vtu/networks`
- `GET /vtu/data-plans`
- `POST /vtu/airtime`
- `POST /vtu/data`

### KYC
- `GET /kyc/tier2`
- `POST /kyc/tier2/personal`
- `POST /kyc/tier2/business`

## 3.2 Merchant WhatsApp API (`/api/v1/whatsapp-wallet`)
- `POST /lookup`
- `POST /ensure`
- `POST /send-message`
- `POST /topup/virtual-account`
- `POST /pay/start`

---

## 4) Capability Matrix (What the App Can Do)

- Create/sign-in wallet user via phone OTP: **Yes**
- Create/ensure wallet record: **Yes**
- Get wallet balance/summary/tier: **Yes**
- List recent wallet transactions: **Yes**
- Get temporary top-up VA: **Yes (Tier 1)**
- Get permanent top-up VA: **Yes (Tier 2)**
- Set/change transaction PIN: **Yes**
- Send money wallet-to-wallet (P2P): **Yes (PIN-gated)**
- Send money to bank account: **Yes (PIN-gated)**
- Resolve bank account name before transfer: **Yes**
- Buy airtime/data (VTU): **Yes (PIN-gated)**
- Submit tier-2 KYC (personal/business): **Yes**
- Send outbound WhatsApp message through API: **Yes (merchant endpoint)**

---

## 5) Mobile App Integration Notes

- Recommended base URL pattern:
  - Consumer app: `/api/v1/consumer`
  - Merchant/automation channel: `/api/v1/whatsapp-wallet`
- Store bearer token securely (Android Keystore / iOS Keychain).
- Require PIN confirmation UI for debit-like flows:
  - P2P transfer
  - Bank transfer
  - VTU purchases
- For top-up UX:
  - Call `/wallet/topup/virtual-account`
  - Inspect `kind` field (`temporary` or `permanent`)
  - Show `expires_at` only for temporary VA

---

## 6) Suggested Mobile Product Possibilities

- Full wallet onboarding with OTP + progressive KYC
- Tiered wallet experience (Tier 1 to Tier 2)
- Consumer banking experience:
  - Funding via VA
  - P2P + bank payouts
  - Airtime/data
  - Transaction history + receipt timeline
- Unified communication:
  - Consumer app for banking operations
  - Merchant flows still reachable via WhatsApp endpoints

---

## 7) Recommended Next Steps

1. Freeze and review the exact file list to push (exclude accidental storage/runtime files).
2. Add automated API tests:
   - OTP request/verify
   - wallet read
   - one transfer path
   - one VTU path
3. Generate OpenAPI JSON/YAML from these routes for mobile team SDK generation.
4. Add API versioning conventions for future non-breaking expansion (`/api/v1/consumer/...` kept stable).

