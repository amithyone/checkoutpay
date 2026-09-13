# Xtrapay — Backend contract & mock inventory

This document maps **everything the React app currently mocks** to what the backend must provide. Use it as the build checklist for APIs, models, and auth.

**Frontend stack:** Vite + React · state hub `src/context/TransactionContext.tsx` · types `src/types.ts`  
**Mock sources:** `src/data/*.ts` + screen-local seed state  
**Currency:** NGN (₦) unless noted (USD card amounts)  
**Timezone:** Africa/Lagos (WAT)

> Goal: replace every hard-coded seed and client-only mutation with authenticated API responses. The UI should become a consumer of these contracts.

---

## 1. Principles

| Rule | Detail |
|------|--------|
| Auth | All user/money endpoints require Bearer token (except public auth + legal). |
| Money | Amounts as **integer kobo** on the wire (`5000000` = ₦50,000.00) *or* decimal NGN with 2 places — pick one and stick to it. Frontend today uses `number` NGN. |
| IDs | Opaque string IDs (`tx-…`, `trm-…`, UUID ok). |
| Errors | `{ code, message, fields? }` + HTTP status. |
| Idempotency | Transfers, bills, loan disburse, collections: `Idempotency-Key` header. |
| PIN | Never send raw PIN logs. Prefer PIN challenge token / hashed verify endpoint. Mock today accepts any 4 digits. |
| Pagination | List endpoints: `?cursor=&limit=` or `page&pageSize`. |

### Suggested base URL

```
https://api.xtrapay.ng/v1
```

---

## 2. Mock file inventory (current frontend)

| Location | What it seeds |
|----------|----------------|
| `src/data/initialData.ts` | Transactions, beneficiaries, banks, billers, money requests, group pots, nearby peers, shop tills |
| `src/data/wallets.ts` | Personal / business / sub wallets |
| `src/data/terminals.ts` | POS terminals + terminal txs |
| `src/data/appSearch.ts` | Client-only feature catalogue (can stay client or become CMS) |
| `TransactionContext` | Balances, limits, auth flags, cards freeze, savings, overdraft, transfer flow |
| `LoansScreen` | Active loans + collection ledger (local) |
| `LimitsScreen` | Daily / single / transfer / POS caps (local until PIN save) |
| `SupportScreen` | Tickets (local) |
| `NetworkScreen` | Rails health blended with txs (should become real heartbeats) |
| `RecurringPaymentsScreen` | Recurring plans + run history (local) |
| `ProfileScreen` | Profile / KYC display fields (local) |
| `DollarCardScreen` | Card UI state (freeze uses context) |
| Auth screens | Login/register/KYC/OTP/reset (localStorage flags only) |
| `LegalScreen` / `CheckoutNowScreen` | Static copy (CMS or keep static) |

---

## 3. Domain models (API shapes)

Align responses with frontend types in `src/types.ts` and `src/data/wallets.ts`.

### 3.1 User & profile

```ts
UserProfile {
  id: string
  fullName: string
  email: string
  phone: string            // E.164 preferred: +2348034129981
  address: string
  city: string
  state: string            // NG state name
  dateOfBirth: string      // ISO date
  gender: 'male' | 'female'
  tier: 'Tier 1' | 'Tier 2' | 'Tier 3' | string
  kyc: {
    bvnMasked?: string
    ninMasked?: string
    idType?: 'bvn' | 'nin'
    status: 'none' | 'pending' | 'verified' | 'rejected'
  }
  agent?: {
    code: string           // e.g. AG-1003925
    aggregator?: string
    active: boolean
  }
  preferences: {
    theme?: 'dark' | 'light'
    balanceHidden?: boolean
    biometrics?: boolean
    faceId?: boolean
    push?: boolean
    sms?: boolean
    emailNotif?: boolean
    txnAlerts?: boolean
    promo?: boolean
    loginAlerts?: boolean
    sessionTimeoutMin?: number
  }
}
```

### 3.2 Wallet

```ts
WalletAccount {
  id: string
  name: string
  kind: 'personal' | 'business' | 'sub_personal' | 'sub_business'
  accountNumber: string
  bankName: string
  balance: number          // NGN
  subtitle: string
  currency: 'NGN'
}
```

**Current mock wallets**

| id | name | kind | account | bank | balance |
|----|------|------|---------|------|---------|
| personal | Personal account | personal | 0124892019 | Zenith Bank | 4,850,240 |
| business | Business account | business | 2048991204 | Providus Bank | 14,250,000 |
| sub-1 | Rent wallet | sub_personal | 0124892201 | Zenith Bank | 125,000 |
| sub-2 | Market stall | sub_business | 2048991308 | Providus Bank | 482,450.5 |

### 3.3 Transaction

