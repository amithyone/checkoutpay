# CheckoutPay Marketing Brief

**Commercial marketing foundation** for CheckoutPay + CheckoutNow.  
Use this as the source of truth for brand, product claims, campaigns, and creative.

---

## Brand Overview

| Brand | Role | Primary URL | Audience |
|-------|------|-------------|----------|
| **CheckoutPay** | Merchant payment infrastructure | https://check-outpay.com | Businesses, developers, WooCommerce stores |
| **CheckoutNow** | Consumer wallet (app + WhatsApp) | https://app.check-outnow.com | Everyday Nigerians |
| **COPN** | WooCommerce product line | WordPress plugin (COPN Payment Gateway) | Nigerian online stores |
| **METRAVON INNOVATION LTD** | Operating / legal entity | — | Trust / “Powered by” |

**CheckoutPay tagline:** Payments, simply.  
**CheckoutNow line:** Your wallet, secured. / Affordable, reliable everyday payments in Nigeria.

**One-line commercial story:**  
CheckoutPay lets Nigerian businesses accept money. CheckoutNow lets Nigerians move, save, and spend that money — including paying merchants via WhatsApp Pay Code.

**Social:** @CheckoutPayNG

**Mission (brand):** We are not marketing. We are just not talking about our achievements.  
**Mission (commercial):** Make CheckoutPay and CheckoutNow clear, credible, and easy to choose — without hype.

---

## Brand Architecture

```
Merchants                         Consumers
─────────                         ─────────
CheckoutPay Gateway               CheckoutNow App
COPN (WooCommerce)                WhatsApp Wallet
API + Hosted Checkout             USD Virtual Card
        │                                  │
        └──── Virtual accounts / Pay Code ─┘
              (same platform, one ledger)
```

- Merchants accept NGN via virtual accounts, hosted checkout, API, or COPN.
- Customers can pay with bank transfer **or** WhatsApp Pay Code from the same wallet used in CheckoutNow.
- Consumer → merchant path: “Get CheckoutPay business account” from the app.

---

## Core Brand Philosophy

**"We are not doing anything different. We are just part of the market that doesn't exist."**

CheckoutPay exists like roads exist. People don't advertise roads. They just use them.

### The Official Stance

**We are not marketing. We are just not talking about our achievements.**

Meaning:
- We exist
- We function
- We don't announce for applause
- If it works, that's enough

### Brand Archetype

**"We let you leave without explanation."**

No follow-ups. No speeches. No clarifications. Just absence.

**Internal brand translation:**  
> CheckoutPay does not seek validation. If you understand, fine. If you don't, enjoy your weekend.

### Brand Positioning

CheckoutPay is **infrastructure with taste** — a payment stack that blends into modern Nigerian business and everyday life.

**We are not convincing people to use CheckoutPay. We are letting CheckoutPay exist — and making the product facts easy to find when someone is ready.**

---

## Voice: Brand vs Commercial

### Brand voice (lifestyle, social, brand films)

Observational, calm, unimpressed. Short. Almost boring.

**Allowed:** “We're here.” “It works.” “Processed.” “Operational.” “For Nigerian businesses.”  
**Discouraged:** “Grow faster.” “Unlock.” “Trusted by thousands.” “Leading.” “Award-winning.”

### Commercial voice (ads, landing CTAs, sales, store listings)

Clearer and more direct is allowed. Still factual. Still calm.

**Allowed:** “Accept payments.” “1% + ₦50.” “Download CheckoutNow.” “Message WALLET.” “Get your dollar card.”  
**Still forbidden:** “Cheapest in Nigeria.” Unverified volume/trust stats. Competitor-bashing. Fake urgency.

**Rule:** Commercial can invite action. Brand should not beg for attention.

---

## Commercial Pillars (What We Sell)

### Pillar 1 — Accept payments (CheckoutPay)

- Bank-transfer-first checkout with virtual accounts + automated matching
- Hosted checkout (`/pay`) and REST API + webhooks
- WooCommerce via **COPN**
- Invoices, event tickets, memberships, rentals, collections, payouts
- **Price claim (accurate):** **1% + ₦50** per successful transaction; no setup/monthly on standard pay-as-you-go

### Pillar 2 — Everyday wallet (CheckoutNow + WhatsApp)

- Same wallet across app, web, and WhatsApp (`WALLET`)
- Send / receive, bank transfer, Ask for money, Save Together
- Bills: airtime, data, electricity, cable, betting
- Tier 1 (phone + PIN) → Tier 2 KYC (permanent VA, higher limits, dollar card)

### Pillar 3 — Dollar virtual card

- USD card funded from NGN wallet
- Use cases: Netflix, Spotify, ads, SaaS
- Setup: **$7.50 USD** total (creation + initial load) — always state clearly
- Tier 2 KYC required

### Pillar 4 — Grow into business

