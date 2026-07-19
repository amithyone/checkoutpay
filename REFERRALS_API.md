# Wallet referrals API (CheckoutNow)

Base prefix: `/api/v1/consumer` (or your deployed API prefix). Authenticated routes use Sanctum Bearer token unless noted.

## Rules (public)

`GET referrals/rules`

No auth. Returns live programme knobs for UI copy (never hardcode amounts in the app).

```json
{
  "success": true,
  "data": {
    "enabled": true,
    "bonus_months": 6,
    "first_deposit_percent": 5,
    "first_deposit_max_ngn": null,
    "first_deposit_min_ngn": 0,
    "milestone_every": 100,
    "milestone_amount_ngn": 200,
    "milestone_currency": "NGN",
    "leaderboard_enabled": true,
    "leaderboard_top_n": 10,
    "terms_url": "https://check-outpay.com/terms-and-conditions#referral-programme",
    "faq_url": "https://check-outpay.com/faqs?category=whatsapp-wallet"
  }
}
```

When `enabled` is `false`, hide or disable referral UI (history endpoints may still return past data for signed-in users).

## Register with referral

`POST auth/register`

Optional field: `referral_code` — referrer **pay code** or **phone**. Registration attribution is supreme (never overwritten by later P2P).

## Me / stats

`GET referrals/me` (auth)

Returns pay code, phone, aggregate stats, whether this wallet was referred, and embedded `rules`.

## Invite share payload

`GET referrals/invite` (auth)

```json
{
  "pay_code": "AB12CD",
  "phone_e164": "23480…",
  "share_text": "…",
  "deep_link_hint": "https://…/?ref=AB12CD"
}
```

## Referred list

`GET referrals/list?per_page=20` (auth)

Items: masked phone, attribution source, dates, counted tx total, milestones paid, `status` = `active`|`expired`.

## Bonus history

`GET referrals/bonuses?per_page=20` (auth)

Types: `first_deposit`, `tx_milestone`, `leaderboard`.

## Leaderboard

`GET referrals/leaderboard` (auth)

Current calendar month (Africa/Lagos) standings + `me.rank` / `me.score`.

## Product rules (for UX copy)

1. **First bank top-up** by referred user → referrer gets `%` of that top-up (admin-configured).
2. **Every N counted txs** by referred user (continual) → fixed NGN milestone (admin).
3. Counted txs: VTU, P2P send, bank transfer out, partner merchant pay.
4. Window: admin months from attribution.
5. If no code at signup: first successful P2P credit to the new wallet claims the sender as referrer.
6. **All referral bonuses credit flexible savings** (not spendable wallet balance). Push payload includes `credited_to: "flexible_savings"`.
