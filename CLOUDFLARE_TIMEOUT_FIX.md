# Cloudflare Timeout Fix

## Problem
- Entire site is slow
- Cloudflare is timing out
- Account number assignment still failing/timing out

## Root Causes

1. **PHP Execution Time Limit**: Default PHP `max_execution_time` might be 30 seconds or less
2. **Cloudflare Timeout**: Cloudflare has a 100-second timeout, but if PHP times out first, Cloudflare shows 524 error
3. **Slow Account Assignment**: Even with caching, account assignment might still be slow under load

## Fixes Applied

### ✅ 1. Increased PHP Execution Time for API Endpoint
**File**: `app/Http/Controllers/Api/PaymentController.php`

- Added `set_time_limit(60)` at start of payment creation
- Gives 60 seconds for account assignment (should only take 10-50ms with optimizations)
- Prevents PHP timeout before Cloudflare timeout

### ✅ 2. Added Performance Timing to Payment Creation
**File**: `app/Services/PaymentService.php`

- Added timing logs to track total payment creation time
- Helps identify if account assignment or other operations are slow

## Cloudflare Configuration Recommendations

### 1. Increase Cloudflare Timeout (if needed)
In Cloudflare dashboard:
- Go to **Speed** → **Optimization**
- Set **HTTP/2 to Origin** timeout to 100 seconds (default)
- Or increase if needed

### 2. Bypass Cloudflare for API Endpoints (Recommended)
Add Cloudflare Page Rule:
- **URL Pattern**: `*check-outpay.com/api/*`
- **Setting**: **Cache Level** → **Bypass**
- **Setting**: **Security Level** → **Medium** (or as needed)

This ensures API requests go directly to origin without Cloudflare caching delays.

### 3. Enable Cloudflare Workers (Optional)
For better performance, consider using Cloudflare Workers to:
- Cache account numbers at edge
- Reduce origin load
- Faster response times globally

## Server Configuration

### PHP Configuration
Check and update `php.ini`:
```ini
max_execution_time = 60
max_input_time = 60
memory_limit = 256M
```

### Nginx/Apache Configuration
Ensure timeouts are sufficient:
```nginx
# Nginx
proxy_read_timeout 60s;
fastcgi_read_timeout 60s;
```

```apache
# Apache
Timeout 60
```

## Monitoring

After deployment, check logs for:
1. `duration_ms` in account assignment logs
2. `duration_ms` in payment creation logs
3. Any timeout errors

**Expected Performance**:
- Account assignment: < 50ms
- Total payment creation: < 200ms

## Troubleshooting

### If still timing out:

1. **Check PHP execution time**:
   ```bash
   php -i | grep max_execution_time
   ```

2. **Check Cloudflare timeout**:
   - Look for 524 errors in Cloudflare dashboard
   - Check Cloudflare logs for timeout events

3. **Check database performance**:
   ```bash
   # Check slow queries
   mysql -e "SHOW FULL PROCESSLIST;"
   ```

4. **Check cache is working**:
   ```bash
   php artisan tinker
   >>> Cache::get('account_number_service:pool_accounts');
   ```

5. **Check server resources**:
   ```bash
   # CPU usage
   top
   
   # Memory usage
   free -h
   
   # Disk I/O
   iostat -x 1
   ```

## Quick Fixes

### Immediate Actions:
1. ✅ Deploy code changes (already done)
2. ⚠️ Update PHP `max_execution_time` to 60 seconds
3. ⚠️ Configure Cloudflare Page Rule to bypass API endpoints
4. ⚠️ Clear all caches: `php artisan cache:clear`

### Long-term Solutions:
1. Consider Redis/Memcached for faster caching
2. Consider queue system for account assignment (if still slow)
3. Consider database read replicas for high load
4. Consider CDN caching for static assets

## Expected Results

- **Before**: 10+ second timeout, 524 errors from Cloudflare
- **After**: < 200ms response time, no timeouts

## Testing

Test the API endpoint:
```bash
curl -X POST https://check-outpay.com/api/v1/payment-request \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{"name":"Test User","amount":1000,"webhook_url":"https://example.com/webhook"}' \
  -w "\nTime: %{time_total}s\n"
```

Should complete in < 1 second.
