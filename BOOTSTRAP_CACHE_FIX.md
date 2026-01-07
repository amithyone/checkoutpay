# Fix: Bootstrap Cache Directory Error

## 🔴 Error
```
The /home/checzspw/public_html/bootstrap/cache directory must be present and writable.
```

## ✅ Solution

Create the directory and set correct permissions:

### Via SSH/Terminal:

```bash
cd /home/checzspw/public_html

# Create bootstrap/cache directory
mkdir -p bootstrap/cache

# Set permissions (755 for directory, 775 for writable)
chmod -R 755 bootstrap/cache
chmod -R 775 bootstrap/cache

# Also ensure storage directories exist and are writable
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache
mkdir -p storage/logs

# Set storage permissions
chmod -R 755 storage
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Via cPanel File Manager:

1. Go to **File Manager**
2. Navigate to `public_html`
3. Navigate to `bootstrap` folder
4. If `cache` folder doesn't exist:
   - Click **+ Folder**
   - Name it `cache`
   - Click **Create**
5. Right-click `cache` folder → **Change Permissions**
6. Set to **755** or **775**
7. Check **Recurse into subdirectories**
8. Click **Change Permissions**

## 🔧 Complete Setup Script

Run this complete setup on your server:

```bash
cd /home/checzspw/public_html

# Create required directories
mkdir -p bootstrap/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/framework/cache
mkdir -p storage/logs

# Set permissions
chmod -R 755 bootstrap/cache
chmod -R 775 bootstrap/cache
chmod -R 755 storage
chmod -R 775 storage

# Now try composer install again
composer install --no-dev --optimize-autoloader
```

## 📝 Why This Happens

- Laravel needs `bootstrap/cache` to store cached package manifests
- The directory must be writable for Laravel to function
- This directory is often missing after Git clone/pull
- It's in `.gitignore` so it's not tracked in Git

## ✅ Verification

After creating directories, verify:

```bash
# Check if directory exists
ls -la bootstrap/cache

# Check permissions
stat bootstrap/cache

# Should show: drwxrwxr-x or drwxr-xr-x
```

## 🚨 If Still Having Issues

Try setting ownership (if you have sudo access):

```bash
# Find your user
whoami

# Set ownership (replace 'checzspw' with your username)
chown -R checzspw:checzspw bootstrap/cache
chown -R checzspw:checzspw storage
```

## 📋 Complete Deployment Checklist

After fixing permissions, run:

```bash
cd /home/checzspw/public_html

# 1. Create directories
mkdir -p bootstrap/cache storage/framework/{sessions,views,cache} storage/logs

# 2. Set permissions
chmod -R 755 bootstrap/cache
chmod -R 775 bootstrap/cache storage

# 3. Install dependencies
composer install --no-dev --optimize-autoloader

# 4. Generate app key
php artisan key:generate

# 5. Run migrations
php artisan migrate --force

# 6. Clear and cache config
php artisan config:clear
php artisan config:cache
php artisan route:cache
```

That's it! ✅