```ts
Transaction {
  id: string
  title: string
  subtitle: string
  date: string             // display or prefer ISO `occurredAt`
  timestamp: string
  fullTime?: string
  occurredAt?: string      // ISO — preferred for backend
  amount: number
  type: 'debit' | 'credit'
  status: string           // Settled | Successful | Processing | Failed | …
  category: 'transfer' | 'utility' | 'bill' | 'savings' | 'card' | 'p2p'
  reference: string
  token?: string
  bank?: string
  recipient?: string
  cardLast4?: string
  cardUsdAmount?: string
  note?: string
  walletId?: string
}
```

### 3.4 Transfer (in flight)

```ts
ActiveProcessingTransfer {
  step: 1 | 2 | 3          // initiated → processing → settled
  amount: number
  recipientName: string
  bankName: string
  accountNumber: string
  reference: string
  narration?: string
  initTime / processedTime / settledTime: string
  isComplete: boolean
}
```

### 3.5 Banks, beneficiaries, billers

```ts
Bank { id, name, code }                    // NUBAN bank list
Beneficiary { id, name, initials, bank, accountNumber, tier, colorClass? }
BillerCategory { id, name, icon, subtitle, active? }
FrequentBiller { id, name, icon, subtitle, accountNumber, category, provider }
```

### 3.6 POS

```ts
Terminal { id, terminalId, serialNumber, name, model, address, status, balance, dateMapped, lastTransaction, pendingAddress?, addressRequestStatus? }
TerminalTx { id, terminalId, type, amount, status, reference, date, time, commission?, xPoints? }
status: Active | Inactive | Offline | Locked | Pending
```

### 3.7 Savings & group pots

```ts
SavingsSummary { flexibleBalance, strictBalance, strictAutoSave: boolean }
GroupPot { id, title, subtitle, targetAmount, raisedAmount, myStatus, myContribution, frequency, members[] }
PotMember { id, name, phone, initials, status, contributed, share, isCreator? }
```

### 3.8 Money requests & credit

```ts
MoneyRequest { id, type: contact|overdraft|loan, …, status, isIncoming, facilityKind?, tenor? }
Loan {
  id, title, principal, outstanding, dueDate, tenor, status: Active|Overdue|Settled
}
LoanCollection {
  id, customer, phone, amount, method: Cash|Transfer|POS, date, reference, agentId?
}
Overdraft { limit: number, used?: number }
```

### 3.9 Limits

```ts
Limits {
  dailySpendCap: number
  dailySpent: number
  singleTxnCap: number
  transferCap: number
  posFloatCap: number
}
```

### 3.10 Cards

```ts
Card {
  id, kind: 'physical' | 'virtual_usd'
  last4, panMasked?, status: active|frozen|pending
  spendAvailableNgn?, spendAvailableUsd?
  network: visa|mastercard|verve
}
```

### 3.11 Recurring

```ts
RecurringPlan {
  id, recipientName, bankName, accountNumber, amount, narration,
  scheduleLabel, nextRun, active, walletId?
}
RecurringRun { id, planId, recipientName, amount, status, date, time, reference }
```

### 3.12 Support & network

```ts
SupportTicket { id, subject, category, status: Open|Pending|Resolved, updatedAt, messages? }
NetworkRail {
  id, name, backend, status: Active|Degraded|Down,
  successRate, uptime, latencyMs, lastTrafficAt, volume24h
}
```

### 3.13 Proximity / shop (optional phase)

```ts
NearbyPeer { id, name, device, distance, walletTag, avatarColor }
ShopTerminal { id, name, terminalId, amount?, merchantCategory, rssi }
```

---

## 4. Auth API

Frontend flow today: Intro (client) → Login / Register(basic) → KYC → OTP → session.

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/auth/register` | Step 1 basic: `{ fullName, phone, email, password }` → `{ registrationId }` |
| POST | `/auth/kyc` | Step 2: `{ registrationId, idType, idNumber, dateOfBirth, gender, address, city, state }` |
| POST | `/auth/otp/send` | `{ destination, purpose: register\|login\|reset }` |
| POST | `/auth/otp/verify` | `{ destination, purpose, code }` → tokens on success |
| POST | `/auth/login` | `{ identifier, password }` → tokens **or** `{ requiresOtp: true }` |
| POST | `/auth/password/forgot` | `{ identifier }` |
| POST | `/auth/password/reset` | `{ identifier, otp, newPassword }` |
| POST | `/auth/logout` | Invalidate refresh |
| POST | `/auth/refresh` | Refresh access token |
| GET | `/auth/session` | Current user + wallets summary |
| DELETE | `/auth/account` | Delete account (confirm with PIN / password) |

**Tokens:** `{ accessToken, refreshToken, expiresIn }`  
**Client storage today:** `localStorage xtrapay_auth`, `xtrapay_intro` — replace with secure token storage.

**PIN (transaction):**

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/security/pin/verify` | `{ pin }` or challenge → `{ ok, challengeId? }` |
| POST | `/security/pin/set` | Change PIN (after verify) |
| POST | `/security/password/change` | Change login password |

