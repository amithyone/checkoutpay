# Cache Warm-up Guide

## Problem
After `php artisan cache:clear`, all cache keys are missing. The cache needs to be "warmed up" by making requests that populate the cache.

## Solution: Warm Up Cache

### Option 1: Automatic Warm-up (Recommended)

Create a command to warm up cache:

```bash
php artisan cache:warm
```

### Option 2: Manual Warm-up

Make requests to populate cache:

```bash
# 1. Visit homepage (populates page_home and settings)
curl https://check-outpay.com/

# 2. Make a test payment request (populates account number caches)
curl -X POST https://check-outpay.com/api/v1/payment-request \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{"name":"Test","amount":1000,"webhook_url":"https://example.com"}'
```

### Option 3: Create Warm-up Command

Run this to warm up all caches:

```bash
php artisan tinker
>>> \App\Models\Setting::get('site_logo'); // Warm settings cache
>>> \App\Models\Page::getBySlug('home'); // Warm page cache
>>> app(\App\Services\AccountNumberService::class)->getAvailablePoolCount(); // Warm account cache
```

## Why Cache Keys Are Missing

After `cache:clear`, cache is empty. Cache keys are populated on first use:
- `page_home` - populated when homepage is visited
- `setting_*` - populated when settings are accessed
- `account_number_service:*` - populated when account is assigned

## Expected Behavior

1. **First request after cache clear**: Slower (populates cache)
2. **Subsequent requests**: Fast (uses cache)

## Check Cache After Warm-up

```bash
php artisan tinker
>>> Cache::has('page_home')
>>> Cache::has('setting_site_logo')
>>> Cache::has('account_number_service:pool_accounts')
```

All should return `true` after warm-up.
