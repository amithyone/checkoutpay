# CheckoutPay partner kit

White-label partners receive a **private Git repository** with an **ionCube-encoded Laravel core** and **readable** skin, config, routes, views, and CheckoutNow UI sources. Checkout retains plaintext development in `amithyone/checkoutpay`; partners never merge upstream plaintext.

## What you can edit

| Area | Purpose |
|------|---------|
| `config/**` | Feature toggles, `partner_banks.php`, `brand.php`, `.error` / env |
| `routes/**` | Thin route files pointing at encoded controllers |
| `resources/views/**` | Admin/business dashboard theming and copy |
| `database/migrations/**` | Schema updates when you run `partner-update.sh` |
| `public/**` | Static assets and `.well-known` on your domain |
| `scripts/partner-build/**` | `partner-update.sh`, verify helpers |
| `partner-build-manifest.json` | Release version + SHA-256 of encoded files |
| `checkoutnow/` (separate app repo) | Capacitor UI, logos, `brand.json`, `npm run brand:sync` |

## What stays encoded (do not expect readable PHP)

All payment, wallet, payout, WhatsApp, merchant, admin, and bank integration logic under:

- `app/Http/Controllers`, `Middleware`
- `app/Services`, `Jobs`, `Models`
- `app/Console/Commands` (except the two ops commands below)
- `app/Providers`, `Support`, `Exceptions`, `Listeners`, `Notifications`

The **license client** is encoded so the enforcement check cannot be patched out.

### Readable Artisan ops only

- `php artisan partner:verify-build` — checksum + ionCube loader check
- `php artisan partner:license-ping` — phone home to issuer

## Environment (partner drop)

Copy `.env.example` to `.error` (this project loads secrets from `.error`, not `.env`).

```env
PARTNER_LICENSE_ENFORCED=true
PARTNER_LICENSE_KEY=pl_...
PARTNER_LICENSE_ISSUER_URL=https://check-outpay.com
PARTNER_LICENSE_GRACE_HOURS=72

# Optional: issuer advertises newer builds
# PARTNER_LICENSE_LATEST_BUILD_VERSION=1.0.1

# Brand (Laravel + emails)
BRAND_APP_NAME=YourWallet
BRAND_SUPPORT_EMAIL=support@yourbrand.com
BRAND_PRIMARY_COLOR=#0d9488

# Bank rails (slugs we shipped in your encoded build)
PARTNER_BANK_DEFAULT=mevonpay
PARTNER_BANKS_ENABLED=mevonpay
```

On **Checkout issuer** (check-outpay.com):

```env
PARTNER_LICENSE_ISSUER_ENABLED=true
PARTNER_LICENSE_ENFORCED=false
PARTNER_LICENSE_LATEST_BUILD_VERSION=1.0.0
```

Issue keys in **Admin → Partner licenses** (super admin).

## License behaviour

- Consumer API and merchant payout routes use middleware `partner.license`.
- Partners call issuer `POST /api/v1/partner-license/ping` with key, hostname, and build version from `partner-build-manifest.json`.
- If the issuer is unreachable, a **72-hour grace** period applies after the last successful ping; then licensed features return HTTP 503 (fail closed).
- `min_build_version` on the license can force security updates.

## Updates (partners)

Partners **do not** `git pull` from Checkout plaintext.

1. Checkout publishes a new tag `release/X.Y.Z` to your private repo (encoded full `app/` core).
2. You run:

```bash
./scripts/partner-build/partner-update.sh release/1.0.1
```

This verifies checksums, runs `partner:license-ping`, migrates, and refreshes caches. It **never** overwrites `.error`.

## Updates (Checkout release engineering)

From plaintext `checkoutpay` at a release tag:

```bash
export IONCUBE_ENCODER=/path/to/ioncube_encoder
export PARTNER_RELEASE_GIT_REMOTE=git@github.com:YOUR_ORG/partner-checkout.git
./scripts/partner-build/publish-partner-release.sh 1.0.0
```

Manifest paths: `scripts/partner-build/encode-manifest.json`. The publisher **fails** if ionCube is missing or plaintext PHP remains in encoded trees.

## Custom bank rails

Partners **do not** implement bank adapters.

1. You enable slugs in readable `config/partner_banks.php` / env only.
2. Checkout implements the adapter in plaintext, encodes it, and ships it in your next tagged release.
3. We document any new env keys (API URLs, secrets) for that rail.

## CheckoutNow mobile / web UI

- Edit `checkoutnow/brand.json` (see `brand.json.example`) for name, colors, bundle IDs, API base URL.
- Replace `public/favicon.svg` and run `npm run brand:sync` before `npm run build`.
- Money rails and secrets remain on the encoded Laravel backend only.

## Handoff checklist (Checkout before first partner release)

- Remove production secrets, Firebase keys, keystores, and live credentials from the partner repo template.
- Confirm `PARTNER_LICENSE_ENFORCED=true` in partner `.env.example`.
- Smoke test on PHP with ionCube Loader: OTP login, consumer wallet read, merchant payout API (staging keys).
- Tag first release `release/1.0.0` to the partner private GitHub repo.

## Private repositories

First delivery is typically **two** private GitHub repositories (Laravel backend + CheckoutNow UI). Checkout creates repos and pushes **tags only** for backend updates. Request repo names and GitHub org access from Checkout before go-live.

## Support

Encoded stack traces require Checkout support. Keep your license key and `partner-build-manifest.json` version handy when opening incidents.
