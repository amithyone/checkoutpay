# WhatsApp Wallet — Namibia

**What we need · what we’re bringing · why it matters**

---

## The solution we’re bringing

We’re building a **WhatsApp-native wallet** for Namibia: users **chat with a bot**, **register once**, hold a balance in **NAD**, and move money **person-to-person on WhatsApp**—the same place they already coordinate daily life.

On top of that foundation, we design the product so **transfers can reach multiple countries**: when someone sends across a border, **conversion is visible** (“what you pay” / “what they receive” / approximate rate)—so international peer-to-peer stays **honest and predictable**, not a black box.

**Local depth + regional reach**: strong **Namibia** rails first; **cross-border** capability so money can flow **to and from other currencies** where we enable pairs and compliance allows.

---

## Benefits

| Benefit | What it means for users and partners |
|---------|--------------------------------------|
| **Familiar surface** | No separate app required for core flows—**WhatsApp** is already trusted. |
| **Peer-to-peer first** | Send money to **someone’s number**, like messaging them—it’s intuitive. |
| **NAD at home** | Balance and domestic flows in **Namibian dollars**, aligned with local banking. |
| **Multi-country when needed** | Users can send **beyond one country** with **clear FX**, not hidden conversion. |
| **Room to grow** | Same product spine can add **bank pay-in / pay-out** once local partners are live. |

---

## What we need to make it run in Namibia

### 1. Domestic payments partner (bank rails)

To **receive from bank channels** and **pay out to Namibian bank accounts** at scale, we need a **commercial agreement** with an **aggregator or bank** that provides APIs for:

- Listing **participating banks** (or institution identifiers).
- **Validating** beneficiary accounts before payout.
- **Outgoing transfers** (e.g. EFT or instant rails) in **NAD**.
- **Incoming money** matched to users—e.g. **virtual account**, **reference**, or **hosted collection**—so wallet **top-ups** reconcile reliably.

Usually this is **one primary integration**, not hand-built links to every bank separately.

**Work**: sandbox, certification, go-live, SLAs, reconciliation and settlement processes.

---

### 2. Regulation & compliance

Align with **Bank of Namibia** expectations and **AML/CFT** norms:

- **Wallet tiers** and limits (who can send how much).
- **KYC** depth for higher limits or bank-linked payouts.
- **Screening** and reporting as required.

**Work**: legal structuring (how the wallet sits relative to licensed institutions), partner compliance packs, product rules that match **Namibia**.

---

### 3. Product & operations

- **User journey**: registration, PIN and identity steps, transparent fees in chat.
- **Cross-border**: configured **exchange logic** so multi-country transfers show **clear amounts** on both sides.
- **Operations**: settlement cadence with the PSP, **float** or prefunding if the model requires it, **support** path for failed or disputed transfers.

---

## What “multi-country transfers” means here

- **Domestic**: NAD wallet ↔ NAD wallet, and eventually NAD ↔ **local bank accounts**.
- **International**: when the **recipient’s wallet or bank** is in **another currency**, the product applies **conversion** with **visible terms**—so one platform can serve **Namibia deeply** and still **connect outward** where allowed and priced.

---

## Pitch lines

- *Money where you already chat—starting with Namibia in NAD.*
- *Send to someone on WhatsApp, or outward across borders with the rate in the message.*
- *One wallet spine: local rails when they’re ready, multi-country when you need them.*

---

## Next decisions

1. **Shortlist** Namibian PSPs/banks with **documented business APIs** for payout and collection.
2. **Legal model** for the wallet (product + licensing path).
3. **Phasing**: wallet + **P2P + cross-border** first; **full bank pay-in/out** as partner milestones allow.
