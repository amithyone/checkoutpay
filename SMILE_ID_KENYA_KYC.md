# Smile ID — Kenya Tier 2 KYC

CheckoutPay uses **Smile ID Basic KYC** for Kenyan wallet Tier 2 (National ID / IPRS).

See also: enter0 → Settings → Dollar Virtual Card → “Kenya Tier 2 KYC enabled”.

## Outside this repo (ops)

1. Sign up at [portal.usesmileid.com](https://portal.usesmileid.com/).
2. Enable **Kenya → National ID → Basic KYC** (sandbox first).
3. Copy **Partner ID** and **API Key for Signature** (sandbox vs production are different keys).
4. Set env on the app server (see below).
5. Confirm pricing for Basic KYC vs Biometric KYC. MVP uses **Basic** only.

## Env

```bash
SMILE_ID_PARTNER_ID=085
SMILE_ID_API_KEY=...
SMILE_ID_SANDBOX=true
# Optional admin toggle (also in enter0 Settings → Dollar Virtual Card):
# Setting kenya_tier2_enabled = 1
```

Or leave credentials empty and set `kenya_tier2_enabled` only after Smile is ready.

## Sandbox National ID numbers

Smile documents test IDs such as `00000000` (success with PII), `00000001` (not found), etc. Use those while `SMILE_ID_SANDBOX=true`.

## Flow

1. Kenya wallet submits personal Tier 2 with `national_id` + name + DOB + gender + email.
2. Backend calls Smile sync `POST /v2/verify` (`country=KE`, `id_type=NATIONAL_ID`).
3. Result codes `1020` (exact) or `1021` (partial, if accepted) → wallet `tier = 2`, `kyc_verified_at` set.
4. USD virtual card requires Tier 2 (same as Nigeria).
