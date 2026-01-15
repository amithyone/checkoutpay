# Fix Storage 404 Errors on Production Server

## Problem
Getting 404 errors for uploaded images like:
```
DBDzrVmlzZGlr8kyXerPR1M6xbHbhllloQQgISFQ.png:1 Failed to load resource: the server responded with a status of 404
```

## Root Cause
The `public/storage` symlink is missing or broken on the production server. This symlink connects `public/storage` to `storage/app/public` so uploaded files can be accessed via the web.

## Solution

### Step 1: SSH into Production Server
```bash
ssh your-server
cd /var/www/checkout  # or your actual checkout path
```

### Step 2: Remove Existing Symlink (if broken)
```bash
rm -f public/storage
```

### Step 3: Create Storage Symlink
```bash
php artisan storage:link
```

### Step 4: Verify Symlink
```bash
ls -la public/storage
# Should show: public/storage -> /var/www/checkout/storage/app/public
```

### Step 5: Set Proper Permissions
```bash
chmod -R 775 storage/app/public
chown -R www-data:www-data storage/app/public  # Adjust user/group as needed
```

### Step 6: Clear Cache
```bash
php artisan optimize:clear
```

## Complete Fix Script (Run on Production)
```bash
cd /var/www/checkout && \
rm -f public/storage && \
php artisan storage:link && \
chmod -R 775 storage/app/public && \
php artisan optimize:clear && \
echo "Storage symlink fixed!"
```

## Verify It's Working
After running the fix, check:
1. Go to Admin → Settings
2. Logo previews should now load
3. Check browser console - no more 404 errors

## Alternative: Check Storage Path
If symlink still doesn't work, verify the storage path:
```bash
# Check if storage directory exists
ls -la storage/app/public/settings/

# Check symlink target
readlink -f public/storage

# Should point to: /var/www/checkout/storage/app/public
```
