#!/bin/bash
# Fix storage symlink and permissions on production server
# Run this script on your production server

cd /var/www/checkout || exit 1

echo "=== Fixing Storage Symlink ==="

# Remove existing symlink if it exists (broken or not)
if [ -L "public/storage" ] || [ -e "public/storage" ]; then
    echo "Removing existing storage symlink/directory..."
    rm -rf public/storage
fi

# Create the storage symlink
echo "Creating storage symlink..."
php artisan storage:link

# Verify symlink was created
if [ -L "public/storage" ]; then
    echo "✓ Storage symlink created successfully"
    echo "  Symlink: $(readlink -f public/storage)"
else
    echo "✗ Failed to create storage symlink"
    exit 1
fi

# Set proper permissions
echo "Setting permissions..."
chmod -R 775 storage/app/public
chown -R www-data:www-data storage/app/public 2>/dev/null || chown -R apache:apache storage/app/public 2>/dev/null || echo "Note: Could not change ownership (may need sudo)"

# Clear cache
echo "Clearing cache..."
php artisan optimize:clear

echo ""
echo "=== Verification ==="
echo "Checking storage directory..."
ls -la storage/app/public/settings/ 2>/dev/null && echo "✓ Settings directory exists" || echo "✗ Settings directory missing"

echo ""
echo "=== Fix Complete ==="
echo "Storage symlink should now be working!"
echo "Test by visiting: Admin → Settings and checking logo previews"
