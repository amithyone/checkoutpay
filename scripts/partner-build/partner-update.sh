#!/usr/bin/env bash
# Partner-side update helper — run in the partner private repo checkout (never overwrites .error).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TAG="${1:-}"

cd "${ROOT}"

if [[ ! -f "${ROOT}/partner-build-manifest.json" ]]; then
  echo "partner-build-manifest.json missing. This script is for partner encoded drops only." >&2
  exit 1
fi

if [[ -n "${TAG}" ]]; then
  echo "==> Fetching ${TAG}"
  git fetch --tags origin
  git checkout "${TAG}"
fi

if ! php -m | grep -qi 'ionCube Loader'; then
  echo "WARNING: ionCube Loader extension is not loaded. Encoded core will not run." >&2
fi

echo "==> Verifying encoded build integrity"
php artisan partner:verify-build

echo "==> License ping"
php artisan partner:license-ping --force || true

echo "==> Migrations"
php artisan migrate --force

echo "==> Caching config/routes (optional — comment out if you hot-patch config)"
php artisan config:cache
php artisan route:cache

echo "Partner update complete."
