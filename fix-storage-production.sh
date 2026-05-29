#!/bin/bash
# Fix storage symlink and permissions on production server
# Run: sudo bash fix-storage-production.sh

set -euo pipefail

cd /var/www/checkout

WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

echo "=== Fixing Storage Symlink ==="

if [ -L "public/storage" ] || [ -e "public/storage" ]; then
    echo "Removing existing storage symlink/directory..."
    rm -rf public/storage
fi

echo "Creating storage symlink..."
sudo -u "${WEB_USER}" php artisan storage:link

if [ -L "public/storage" ]; then
    echo "✓ Storage symlink created successfully"
    echo "  Symlink: $(readlink -f public/storage)"
else
    echo "✗ Failed to create storage symlink"
    exit 1
fi

echo "=== Fixing permissions (storage + bootstrap/cache) ==="
chown -R "${WEB_USER}:${WEB_GROUP}" storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

echo "Clearing cache (as ${WEB_USER})..."
sudo -u "${WEB_USER}" php artisan optimize:clear

echo ""
echo "=== Verification ==="
ls -la storage/framework/views | head -3
ROOT_COUNT=$(find storage/framework/views -user root 2>/dev/null | wc -l)
if [ "$ROOT_COUNT" -gt 0 ]; then
    echo "✗ Warning: $ROOT_COUNT view files still owned by root"
    exit 1
fi
echo "✓ No root-owned compiled views"

echo ""
echo "=== Fix Complete ==="
