# Debug HTTP 500 Error

## 🔴 Error
```
HTTP ERROR 500
This page isn't working right now
```

## ✅ Quick Fixes

### Step 1: Check Error Logs

**Via SSH:**
```bash
cd /home/checzspw/public_html

# Check Laravel logs
tail -50 storage/logs/laravel.log

# Check PHP error log
tail -50 /home/checzspw/public_html/error_log
```

**Via cPanel:**
1. Go to **cPanel → Error Logs**
2. Check recent errors

### Step 2: Check Common Issues

#### Issue 1: Missing Vendor Folder
```bash
# Check if vendor exists
ls -la vendor/

# If missing, install dependencies
composer install --no-dev --optimize-autoloader
```

#### Issue 2: Missing Bootstrap/Cache Directory
```bash
# Create if missing
mkdir -p bootstrap/cache
chmod -R 775 bootstrap/cache
```

#### Issue 3: Storage Permissions
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

#### Issue 4: Missing .env File
```bash
# Check if .env exists
ls -la .env

# If missing, copy from example
cp .env.example .env

# Generate app key
php artisan key:generate
```

#### Issue 5: Database Connection Error
```bash
# Test database connection
php artisan db:show

# If fails, check .env database settings
cat .env | grep DB_
```

### Step 3: Clear All Caches

```bash
cd /home/checzspw/public_html

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Rebuild config
php artisan config:cache
```

### Step 4: Check PHP Errors

Enable error display temporarily:

```bash
# Edit .env
nano .env

# Set these temporarily:
APP_DEBUG=true
LOG_LEVEL=debug

# Clear config
php artisan config:clear
```

Then refresh the page to see the actual error.

### Step 5: Check File Permissions

```bash
# Set correct permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

# Special permissions for storage and cache
chmod -R 775 storage bootstrap/cache
```

## 🎯 Most Common Causes

1. **Missing vendor folder** - Run `composer install`
2. **Missing .env file** - Copy from `.env.example` and configure
3. **Database connection error** - Check database credentials
4. **Storage permissions** - Set to 775
5. **Missing bootstrap/cache** - Create directory
6. **PHP version mismatch** - Ensure PHP 8.1+

## 🔍 Quick Diagnostic Script

Run this to check everything:

```bash
cd /home/checzspw/public_html

echo "=== Checking Files ==="
[ -f .env ] && echo "✅ .env exists" || echo "❌ .env missing"
[ -d vendor ] && echo "✅ vendor exists" || echo "❌ vendor missing"
[ -d bootstrap/cache ] && echo "✅ bootstrap/cache exists" || echo "❌ bootstrap/cache missing"
[ -d storage ] && echo "✅ storage exists" || echo "❌ storage missing"

echo ""
echo "=== Checking Permissions ==="
ls -ld storage bootstrap/cache

echo ""
echo "=== Checking Database ==="
php artisan db:show 2>&1 | head -5

echo ""
echo "=== Recent Errors ==="
tail -10 storage/logs/laravel.log 2>/dev/null || echo "No log file"
```

## 🚨 If Still Not Working

1. **Check PHP version:**
```bash
php -v
# Should be PHP 8.1 or higher
```

2. **Check if Laravel is working:**
```bash
php artisan --version
```

3. **Check web server error logs:**
```bash
# Apache
tail -50 /var/log/apache2/error.log

# Or check cPanel error logs
```

4. **Try accessing setup page:**
```
http://yourdomain.com/setup
```

---

**Check the error logs first - they'll tell you exactly what's wrong!** 🔍