- Consumer → CheckoutPay merchant onboarding from the app
- Business wallet / receive accounts
- CAC business name registration — **only market when the feature flag is live**

---

## Audiences & Jobs-to-be-Done

| Audience | Job | Lead product | Primary CTA |
|----------|-----|--------------|-------------|
| Online stores / SMBs | Accept NGN bank transfers clearly and affordably | CheckoutPay + COPN | Create business account / Install plugin |
| Developers / agencies | Integrate once; optional revenue share | API + Developer Program | Read docs / Join program |
| Everyday consumers | Send money, pay bills, save | CheckoutNow / WhatsApp | Download app / Message WALLET |
| Global spenders | Pay international subscriptions from NGN | Dollar card | Get card (after Tier 2) |
| Freelancers / families | P2P and ask-for-money | WhatsApp + app | Send / Ask for money |
| Event / rental / membership operators | Sell and collect in one stack | CheckoutPay products | Open products / Get started |

### Primary (merchant)

- E-commerce, SaaS, services, freelancers, event organizers, digital sellers

### Primary (consumer)

- WhatsApp-first Nigerians, families, freelancers, bill payers, diaspora-facing spenders

---

## Positioning Statements

**Master:**  
Nigeria’s payment stack for businesses and people — bank-transfer checkout for merchants, WhatsApp-native wallet for customers.

**Merchant:**  
Accept payments with virtual accounts and WhatsApp Pay Codes. Transparent pricing: 1% + ₦50.

**Consumer:**  
One wallet for transfers, bills, savings, and a dollar card — on WhatsApp and in the CheckoutNow app.

**Differentiator (truthful):**  
Bank-transfer-first + WhatsApp wallet on the same platform. Not “another card-only gateway.”

---

## Message House

**Problem:** Nigerian businesses need reliable NGN acceptance. People need a simple way to pay, send, save, and spend online.

**Promise:** CheckoutPay moves money for business. CheckoutNow moves money for life.

**Proof points (product-backed):**
- Virtual accounts + automated reconciliation
- WhatsApp Pay Code at checkout
- Unified wallet (app ↔ WhatsApp)
- VTU / bills in-app
- USD virtual card from NGN
- WooCommerce plugin (COPN)
- Transparent fee: 1% + ₦50

**Avoid unless verified:** “cheapest in Nigeria”, “trusted by X businesses”, awards, unverified volume stats.

---

## Pricing Cheat Sheet (Creatives & Sales)

| Item | Claim |
|------|--------|
| Merchant gateway | **1% + ₦50** per successful transaction |
| Setup / monthly (standard) | None on pay-as-you-go |
| Example ₦10,000 | ₦150 fee |
| Example ₦100,000 | ₦1,050 fee |
| P2P to others | Free (per product config) |
| Self-bank transfer (own account) | **1.5%**, capped ₦500 |
| Virtual card setup | **$7.50 USD** from NGN at live sell rate |
| Tier 1 defaults | ₦50,000 max balance & daily send |

On quiet brand pages, you may say “Competitive rates. Clear charges. No surprises.”  
On commercial pages and ads, state **1% + ₦50** when pricing is the point.

---

## Channels & CTAs

| Channel | Entry | Goal |
|---------|-------|------|
| Website | check-outpay.com (home, pricing, WhatsApp wallet, products) | Merchant acquisition |
| App | app.check-outnow.com + APK / app stores | Consumer acquisition |
| WhatsApp | Bot + wa.me / message `WALLET` | Wallet activation |
| WordPress.org | COPN plugin | Store acquisition |
| Social | @CheckoutPayNG | Awareness (brand voice) |
| Cross-sell | In-app “Get CheckoutPay business account” | Consumer → merchant |
| Business portal | check-outnow.com / dashboard | Merchant ops |

---

## Always-On Campaigns

### 1. Merchant — “Accept NGN”

- Creative: virtual account / checkout moment (subtle)
- CTA: Start accepting payments / Create account
- Landing: `/pricing` or business register
- Proof: 1% + ₦50, virtual accounts, COPN

### 2. Consumer — “Wallet in WhatsApp”

- Creative: chat-native send / pay bills
- CTA: Message WALLET / Download CheckoutNow
- Landing: `/whatsapp-wallet` or app URL
- Proof: same wallet on app + WhatsApp

### 3. Card — “Pay the world from NGN”

- Creative: dollar card for subscriptions
- CTA: Get your card (KYC required)
- Landing: virtual card section / app Dollar Card tab
- Proof: fund from NGN; state $7.50 setup

**Fourth (only when live):** “Start a business from your wallet” (CAC / merchant onboarding).

---

## Content Pillars

### A. The World Around the Brand (Lifestyle)

**Visuals:** Fancy cafés, co-working spaces, hotels, rooftops; laptops on clean tables; well-dressed people working quietly; receipts and checkout screens (subtle).

