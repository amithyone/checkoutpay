# CheckoutNow Android APK

Reference for the **CheckoutNow** consumer wallet app (Android APK): what it is, where it lives, how users download it, and how to build and publish a new release.

---

## Overview

| Item | Value |
|------|--------|
| **App name** | CheckoutNow |
| **Package / application ID** | `com.checkoutnow.app` |
| **Current version** | **2.8** (`versionCode` **19**) |
| **Stack** | React 19 + Vite + Capacitor 7 (hybrid WebView app) |
| **Backend** | CheckoutPay Laravel API (`/api/v1/consumer/*`) |
| **Source repo (on server)** | `/var/www/checkoutnow` |
| **Staged APK (served to users)** | `/var/www/checkoutnow/dist/checkoutnow-android.apk` |

CheckoutNow is the mobile wallet for Nigerian consumers: NGN wallet, bank top-up, P2P transfers, pay bills (VTU), dollar virtual card, transaction history, in-app support chat, and FCM push alerts when money arrives.

The same wallet account can also be used via **WhatsApp Wallet** and the **web app** at [https://app.check-outnow.com](https://app.check-outnow.com).

---

## Download links (public)

### Primary — CheckoutPay marketing site

Users download through the Laravel app (same origin as CheckoutPay):

```
https://check-outnow.com/download/checkoutnow-android.apk
```

- **Route:** `GET /download/checkoutnow-android.apk` → `CheckoutNowApkDownloadController`
- **Named route:** `checkoutnow.apk.download`
- **Download filename:** `checkoutnow-android.apk`

### Optional overrides (`.env` on CheckoutPay)

```env
# Full public URL if APK is hosted on CDN or another domain
WHATSAPP_WALLET_ANDROID_APK_URL=

# Filesystem path to the APK file the download route reads
WHATSAPP_WALLET_ANDROID_APK_PATH=/var/www/checkoutnow/dist/checkoutnow-android.apk
```

If `WHATSAPP_WALLET_ANDROID_APK_URL` is set, marketing pages and Play badge links use that URL instead of the same-origin download route.

### Google Play / App Store

Until store listings are configured, marketing **Google Play** badges fall back to the APK download URL, and **App Store** badges fall back to the web app:

```env
CHECKOUTNOW_PLAY_STORE_URL=https://play.google.com/store/apps/details?id=com.checkoutnow.app
CHECKOUTNOW_APP_STORE_URL=https://apps.apple.com/app/checkoutnow/id0000000000
WHATSAPP_WALLET_APP_URL=https://app.check-outnow.com
```

Code: `App\Support\CheckoutNowApp`.

---

## How download serving works

```
checkoutnow (build)  →  dist/checkoutnow-android.apk
                              ↓
CheckoutPay route    →  reads WHATSAPP_WALLET_ANDROID_APK_PATH
                              ↓
Browser              →  downloads checkoutnow-android.apk
```

1. Android debug/release APK is built under `checkoutnow/android/`.
2. `npm run apk:public` copies `app-debug.apk` → `dist/checkoutnow-android.apk`.
3. CheckoutPay serves that file with `Content-Type: application/vnd.android.package-archive`.

If the file is missing or unreadable, the download route returns **404** with: *"CheckoutNow Android app is not available for download yet."*

---

## Build a new APK

Run on the app server (or CI) from `/var/www/checkoutnow`.

### Prerequisites

- Node.js 22+
- JDK + Android SDK (Gradle wrapper in `android/`)
- Production API URL set at **build time** (embedded in the bundle)

### Environment

```bash
export VITE_CHECKOUT_API_BASE="https://check-outnow.com/api/v1"
# Optional — only when FCM is configured (google-services.json in android/app/)
export VITE_ENABLE_PUSH_NOTIFICATIONS=true
```

See `checkoutnow/.env.example`.

### Full mobile build (recommended)

Builds web assets, syncs Capacitor, assembles Android APK, and stages it for download:

```bash
cd /var/www/checkoutnow
npm install
npm run build
```

`npm run build` runs, in order:

1. Brand sync + strip APK from mobile assets
2. `vite build`
3. `npx cap sync android` (+ iOS)
4. `./gradlew :app:assembleDebug` (via `scripts/android-assemble-and-stage-apk.mjs`)
5. Copy to `dist/checkoutnow-android.apk` (via `scripts/copy-android-apk.mjs`)

### Partial builds

```bash
npm run build:web              # Web only (no native)
npm run build:mobile:android   # Vite + cap sync Android (no Gradle)
npm run apk:public             # Copy existing Gradle output → dist/ only
```

### Signed release (Play Store)

For Play Console, use Android Studio:

**Build → Generate Signed Bundle / APK** → prefer **AAB**.

Release signing is configured in `android/app/build.gradle` via Gradle properties:

- `RELEASE_STORE_FILE`
- `RELEASE_STORE_PASSWORD`
- `RELEASE_KEY_ALIAS`
- `RELEASE_KEY_PASSWORD`

---

## Publish after build

1. Confirm the staged file exists:

   ```bash
   ls -lh /var/www/checkoutnow/dist/checkoutnow-android.apk
   ```

2. Bump version in `checkoutnow/android/app/build.gradle`:

   ```gradle
   versionCode 20      // increment every release
   versionName "2.9"
   ```

3. Rebuild and test download:

   ```bash
   curl -I "https://check-outnow.com/download/checkoutnow-android.apk"
   ```

4. No Laravel deploy is required if only the APK on disk changed (unless you change env paths/URLs).

---

## App features (current)

| Area | Details |
|------|---------|
| **Auth** | Phone OTP, PIN, biometric unlock (`@capgo/capacitor-native-biometric`) |
| **Wallet** | NGN balance, bank VA top-up, P2P transfers, QR receive/scan |
| **Pay bills** | Airtime, data, electricity, cable TV via `consumer/vtu/*` |
| **Dollar virtual card** | Request, fund, withdraw, freeze — rates from admin-published FX |
| **History** | Wallet transactions |
| **Support** | Support screen + in-app support chat |
| **Push** | FCM via `@capacitor/push-notifications` → `POST consumer/wallet/push-token` |
| **Native** | Camera, barcode/ML Kit scan, share, haptics, status bar |

Main API client: `checkoutnow/src/lib/consumerApi.ts` → `{VITE_CHECKOUT_API_BASE}/consumer/*`.

---

## Backend requirements (CheckoutPay)

The APK talks to the **consumer API** on the same CheckoutPay install.

### Required

- Consumer wallet + auth routes enabled
- CORS allows the app origin (Capacitor uses `https://localhost` on device; web uses `app.check-outnow.com`)
- `VITE_CHECKOUT_API_BASE` in the APK build points at your live API

### Push notifications (optional but recommended)

On CheckoutPay (`.env`):

```env
FCM_PROJECT_ID=
FCM_SERVICE_ACCOUNT_JSON=/path/to/fcm-service-account.json
```

In `checkoutnow/android/app/`: add **`google-services.json`** from Firebase.

Config: `config/consumer_wallet.php` (`credit_push_enabled`, titles, channel).

### Virtual card FX

Rates shown in the app match **admin published** sell/buy rates (Settings → Dollar Virtual Card / Card Management → Refresh app FX rates). Marketing site calculator uses the same source.

---

## Marketing integration

CheckoutPay surfaces the app on:

- Home / products pages — `<x-marketing.app-store-badges />`
- Virtual card section — APK URL in `MarketingVirtualCard::snapshot()['apk_url']`
- WhatsApp wallet section — “same wallet powers CheckoutNow”

Helper class: `app/Support/CheckoutNowApp.php`.

---

## iOS note

The repo includes `ios/` (Capacitor). **iOS builds require macOS + Xcode.** There is no sideload IPA on the website for general users — use TestFlight or App Store. See `checkoutnow/docs/MOBILE.md`.

---

## Troubleshooting

| Problem | Check |
|---------|--------|
| Download 404 | File exists at `WHATSAPP_WALLET_ANDROID_APK_PATH`; permissions readable by web server |
| App shows “Missing API configuration” | Rebuild with `VITE_CHECKOUT_API_BASE` set |
| Login/API errors | API URL, SSL, consumer routes, Sanctum tokens |
| Push not working | `google-services.json`, `FCM_*` on server, `VITE_ENABLE_PUSH_NOTIFICATIONS=true` at build |
| Old version still downloading | CDN/cache — purge; confirm `dist/checkoutnow-android.apk` timestamp |
| APK inside APK bloat | Do **not** put APK in `public/`; only `dist/` + server download route (see `copy-android-apk.mjs`) |

---

## Related files

| Path | Purpose |
|------|---------|
| `/var/www/checkoutnow/` | App source (React + Capacitor) |
| `/var/www/checkoutnow/capacitor.config.ts` | App ID, display name, web dir |
| `/var/www/checkoutnow/android/app/build.gradle` | Version code/name, signing |
| `/var/www/checkoutnow/docs/MOBILE.md` | Capacitor build & store checklist |
| `/var/www/checkout/app/Http/Controllers/Public/CheckoutNowApkDownloadController.php` | Download handler |
| `/var/www/checkout/app/Support/CheckoutNowApp.php` | Public URLs helper |
| `/var/www/checkout/config/whatsapp.php` | APK path/URL env keys |
| `/var/www/checkout/routes/web.php` | Download route registration |

---

## Quick reference

```bash
# Build + stage APK
cd /var/www/checkoutnow && VITE_CHECKOUT_API_BASE="https://check-outnow.com/api/v1" npm run build

# Verify staged file
ls -lh dist/checkoutnow-android.apk

# Test public download
curl -I "https://check-outnow.com/download/checkoutnow-android.apk"
```

**Last documented APK on server:** ~9.4 MB, built 2026-06-12 (`versionName` 2.8 / `versionCode` 19).
