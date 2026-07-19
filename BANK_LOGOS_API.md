# Bank logos API

Bank logos are stored on the existing `banks` table (`logo_path`, `logo_source`). Syncing bank names/codes never clears logos.

## Endpoints

### Consumer (native / web app — Sanctum)

`GET /api/v1/consumer/banks`

```json
{
  "success": true,
  "data": {
    "banks": [
      {
        "code": "000014",
        "name": "Access Bank",
        "logo_url": "https://check-outpay.com/storage/bank-logos/000014.svg"
      }
    ]
  }
}
```

`logo_url` is `null` when unmapped.

### Rentals / web

`GET /api/v1/rentals/banks` — same row shape: `code`, `name`, `logo_url`.

`POST /api/v1/rentals/kyc/banks` — possible banks for an account; each row includes `logo_url` when the code is mapped.

## Admin

Admin → **Bank Logos**: filter, assign from library, upload SVG/PNG, clear, auto-map.

CLI: `php artisan banks:import-logos` (optional `--force`).

Library SVGs live in `resources/bank-logos/library/`. Uploads go to `storage/app/public/bank-logos/{code}.ext`.
