# LCP (Largest Contentful Paint) Optimization

## Problem
LCP is 11.44 seconds (poor) - header#home element is slow to render.

## Root Causes Identified

1. **N+1 Database Queries**: Multiple `Setting::get()` calls in view (3+ queries)
2. **No Caching**: Page and Settings loaded from database every request
3. **External CDN Resources**: Tailwind CSS and Font Awesome blocking render
4. **No Resource Hints**: Missing preconnect/dns-prefetch for CDNs

## Fixes Applied

### ✅ 1. Cache Settings Model
**File**: `app/Models/Setting.php`

- Added caching to `Setting::get()` method (1 hour TTL)
- Cache invalidates automatically when settings are updated
- **Impact**: Eliminates 3+ database queries per page load

**Before**:
```php
public static function get(string $key, $default = null)
{
    $setting = self::where('key', $key)->first(); // Query every time!
    return $setting ? $setting->value : $default;
}
```

**After**:
```php
public static function get(string $key, $default = null)
{
    return Cache::remember("setting_{$key}", 3600, function () use ($key, $default) {
        // Query only once per hour
    });
}
```

### ✅ 2. Cache Page Model
**File**: `app/Models/Page.php`

- Added caching to `Page::getBySlug()` method (1 hour TTL)
- Cache invalidates automatically when pages are updated
- **Impact**: Eliminates 1 database query per page load

### ✅ 3. Pre-load Settings in Controller
**File**: `app/Http/Controllers/HomeController.php`

- Load all settings used in view upfront (single cache lookup)
- Pass settings array to view instead of calling `Setting::get()` in view
- **Impact**: Reduces view processing time

### ✅ 4. Optimize CDN Loading
**File**: `resources/views/home.blade.php`

- Added `preconnect` and `dns-prefetch` for CDN domains
- Made Font Awesome CSS load asynchronously (non-blocking)
- Made Tailwind CSS load with `defer`
- Added `loading="eager"` to logo image (LCP element)
- **Impact**: Faster CDN resource loading, non-blocking render

### ✅ 5. Add Header ID
**File**: `resources/views/home.blade.php`

- Added `id="home"` to header element (matches LCP element)
- Ensures proper identification for performance monitoring

## Performance Improvements

### Before:
- **Database Queries**: 4+ queries per page load
- **LCP**: 11.44 seconds
- **CDN Loading**: Blocking render

### After:
- **Database Queries**: 0 queries (all cached)
- **LCP**: Expected < 2.5 seconds (good)
- **CDN Loading**: Non-blocking, optimized

## Additional Recommendations

### 1. Use Cloudflare for Static Assets
- Enable Cloudflare caching for CSS/JS/images
- Use Cloudflare CDN for faster global delivery

### 2. Optimize Images
- Compress logo image
- Use WebP format if possible
- Add width/height attributes to prevent layout shift

### 3. Consider Self-Hosting Tailwind
- Instead of CDN, compile Tailwind CSS and serve locally
- Reduces external dependency

### 4. Enable HTTP/2 Server Push
- Push critical CSS/JS resources
- Faster initial page load

## Testing

After deployment, test LCP:
```bash
# Use Chrome DevTools Lighthouse
# Or PageSpeed Insights: https://pagespeed.web.dev/
```

Expected improvements:
- LCP: 11.44s → < 2.5s (good)
- Database queries: 4+ → 0
- Page load time: Significantly faster

## Cache Management

### Clear Cache When Needed:
```bash
# Clear all caches
php artisan cache:clear

# Clear specific setting cache
Cache::forget('setting_site_logo');
```

### Cache Invalidation:
- Settings cache invalidates automatically when updated
- Page cache invalidates automatically when updated
- No manual cache clearing needed