---

## 5. Core money & wallet API

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/me` | Profile |
| PATCH | `/me` | Update name/email/phone/address/prefs |
| GET | `/wallets` | List wallets |
| POST | `/wallets` | Create sub-account |
| GET | `/wallets/:id` | Detail + balance |
| GET | `/wallets/:id/transactions` | Ledger (`category`, `from`, `to`, cursor) |
| GET | `/transactions` | Cross-wallet history |
| GET | `/transactions/:id` | Receipt payload |
| POST | `/transfers` | `{ walletId, accountNumber, bankCode, amount, narration, pinChallenge }` |
| GET | `/transfers/:id` | Status polling (steps 1–3) |
| POST | `/transfers/name-enquiry` | `{ accountNumber, bankCode }` → `{ accountName }` |
| GET | `/banks` | NUBAN list |
| GET | `/beneficiaries` | Saved recipients |
| POST | `/beneficiaries` | Save |
| DELETE | `/beneficiaries/:id` | Remove |

**Resend behaviour (UI):** does **not** call transfer again — opens form with prefill. Backend only needs GET of last transfer details if client doesn’t keep them.

---

## 6. Bills, airtime, utilities

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/billers/categories` | Categories |
| GET | `/billers?category=` | Providers |
| GET | `/billers/frequent` | User frequents |
| POST | `/bills/validate` | Meter/smartcard lookup |
| POST | `/bills/pay` | Pay → `{ reference, token? }` |
| GET | `/savings` | Flexible + strict balances |
| POST | `/savings/flexible/deposit` | Quick save |
| POST | `/savings/flexible/withdraw` | Withdraw |
| PATCH | `/savings/strict/autosave` | Toggle |

---

## 7. POS / terminals

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/terminals` | Mapped devices |
| GET | `/terminals/:id` | Detail |
| GET | `/terminals/:id/transactions` | POS txs |
| POST | `/terminals/:id/fund` | Push float |
| POST | `/terminals/sweep` | `{ terminalIds: 'all' \| string[], amount? }` + PIN |
| POST | `/terminals/:id/lock` | Lock |
| POST | `/terminals/:id/unlock` | Unlock |
| POST | `/terminals/:id/address-request` | Update address |

---

## 8. Loans, limits, network, support

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/credit/overview` | Outstanding, overdraft limit |
| GET | `/credit/loans` | Active loans |
| POST | `/credit/loans/request` | `{ amount, tenor }` + PIN → disburse |
| POST | `/credit/loans/:id/repay` | Repay own loan from wallet + PIN |
| GET | `/credit/collections` | *(optional agent)* collection log |
| POST | `/credit/collections` | *(optional agent)* record collection + PIN |
| GET | `/limits` | Caps + dailySpent |
| PUT | `/limits` | Update caps + PIN |
| GET | `/network/rails` | Runtime health |
| GET | `/support/tickets` | List |
| POST | `/support/tickets` | Create |
| GET | `/support/tickets/:id` | Thread |

---

## 9. Cards, recurring, statements, X-Points

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/cards` | Physical + virtual |
| POST | `/cards/request` | Request card |
| POST | `/cards/:id/freeze` | Freeze/unfreeze |
| POST | `/cards/:id/pin` | Set/change card PIN |
| GET | `/recurring` | Plans |
| POST | `/recurring` | Create |
| PATCH | `/recurring/:id` | Pause/resume |
| DELETE | `/recurring/:id` | Delete |
| GET | `/recurring/:id/runs` | History |
| POST | `/statements` | `{ kind, period, format: pdf\|csv, posId? }` → download URL |
| GET | `/xpoints` | Balance + history |
| POST | `/xpoints/redeem` | `{ channel, amount }` |

---

## 10. Peer request, group save, proximity (phase 2)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/money-requests` | Inbox/outbox |
| POST | `/money-requests` | Ask contact |
| POST | `/money-requests/:id/accept` | + PIN |
| POST | `/money-requests/:id/decline` | |
| POST | `/money-requests/:id/cancel` | |
| GET | `/pots` | Group savings |
| POST | `/pots` | Create |
| POST | `/pots/:id/contribute` | + PIN |
| POST | `/pots/:id/accept` | Invite |
| POST | `/pots/:id/decline` | |
| GET | `/proximity/peers` | Nearby (BLE/session) |
| POST | `/proximity/pay` | P2P nearby |
| GET | `/shops/nearby` | Till broadcast |
| POST | `/shops/pay` | Pay at shop |

