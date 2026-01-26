# Cloudflare Configuration for Account Number API

## ⚠️ IMPORTANT: Cloudflare Should NOT Cache Account Assignment

### Why Cloudflare Hurts Account Assignment:

1. **Adds Latency**: Cloudflare adds 50-200ms extra hop between user and server
2. **Caching Breaks Logic**: Account assignment needs fresh database data every time
3. **Cache Conflicts**: Multiple requests might get same cached account number
4. **Timeout Issues**: Cloudflare timeout (100s) + PHP timeout can cause 524 errors

### ✅ Solution: Bypass Cloudflare for API Endpoints

## Cloudflare Page Rule Configuration

### Step 1: Create Page Rule to Bypass API

1. Go to **Cloudflare Dashboard** → **Rules** → **Page Rules**
2. Click **Create Page Rule**
3. Configure:

**URL Pattern:**
```
*check-outpay.com/api/*
```

**Settings (Add Multiple):**
1. **Cache Level** → **Bypass**
2. **Security Level** → **Medium** (or as needed)
3. **Disable Apps** → **Off** (optional, reduces overhead)

### Step 2: Verify Rule is Active

- Rule should show as **Active**
- Test API endpoint - should go directly to origin server

## Alternative: Use Cloudflare Workers (Advanced)

If you want Cloudflare benefits without caching:

```javascript
// Cloudflare Worker - API Proxy
addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {
  // Don't cache API requests
  const response = await fetch(request, {
    cf: {
      cacheEverything: false,
      cacheTtl: 0
    }
  })
  
  return response
}
```

## Performance Comparison

### With Cloudflare Caching (BAD):
- First request: 200ms (server) + 150ms (Cloudflare) = **350ms**
- Cached requests: 150ms (Cloudflare cache) = **150ms** ❌ WRONG ACCOUNT!
- **Problem**: Same account assigned to multiple payments

### With Cloudflare Bypass (GOOD):
- All requests: 50ms (server directly) = **50ms** ✅ CORRECT!
- **Benefit**: Fresh account every time, faster response

### Without Cloudflare (BEST for API):
- All requests: 50ms (direct to server) = **50ms** ✅ FASTEST!
- **Benefit**: No extra hops, lowest latency

## Recommended Setup

### ✅ DO:
- Use Cloudflare for static assets (CSS, JS, images)
- Bypass Cloudflare for `/api/*` endpoints
- Use Cloudflare for public pages (if needed)

### ❌ DON'T:
- Cache API responses
- Use Cloudflare for account assignment endpoints
- Enable Cloudflare caching for dynamic content

## Testing

After configuring bypass:

```bash
# Test API endpoint
curl -X POST https://check-outpay.com/api/v1/payment-request \
  -H "Content-Type: application/json" \
  -H "X-API-Key: YOUR_API_KEY" \
  -d '{"name":"Test","amount":1000,"webhook_url":"https://example.com"}' \
  -w "\nTime: %{time_total}s\n"

# Should complete in < 0.2 seconds
# Check response headers - should NOT have CF-Cache-Status
```

## Expected Results

**Before (with Cloudflare caching):**
- Response time: 150-350ms
- Risk: Same account assigned multiple times
- Timeout errors: More frequent

**After (bypass Cloudflare for API):**
- Response time: 50-100ms
- Risk: None (fresh data every time)
- Timeout errors: Eliminated

## Summary

**Cloudflare = BAD for account assignment**
- Adds latency
- Breaks caching logic
- Causes timeout issues

**Bypass Cloudflare for API = GOOD**
- Faster response times
- Correct account assignment
- No timeout issues

**Use Cloudflare for static assets only!**
