# Deployment Instructions - Performance Fixes

## Problem
Checkout API is timing out after 10 seconds when assigning account numbers.

## Solution
Deploy the performance optimizations that reduce account assignment time from 50ms-2s+ down to 10-50ms.

## Deployment Steps for Live Server

### 1. SSH into your live checkout server
```bash
ssh user@your-checkout-server
cd /var/www/checkout  # or wherever your checkout project is
```

### 2. Pull latest changes
```bash
git pull origin main
```

### 3. Run the migration (adds database index)
```bash
php artisan migrate
```

### 4. Clear all caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### 5. Restart PHP-FPM (if applicable)
```bash
# For PHP-FPM
sudo systemctl restart php8.1-fpm  # or your PHP version

# Or for Apache
sudo systemctl restart apache2

# Or for Nginx + PHP-FPM
sudo systemctl restart nginx
sudo systemctl restart php8.1-fpm
```

### 6. Verify the migration ran successfully
```bash
php artisan migrate:status
```

You should see:
- `2026_01_26_000001_add_composite_index_for_account_assignment` as "Ran"

### 7. Test the API endpoint
```bash
# Test that account assignment is faster
curl -X POST https://check-outpay.com/api/v1/payment-request \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{"name":"Test User","amount":1000,"webhook_url":"https://example.com/webhook"}'
```

## Expected Results

- **Before**: 10+ second timeout
- **After**: < 100ms response time
- **Database queries**: Reduced from 4-6 to 0-1 (with cache)

## Monitoring

After deployment, monitor:
- Response times in logs
- Error rates
- Database query performance
- Cache hit rates

## Rollback (if needed)

If issues occur:
```bash
# Revert code
git reset --hard HEAD~1

# Drop the index (optional)
php artisan migrate:rollback --step=1

# Clear cache
php artisan cache:clear
```

## Troubleshooting

### If migration fails:
```bash
# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check if index already exists
php artisan tinker
>>> DB::select("SHOW INDEX FROM payments WHERE Key_name = 'idx_status_account_expires'");
```

### If cache is not working:
- Check Redis/Memcached is running
- Verify cache driver in `.env`: `CACHE_DRIVER=redis` or `CACHE_DRIVER=file`
- Check cache permissions: `storage/framework/cache` should be writable

### If still timing out:
- Check server resources (CPU, memory)
- Check database performance
- Review error logs: `storage/logs/laravel.log`
- Verify cron is running for orphaned account cleanup