---

## 11. Legal / partner (mostly static)

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/legal/terms` | Optional CMS |
| GET | `/legal/privacy` | Optional CMS |
| GET | `/partners/checkoutnow` | Partner blurb (or keep static in app) |

**Product attribution (UI copy):**  
Xtrapay by **Xtratech Global Solutions** · Powered by **CheckoutNow** (licensed engine; CheckoutNow does not control user funds/data).

---

## 12. Screen → API mapping

| Screen | Needs from backend |
|--------|-------------------|
| Auth (all) | Auth + OTP + KYC |
| Hub | `/wallets`, recent `/transactions`, selected wallet |
| Pay | Limits snippet, recent transfers |
| Transfer | Banks, name enquiry, beneficiaries, POST transfer, PIN verify |
| Receive | Wallet account/QR payload |
| History | Paginated transactions |
| Pay bills | Billers + pay |
| Saving | Savings summary + mutate |
| Save together | Pots |
| Ask money | Money requests + facilities |
| Terminals | Terminals + sweep/fund/lock |
| Cards | Cards CRUD-ish |
| X-Points | Balance + redeem |
| Statement | Generate export |
| Recurring | Plans + runs |
| Sub-accounts | Wallets filter / create |
| Loans | Loans + collections |
| Limits | GET/PUT limits |
| Network | Rails health |
| Support | Tickets |
| Profile | `/me`, delete account, prefs |
| Services search | Can stay client catalogue |

---

## 13. Current client-side “business rules” to preserve

These are implemented in the mock today — backend should own them:

1. **Transfer:** validate amount > 0, ≤ wallet balance, 10-digit NUBAN, bank selected → PIN → create transfer → async steps → ledger debit + receipt.  
2. **Resend:** UI prefill only (no auto debit).  
3. **Daily limit:** `dailySpent` vs `dailyLimit` (mock: spent 2,450,000 / cap 5,000,000).  
4. **Loan request:** min ₦5,000, tenor 14/30/60/90, ~2.5% flat interest display, credit personal wallet on approve.  
5. **Loan collection:** agent records Cash/Transfer/POS against customer.  
6. **Bill electricity:** return STS-style token groups.  
7. **Account delete:** confirm string `DELETE` in UI + authenticated DELETE.  
8. **KYC:** BVN or NIN exactly 11 digits.  
9. **OTP:** 6 digits (mock accepts any).  
10. **Network health:** blend provider heartbeats with recent txn success rates.

---

## 14. Suggested build order

1. **Auth + session + `/me` + wallets + transactions** (unlocks Hub)  
2. **Banks + name enquiry + transfers + PIN**  
3. **Beneficiaries + bills**  
4. **POS terminals**  
5. **Savings + limits**  
6. **Loans + collections**  
7. **Cards + recurring + statements**  
8. **Support + network rails**  
9. **Money requests + pots + proximity**

---

## 15. Example bootstrap payload

What a logged-in app boot could fetch in one round-trip (optional `GET /bootstrap`):

```json
{
  "user": { "fullName": "Innocent Solomon", "tier": "Tier 3", "phone": "+2348034129981" },
  "wallets": [ /* INITIAL_WALLETS shape */ ],
  "selectedWalletId": "personal",
  "limits": { "dailySpendCap": 5000000, "dailySpent": 2450000, "singleTxnCap": 1000000, "transferCap": 500000, "posFloatCap": 2000000 },
  "savings": { "flexibleBalance": 214558.04, "strictBalance": 2250, "strictAutoSave": true },
  "overdraftLimit": 150000,
  "recentTransactions": [ /* first page */ ],
  "cardFrozen": false,
  "xpointsBalance": 0
}
```

---

## 16. Frontend replacement checklist

When APIs exist:

- [ ] Remove `localStorage` fake auth; use real tokens  
- [ ] Replace `INITIAL_*` imports with React Query / fetch layer  
- [ ] Move mutations in `TransactionContext` to API clients; keep UI toasts  
- [ ] Replace screen-local seeds (loans, recurring, support, limits) with hooks  
- [ ] Drive `NetworkScreen` from `/network/rails`  
- [ ] Keep `appSearch.ts` client-side until a CMS is needed  

---

## 17. Source references

| Concern | Path |
|---------|------|
| Types | `src/types.ts` |
| Context / actions | `src/context/TransactionContext.tsx` |
| Seeds | `src/data/initialData.ts`, `wallets.ts`, `terminals.ts` |
| Auth UI | `src/components/auth/*` |
| Screens | `src/components/screens/*` |

---

*Generated for Xtrapay frontend mock → backend handoff. Update this file as contracts stabilize.*
