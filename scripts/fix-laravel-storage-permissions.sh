#!/usr/bin/env bash
# Fix Laravel storage/bootstrap permissions for PHP-FPM (www-data).
# Run after any `php artisan` command executed as root.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

cd "$ROOT"

echo "Fixing ownership: ${WEB_USER}:${WEB_GROUP} on storage/ and bootstrap/cache/"
chown -R "${WEB_USER}:${WEB_GROUP}" storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "Clearing compiled views (as ${WEB_USER})..."
sudo -u "${WEB_USER}" php artisan view:clear 2>/dev/null || true

echo "Done. Verify:"
ls -la storage/framework/views | head -3
