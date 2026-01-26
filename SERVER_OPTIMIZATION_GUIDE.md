# Server Optimization Guide for check-outnow.com

## Overview
This guide provides server-level optimizations to maximize performance for check-outnow.com on a fast server.

## ✅ Optimizations Applied

### 1. HTTP Response Caching
**Files Modified:**
- `app/Http/Middleware/CacheResponse.php` (NEW)
- `app/Http/Controllers/HomeController.php`
- `app/Http/Kernel.php`

**What it does:**
- Adds `Cache-Control` headers to static pages
- Browser/CDN caches pages for 1 hour (homepage) or 30 minutes (other pages)
- Reduces server load by serving cached content

**Cache Headers Added:**
```
Cache-Control: public, max-age=3600, s-maxage=3600
Expires: [1 hour from now]
Vary: Accept-Encoding
```

### 2. Cache Warm-Up on Boot
**Files Modified:**
- `app/Providers/AppServiceProvider.php`

**What it does:**
- Pre-loads critical caches when application starts
- Ensures first request is fast (no cache miss)
- Runs automatically in production environment

**Caches Pre-loaded:**
- Homepage page data (`page_home`)
- Critical settings (`site_favicon`, `site_logo`, `site_name`)
- Pool accounts list (if available)

### 3. Extended Cache TTLs
**Already Applied:**
- Account number service: 300 seconds (5 minutes)
- Settings: 86400 seconds (24 hours)
- Pages: 86400 seconds (24 hours)

## Server Configuration Recommendations

### 1. PHP OPcache Configuration
Edit `/etc/php/8.x/fpm/php.ini` or `/etc/php/8.x/cli/php.ini`:

```ini
; Enable OPcache
opcache.enable=1
opcache.enable_cli=1

; Memory settings (adjust based on your server RAM)
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000

; Performance settings
opcache.validate_timestamps=0  ; Set to 0 in production (requires restart to clear cache)
opcache.revalidate_freq=0
opcache.fast_shutdown=1

; Preload (PHP 7.4+)
opcache.preload=/var/www/checkout/config/opcache-preload.php
```

### 2. PHP-FPM Configuration
Edit `/etc/php/8.x/fpm/pool.d/www.conf`:

```ini
; Process management
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; Performance tuning
pm.process_idle_timeout = 10s
request_terminate_timeout = 60s
```

### 3. Nginx Configuration
Add to your Nginx server block for check-outnow.com:

```nginx
# Enable gzip compression
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;

# Cache static assets
location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    access_log off;
}

# FastCGI cache for dynamic pages (optional but recommended)
fastcgi_cache_path /var/cache/nginx levels=1:2 keys_zone=CHECKOUT:100m inactive=60m;
fastcgi_cache_key "$scheme$request_method$host$request_uri";

server {
    # ... existing config ...
    
    # Enable FastCGI cache
    set $skip_cache 0;
    
    # Don't cache POST requests or authenticated requests
    if ($request_method = POST) {
        set $skip_cache 1;
    }
    if ($http_authorization != "") {
        set $skip_cache 1;
    }
    
    location ~ \.php$ {
        # ... existing fastcgi config ...
        
        fastcgi_cache CHECKOUT;
        fastcgi_cache_valid 200 60m;
        fastcgi_cache_bypass $skip_cache;
        fastcgi_no_cache $skip_cache;
        add_header X-FastCGI-Cache $upstream_cache_status;
    }
}
```

### 4. MySQL Configuration
Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
# Connection settings
max_connections = 200
max_connect_errors = 10000

# Query cache (MySQL 5.7 and earlier)
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# InnoDB settings
innodb_buffer_pool_size = 1G  # Adjust based on available RAM (50-70% of RAM)
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2  # Better performance, slight risk on crash
innodb_flush_method = O_DIRECT

# Table cache
table_open_cache = 4000
table_definition_cache = 2000

# Temporary tables
tmp_table_size = 64M
max_heap_table_size = 64M
```

**After MySQL changes, restart MySQL:**
```bash
sudo systemctl restart mysql
```

### 5. Redis Configuration (If Using Redis Cache)
Edit `/etc/redis/redis.conf`:

```conf
# Memory settings
maxmemory 256mb
maxmemory-policy allkeys-lru

# Persistence (for production)
save 900 1
save 300 10
save 60 10000

# Performance
tcp-backlog 511
timeout 0
tcp-keepalive 300
```

**Update Laravel `.env` to use Redis:**
```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 6. System Limits
Edit `/etc/security/limits.conf`:

```
www-data soft nofile 65535
www-data hard nofile 65535
```

Edit `/etc/sysctl.conf`:

```conf
# Network performance
net.core.somaxconn = 65535
net.ipv4.tcp_max_syn_backlog = 65535

# File descriptors
fs.file-max = 2097152
```

Apply changes:
```bash
sudo sysctl -p
```

## Deployment Steps

### 1. Deploy Code Changes
```bash
cd /var/www/checkout
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan cache:clear
php artisan cache:warm
```

### 2. Apply Server Configurations
```bash
# Restart PHP-FPM
sudo systemctl restart php8.x-fpm

# Restart Nginx
sudo systemctl restart nginx

# Restart MySQL (if config changed)
sudo systemctl restart mysql

# Restart Redis (if using Redis)
sudo systemctl restart redis
```

### 3. Verify Optimizations
```bash
# Check PHP OPcache status
php -r "var_dump(opcache_get_status());"

# Test cache warm-up
php artisan cache:warm

# Check performance
php artisan performance:diagnose
```

## Performance Monitoring

### 1. Monitor Cache Hit Rates
```bash
# Check Laravel cache stats
php artisan tinker
>>> Cache::getStore()->getRedis()->info('stats')
```

### 2. Monitor Slow Queries
```bash
# Enable MySQL slow query log
mysql -e "SET GLOBAL slow_query_log = 'ON'; SET GLOBAL long_query_time = 1;"

# View slow queries
tail -f /var/log/mysql/slow-query.log
```

### 3. Monitor Application Performance
```bash
# Analyze slow requests
php artisan performance:analyze-slow --hours=24 --min-duration=500

# Diagnose overall performance
php artisan performance:diagnose
```

## Expected Performance Improvements

### Before Optimizations:
- First request: 500-2000ms
- Subsequent requests: 200-500ms
- Cache expiration: Every 60 seconds

### After Optimizations:
- First request: 100-300ms (cache warm-up)
- Subsequent requests: 50-150ms (HTTP cache + application cache)
- Cache expiration: Every 5 minutes (account numbers) or 24 hours (settings/pages)

## Troubleshooting

### Issue: Cache not working
**Solution:**
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Warm up cache
php artisan cache:warm

# Check cache driver
php artisan tinker
>>> config('cache.default')
```

### Issue: Still slow after optimizations
**Check:**
1. Server resources: `htop`, `free -h`, `df -h`
2. Database connections: `mysql -e "SHOW PROCESSLIST;"`
3. PHP-FPM status: `systemctl status php8.x-fpm`
4. Nginx error logs: `tail -f /var/log/nginx/error.log`

### Issue: HTTP cache headers not appearing
**Solution:**
- Check middleware is registered in `app/Http/Kernel.php`
- Verify route is not authenticated (cache middleware skips authenticated users)
- Check Nginx is not stripping headers

## Summary

With these optimizations:
- ✅ HTTP response caching reduces server load
- ✅ Cache warm-up ensures fast first request
- ✅ Extended cache TTLs reduce database queries
- ✅ Server-level optimizations maximize performance

**Result:** Site should be fast on first click and remain fast for extended periods.
