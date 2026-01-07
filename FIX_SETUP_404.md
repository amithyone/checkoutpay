# Fix Setup Route 404 Error

## 🔴 Error
```
404 - Setup route not found
```

## ✅ Solution

The route cache might be stale. Clear it and verify routes.

### Step 1: Clear Route Cache

Run on your server:

```bash
cd /home/checzspw/public_html

# Clear route cache
php artisan route:clear

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# List routes to verify setup route exists
php artisan route:list | grep setup
```

Should show:
```
GET|HEAD  setup ................ setup › SetupController@index
```

### Step 2: Verify Route File

Make sure `routes/web.php` has the setup route:

```bash
cat routes/web.php | grep setup
```

Should show:
```php
Route::get('/setup', [SetupController::class, 'index'])->name('setup');
```

### Step 3: Check RouteServiceProvider

Verify routes are being loaded:

```bash
cat app/Providers/RouteServiceProvider.php | grep web.php
```

Should show:
```php
Route::middleware('web')
    ->group(base_path('routes/web.php'));
```

### Step 4: Test Route Directly

```bash
# Test if route exists
php artisan route:list --path=setup
```

### Step 5: If Still 404 - Check .htaccess

Make sure `.htaccess` in `public` folder is correct:

```bash
cat public/.htaccess
```

Should have rewrite rules for Laravel.

## 🎯 Quick Fix Commands

```bash
cd /home/checzspw/public_html

# Clear all caches
php artisan route:clear
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Rebuild route cache
php artisan route:cache

# List routes
php artisan route:list | grep setup

# Try accessing setup
# Visit: http://check-outpay.com/setup
```

## 🔍 Alternative: Direct Access

If route cache doesn't work, try accessing without cache:

```bash
# Don't use route:cache, use route:clear instead
php artisan route:clear

# Then access: http://check-outpay.com/setup
```

## 🚨 If Still 404

Check if the route file is being loaded:

```bash
# Add a test route
echo "Route::get('/test-setup', function() { return 'Setup route works!'; });" >> routes/web.php

# Clear cache
php artisan route:clear

# Test: http://check-outpay.com/test-setup
# If this works, the issue is with SetupController
```

---

**Clear route cache first - that's usually the issue!** 🔍
