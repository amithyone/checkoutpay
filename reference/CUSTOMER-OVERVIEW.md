# Amithy — What it does for you

Amithy helps organizations **distribute premium video on USB** while keeping **playback under your control**. Your audience runs a **dedicated Amithy player**—not a generic media app—so viewing stays inside the experience you intend.

**Students and viewers** get a simple “plug in, launch, watch” flow. **Content distributors and trainers** get one workflow to mint sticks, set policy, and hand off drives—without rebuilding their whole pipeline.

---

## What you can do

- **Ship courses, screeners, internal training, or event content** on a thumb drive instead of (or alongside) streaming links.
- **Choose how strict access should be**—from “only people with the stick and a shared secret” up to **per-machine access with dates you set**.
- **Support Windows and macOS** so the same content strategy works across the laptops your customers already use.
- **Prepare drives from one place** using the Amithy Admin workflow: pick a **root folder** (with optional **nested folders** for courses and subjects), pick the target drive, and produce a ready package—the **player includes search** and groups lessons by folder path.
- **Tune security layers per deployment** (full Admin and test Admin share the same USB builder): recommended protections ship **on by default**, and you can turn individual layers off only when you trust the room—for example accessibility, lab debugging, or a trusted internal screening.

---

## Your security layers (distributor controls)

When you build a USB, Amithy Admin exposes **clear on/off choices** for each protection family. **Defaults are “everything on”** so you do not accidentally ship a weak stick.

| Layer (concept) | What it means for viewers |
|-----------------|---------------------------|
| **Encrypted delivery** | Video leaves your machine protected; the stick does not carry ordinary double-clickable movie files. *(Always on for Amithy sticks.)* |
| **Per-device access** | Each laptop may need its own issued access before playback, with dates you set—or you can relax this for “one classroom stick” scenarios. |
| **Viewer passcode gate** | Optional shared secret before playback; when enabled and you set a password, viewers must enter it. Turn the gate off if you do not want that step at all. |
| **Single-display posture** | Blocks the common “second screen / HDMI split / grabber” topology; turn off only in controlled rooms where extra monitors are required. |
| **Capture resistance** | Asks the operating system to reduce casual screen recording where supported—best-effort, not magic. |
| **Strict time checks** | For dated access, uses stronger real-world clock discipline; relax for fully offline or air-gapped pilots if your policy allows. |
| **USB discretion** | Optionally hides sensitive folders on the stick so casual browsing of the drive is harder—cosmetic plus, not the core cryptography. |
| **Shelf life for the stick** | Optional **last playback day** (UTC calendar): after that, the player **removes encrypted video files** from the drive on launch so the USB does not keep stale premium content forever. |

You stay in charge: **tight ship for paying customers**, **lighter stack for trusted labs**—without maintaining two different products.

---

## Benefits (why teams pick this)

| Benefit | In plain language |
|--------|-------------------|
| **Controlled viewing** | Your masters are **not** having a yard sale on the stick as plain MP4s—there is **no “obvious uncompressed aisle”** for curious people to stroll down. |
| **Your rules** | You decide whether access is **shared** (e.g. one classroom stick) or **tied to specific machines** and **time windows**. |
| **Professional playback** | Full-screen, focused playback is designed for **training rooms, festivals, sales demos, and confidential previews**—not for casual “save as” habits. |
| **Offline-first** | Works where **internet is unreliable or not allowed**—air-gapped sites, ships, venues, or regions with poor connectivity. |
| **You stay the publisher** | You control **who gets a drive**, **when licenses end**, and **how tightly** each deployment is locked—without rebuilding your entire video pipeline. |

---

## Security & trust — multi-layer protection (still readable)

Amithy stacks **several independent gates** so bypassing one layer does not magically unlock the rest. We advertise the **ideas**, not the wiring—so honest buyers understand the value without a blueprint for clones.

### Layer 1 — **Strong encryption at rest**

Your premium footage is protected with **AES-256** class encryption before it ever leaves your Admin machine. On the stick, what viewers see is **not a normal video file** every app can open.

### Layer 2 — **Cryptographic integrity (SHA-256 family)**

Where the product needs **tamper-evident trust**—for example proving that a license or payload was **issued by you and not edited in Notepad**—it relies on **SHA-256**, the same family of hashing the world uses for **signing, fingerprints, and integrity** in banks, browsers, and government systems. It is not “mystery math”; it is the **same NIST-tracked workhorse** everyone serious already bets on.