**Captions:**
- "Building quietly."
- "Business, uninterrupted."
- "Payments, where they belong."
- "For businesses that move smoothly."
- "No noise. Just flow."

No hard sell. Let curiosity work.

### B. Soft Education (How it works, not why)

Instructional, calm, minimal.

- "How to create a CheckoutPay account."
- "Connecting your business in minutes."
- "Accepting payments, step by step."
- "How to open CheckoutNow."
- "Message WALLET to start."

Avoid dream-selling: “Grow faster”, “Increase revenue”, “Boost sales”.

### C. Quiet Credibility

- "Built for Nigerian businesses."
- "NGN-first payments."
- "Competitive rates. No surprises." / "1% + ₦50."
- "WhatsApp wallet. Same account."
- "Designed to stay out of the way."

No competitor screenshots. No brag reels.

### Caption Language (Locked for brand)

Short, almost boring:

- "Accepting payments."
- "Business as usual."
- "Part of the process."
- "Payments happen here."
- "Nothing special. Just working."
- "For Nigerian businesses."
- "Processed." / "Operational." / "It works."
- "CheckoutPay." / "CheckoutNow."
- "Alright." / "Noted." / "Accepted." / "Done."
- "Enjoy your weekend."

If it feels underwhelming → you're doing it right.

---

## Competitive Advantages (Factual)

1. **Transparent pricing** — 1% + ₦50 per successful transaction  
2. **Bank-transfer-first** — virtual accounts + intelligent matching  
3. **WhatsApp Pay Code** — customers pay from wallet at checkout  
4. **Unified consumer wallet** — app + WhatsApp + bills + savings + dollar card  
5. **Commerce stack** — payments, invoices, tickets, memberships, rentals  
6. **COPN** — WooCommerce path for Nigerian stores  
7. **Developer program** — integration + optional revenue share  

Do **not** claim “lowest fees in the market” unless independently verified.

---

## Visual Identity Guidelines

### Design Mood

- Minimal
- Quiet luxury
- Modern Nigerian business
- Fashion / lifestyle, not loud fintech

### Visual Style

- Clean compositions
- Neutral or muted tones (black, white, beige, soft grey, deep green, warm browns)
- CheckoutNow product UI may use dark ocean / blue accents (app brand)
- Natural light preferred
- No clutter

### Imagery

**Use:** Cafés, hotels, co-working, offices; people with laptops/phones; subtle checkout moments; city details.

**Avoid:** Stock fintech illustrations; loud neon; charts/arrows as hero; “look at me” layouts.

### Text on Designs

One line max for brand posts:

- "Payments, simply."
- "Built for modern businesses."
- "Business, uninterrupted."
- "CheckoutPay."
- "CheckoutNow."

Commercial creatives may add a single clear CTA line.

### Design Rule

**Design like nothing needs attention.**  
If a design looks like it wants to be liked → reject it.

---

## Launch Without “Launching”

No “We are excited to announce…”

Instead:
1. Beautiful image + "We're live." / "CheckoutNow is available."
2. "Accepting payments starts here."
3. "Message WALLET." / "Setting up your business."

**What to post:** Lifestyle, setup instructions, process docs.  
**What not to post:** Long captions, 🚀🔥💰, loud numbers, “Fastest/Best/Cheapest”, achievement language (for brand).

### Social Media Bio Options

**Option 1:**
```
Payments, simply.
For modern Nigerian businesses.
↓ Get started
```

**Option 2:**
```
A Nigerian payment gateway.
Built for modern businesses.
↓ Register your business
```

**Option 3:**
```
Business infrastructure.
Payments, refined.
↓ Start here
```

**CheckoutNow (consumer):**
```
Your wallet, secured.
Transfers, bills, savings, dollar card.
↓ app.check-outnow.com
```

---

## Homepage Copy

### Hero

```
CheckoutPay

Payments for Nigerian businesses.

[ Get started ]

Optional subtext:
Accept payments. Move on.
```

Optional dual-brand line (commercial pages):  
`CheckoutNow — wallet for people who pay.`

### What It Is

```
A payment gateway.

Built to accept and process payments in Nigeria.

Nothing more.
```

### What You Can Do

```
Accept online payments
Receive NGN payments
Set up your business
Manage transactions

No promises. Just functions.
```

### How It Works

```
Create an account
Set up your business
Start accepting payments

It takes a few minutes.
```

### Pricing

Quiet brand page:
```
Competitive rates.
Clear charges.
No surprises.
```

Commercial pricing page: state **1% + ₦50** with examples.

### Who It's For

```
For Nigerian businesses.

Small or established.

If you need to accept payments, this works.
```

### Security

```
Transactions are protected.
Data is handled securely.

That's it.
```

### CTA

```
Create an account.

Or don't.
```

### Footer