Viewer secrets (when you use a playback passcode) are not stored in plain text: they are stretched with **modern, slow-by-design key derivation** so guessing attacks cost real time.

### Layer 3 — **Anti-tamper behavior at playback**

If the runtime detects **integrity failures**, **clock games**, or **invalid credentials**, the **cryptographic doors simply stay closed**—playback **refuses to proceed** in a clean, fail-closed way. We describe it as **anti-crack posture**: the experience **stops being useful** to a pirate the moment the environment stops looking honest, instead of “playing broken garbage forever.”

*(We do not document internal tamper branches, file names, or self-test sequences here.)*

### Layer 4 — **Capture-card & “second screen” resistance**

The player is built to **detect hostile viewing topologies**: extra monitors, hot-plugged screens, and the usual **HDMI out / capture-card** party tricks that training pirates love. When the environment crosses the line, the app **protects itself**—including **content-protection** hints to the OS so **casual screen grabbers** have a harder life. This is **anti-leak ergonomics**, not a physics impossibility claim. *(Distributors can turn single-display and capture-resistance layers off together or separately when policy allows—see “Your security layers” above.)*

### Layer 5 — **Honest ceiling**

**Anyone who can point a camera at the screen for the full runtime** can still get a recording—that is true of **every** playback system on Earth, including Netflix. Amithy’s job is to crush **silent copying of masters**, **naive redistribution**, and **lazy classroom leakage**—not to promise magic against a determined nation-state.

---

## What we still keep under wraps

Exact file trees, protocol grammar, round counts, and integration recipes stay **NDA / serious-buyer territory**. That protects your investment and slows “weekend rebuild” copycats.

---

## Good fit / less of a fit

**Good fit:** training vendors, film distributors doing controlled screeners, corporate L&D, live-event content, agencies delivering premium B2B video.

**Less of a fit:** public viral clips meant to be freely reshared, or Hollywood-level threat models requiring studio DRM (e.g. major streaming platform parity).

---

## Next steps

If this matches how you want to **ship and control video**, ask for a **demo build**, **pricing**, or an **NDA-backed technical session**—whatever stage you’re at.

---

## Factory duplication SOP (1500+ USBs)

For high-volume delivery, use a **hybrid** process:

1. Build one **golden USB** with Admin.
2. Clone that USB image to many drives (sector/image duplicator).
3. Run **personalization per cloned USB** to rotate:
   - `resources/amithy/license/install.key`
   - `resources/amithy/license/usb_instance.json` (`usbId`, `batchId`)
4. Issue per-PC `license.dat` bound to both:
   - the viewer machine (`device_id`)
   - that specific USB copy (`usb_id`)

This avoids binding all copies to one “master USB identity” while keeping strong anti-copy behavior.

### Important practical notes

- **Partitioning is optional**: not required for Amithy security.
- Copying only `.enc` files to a laptop does not make them playable; the player expects full USB runtime context (`.amithy-data`, `install.key`, `usb_instance.json`) and valid license policy.
- Per-PC license issuance remains the strongest mode for paid distributions.

---

## Silent telemetry + policy control (test operations)

For controlled testing handoff, Admin and Player can run with a silent operations channel:

- Events are queued locally first (offline-safe), then synced in background when internet is available.
- Queue delivery is automatic; tester action is not required.
- Policy can be returned by server to place the apps into a temporary service hold.
- User-facing copy remains generic (service/security wording only), without exposing control internals.

### Laravel endpoint contract (recommended)

- `POST /api/desktop/events/batch`
  - Auth: `Authorization: Bearer <token>`
  - Integrity: `X-Amithy-Signature` (HMAC SHA-256 over raw JSON body)
  - Body includes app role, app instance id, timestamp, and batched events.
- `GET /api/desktop/policy?role=<admin|player>&instance=<id>`
  - Returns current policy for the app instance/role.

### Policy payload fields

- `locked` (boolean)
- `lockReasonCode` (string, optional)
- `lockAt` (ISO string, optional)
- `graceUntil` (ISO string, optional)
- `minHeartbeatSeconds` (number, optional)

### Runtime environment variables

- `AMITHY_API_BASE_URL` (example: `https://yourdomain.com`)
- `AMITHY_API_TOKEN` (server-issued token)
- `AMITHY_ENV` (optional)