```
CheckoutPay
Payments, simply.

Support | Documentation | Terms | Privacy

© CheckoutPay · Powered by METRAVON INNOVATION LTD
```

Homepage should feel like a government form that happens to look good.

---

## Minimalist Flyer Text Options

1. Accepting payments.  
2. Business, as usual.  
3. Part of the process.  
4. Payments happen here.  
5. Nothing special. Just working.  
6. For Nigerian businesses.  
7. Processed.  
8. Operational.  
9. It works.  
10. CheckoutPay.  
11. CheckoutNow.  
12. Message WALLET.  

Weekend: "Enjoy your weekend." / "We'll continue on Monday."  
Money: "Money should move quietly." / "Payments don't need attention."

Never stack text. White space is part of the message.

---

## Service Descriptions (Website / Content)

### Payment Gateway API
RESTful API for payment integration. Real-time webhooks. Transaction status tracking.

### Hosted Checkout Page
Redirect to hosted payment page. No coding required. Mobile-optimized.

### WordPress / WooCommerce (COPN)
Plugin for Nigerian WooCommerce stores. Install and configure. Automatic charge calculation.

### Invoices
Create invoices with payment links. PDF export. Email sending.

### Rentals
Equipment, vehicles, properties. Availability, bookings, KYC.

### Memberships
Subscription memberships with digital cards and QR verification.

### Event Tickets
Sell tickets. QR verification. Digital PDF delivery.

### Payout
Withdraw to bank. Merchant settlement from dashboard balance.

### WhatsApp Wallet / CheckoutNow
Consumer wallet: P2P, bank transfer, bills, savings, Ask for money, Save Together. Same account on WhatsApp and app.

### Dollar Virtual Card
USD card funded from NGN. Freeze/unfreeze. Tier 2 KYC. State $7.50 setup.

### Collections
Track balances and collection history for merchants.

---

## Success Metrics

### Merchant KPIs
- New business signups
- Active API / plugin integrations
- Transaction volume
- Monthly active businesses
- Fee revenue

### Consumer KPIs
- App / WhatsApp wallet activations
- Tier 2 upgrades
- VTU / transfer volume
- Virtual card requests
- Consumer → merchant conversions

### Marketing Metrics
- Website traffic and signup conversion
- WhatsApp wallet landing → activation
- Plugin installs
- Social engagement (brand)
- Paid campaign CPA / CAC by pillar

---

## Brand Guidelines Summary

### Do's
- Emphasize simplicity and calm presence
- State **1% + ₦50** when pricing matters
- Show process, not dreams
- Keep lifestyle visuals quiet and premium
- Separate brand posts from commercial CTAs cleanly
- Keep CheckoutPay and CheckoutNow names distinct

### Don'ts
- “Lowest / cheapest / best / leading”
- Unverified trust or volume claims
- Aggressive sales tone on brand channels
- Overpromise gated features (CAC, etc.)
- Confuse CheckoutPay (merchant) with CheckoutNow (consumer)
- Compromise security messaging

---

## Approval Filters

**"If the content feels like it's trying, it's wrong."** (brand)

Before approving brand content:
1. Are we explaining ourselves too hard? → cut  
2. Are we talking about ourselves for applause? → cut  
3. Exist vs impress? → exist only  

Commercial ads may try a little — but only with true product facts.

---

## Internal Brand Sentences

> "CheckoutPay is not launching. It is already part of the environment."

> "CheckoutPay exists like roads exist. People don't advertise roads. They just use them."

> "CheckoutPay moves money for business. CheckoutNow moves money for life."

---

## For Graphics Designer

Objective: present, premium, calm.  
Design like nothing needs attention. No visual shouting.  
If it wants to be liked → reject it.

## For Ads / Content Production

Shoot like documenting normal life. Payments in the background.  
Slow shots, natural movement, ambient sound.  
No aggressive zooms, fast cuts, or selling voiceovers on brand films.  
Commercial spots may show product UI clearly and end on one CTA.

## One Public Line (If Ever Needed)

> "We're operational."

Or: "Nothing new. Just necessary."

---

## Next Steps (Marketing Ops)

1. Keep this brief as the single source of truth  
2. Partner one-pager: [marketing/PARTNER_ONE_PAGER.md](marketing/PARTNER_ONE_PAGER.md) · print [PARTNER_ONE_PAGER.html](marketing/PARTNER_ONE_PAGER.html) to PDF  
3. Ad copy pack: [marketing/AD_COPY_PACK.md](marketing/AD_COPY_PACK.md)  
4. Landing SEO/hero aligned with this brief (`config/seo.php`, `config/seo_pages.php`, hero + pricing views)  
5. CheckoutNow store listing: see `checkoutnow/docs/STORE_LISTING.md`  

---

*Last updated: July 2026 — commercial foundation merge (CheckoutPay + CheckoutNow).*
