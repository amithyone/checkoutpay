# Server Performance Diagnostics

## Yes, It Could Be a Server Issue!

After code optimizations, if the site is still slow, check these server-side issues:

## 🔴 Critical Server Checks

### 1. **CPU Usage**
```bash
# Check CPU usage
top
# Or
htop

# Look for:
# - CPU usage > 80% consistently
# - PHP-FPM processes consuming CPU
# - MySQL consuming high CPU
```

**If CPU is high:**
- Reduce PHP-FPM workers
- Optimize database queries (we've done this)
- Consider upgrading server

### 2. **Memory (RAM) Usage**
```bash
# Check memory
free -h

# Check PHP-FPM memory
ps aux | grep php-fpm | awk '{sum+=$6} END {print sum/1024 " MB"}'
```

**If memory is high:**
- Reduce PHP-FPM `pm.max_children`
- Enable OPcache
- Check for memory leaks

### 3. **Database Connection Pool**
```bash
# Check MySQL connections
mysql -e "SHOW PROCESSLIST;"
mysql -e "SHOW STATUS LIKE 'Threads_connected';"
mysql -e "SHOW VARIABLES LIKE 'max_connections';"
```

**If connections are high:**
- Increase `max_connections` in MySQL
- Check for connection leaks
- Use connection pooling

### 4. **Disk I/O**
```bash
# Check disk I/O
iostat -x 1

# Check disk space
df -h

# Check disk speed
dd if=/dev/zero of=/tmp/test bs=1M count=1024 conv=fdatasync
```

**If disk I/O is slow:**
- Use SSD instead of HDD
- Check for disk space issues
- Optimize database (defragment)

### 5. **PHP-FPM Configuration**
```bash
# Check PHP-FPM config
cat /etc/php/8.1/fpm/pool.d/www.conf | grep -E "pm\.|max_children|memory_limit"

# Check active PHP-FPM processes
ps aux | grep php-fpm | wc -l
```

**Common Issues:**
- Too many PHP-FPM workers (exhausting resources)
- Too few PHP-FPM workers (requests queuing)
- Low `memory_limit` causing OOM kills

### 6. **MySQL Performance**
```bash
# Check slow queries
mysql -e "SHOW VARIABLES LIKE 'slow_query_log%';"
mysql -e "SHOW VARIABLES LIKE 'long_query_time';"

# Check MySQL status
mysql -e "SHOW STATUS LIKE 'Slow_queries';"
mysql -e "SHOW STATUS LIKE 'Connections';"
mysql -e "SHOW STATUS LIKE 'Threads_running';"
```

**If MySQL is slow:**
- Enable slow query log
- Check for table locks
- Optimize database indexes
- Increase `innodb_buffer_pool_size`

### 7. **Network Latency**
```bash
# Check network to database
ping your-database-host

# Check DNS resolution
time nslookup check-outpay.com

# Check if database is remote (adds latency)
mysql -e "SHOW VARIABLES LIKE 'hostname';"
```

**If network is slow:**
- Database should be on same server or same datacenter
- Use localhost for database connection
- Check firewall rules

## 🟡 PHP Configuration Issues

### Check PHP Settings:
```bash
php -i | grep -E "memory_limit|max_execution_time|opcache"
```

**Common Issues:**
- `memory_limit` too low (< 128M)
- `max_execution_time` too low (< 30s)
- OPcache not enabled
- Realpath cache too small

### Enable OPcache (Critical for Performance):
```ini
# In php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  # In production
```

## 🟢 Web Server Configuration

### Nginx Configuration:
```nginx
# Check if these are optimized:
fastcgi_read_timeout 60s;
fastcgi_buffering on;
fastcgi_buffer_size 128k;
fastcgi_buffers 4 256k;
```

### Apache Configuration:
```apache
# Check if these are optimized:
Timeout 60
KeepAlive On
MaxKeepAliveRequests 100
KeepAliveTimeout 5
```

## 🔍 Quick Diagnostic Script

Run this to check everything at once:

```bash
#!/bin/bash
echo "=== Server Performance Diagnostics ==="
echo ""
echo "1. CPU Usage:"
top -bn1 | grep "Cpu(s)" | awk '{print $2}'
echo ""
echo "2. Memory Usage:"
free -h
echo ""
echo "3. PHP-FPM Processes:"
ps aux | grep php-fpm | wc -l
echo ""
echo "4. MySQL Connections:"
mysql -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null || echo "Cannot connect to MySQL"
echo ""
echo "5. Disk Space:"
df -h | grep -E "/$|/var"
echo ""
echo "6. PHP Memory Limit:"
php -i | grep memory_limit
echo ""
echo "7. OPcache Status:"
php -i | grep opcache.enable
```

## 📊 Performance Benchmarks

### Expected Values:

| Metric | Good | Warning | Critical |
|--------|------|---------|----------|
| CPU Usage | < 50% | 50-80% | > 80% |
| Memory Usage | < 60% | 60-80% | > 80% |
| PHP-FPM Workers | 10-20 | 5-10 or 20-30 | < 5 or > 30 |
| MySQL Connections | < 50 | 50-100 | > 100 |
| Disk I/O Wait | < 5% | 5-20% | > 20% |
| Response Time | < 200ms | 200-500ms | > 500ms |

## 🛠️ Quick Fixes

### 1. Enable OPcache (Immediate Impact):
```bash
# Edit php.ini
sudo nano /etc/php/8.1/fpm/php.ini

# Add/update:
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm
```

### 2. Optimize PHP-FPM:
```ini
# In /etc/php/8.1/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 10
pm.max_requests = 500
```

### 3. Optimize MySQL:
```ini
# In my.cnf
innodb_buffer_pool_size = 1G  # 50-70% of RAM
max_connections = 200
query_cache_size = 64M
```

### 4. Use Redis for Cache:
```bash
# Install Redis
sudo apt-get install redis-server

# Update .env
CACHE_STORE=redis
```

### 5. Increase PHP Memory:
```ini
# In php.ini
memory_limit = 256M
max_execution_time = 60
```

## 🚨 Red Flags (Server Issues)

If you see these, it's definitely a server issue:

1. **High CPU (> 80%)** - Server is overloaded
2. **High Memory (> 90%)** - Running out of RAM
3. **Many MySQL Connections (> 100)** - Connection pool exhausted
4. **Slow Disk I/O (> 20% wait)** - Disk bottleneck
5. **OPcache disabled** - PHP code not cached
6. **Database on remote server** - Network latency
7. **Low PHP-FPM workers (< 5)** - Requests queuing

## 📝 Next Steps

1. **Run diagnostics**:
   ```bash
   php artisan performance:diagnose
   ```

2. **Check server resources**:
   ```bash
   top
   free -h
   df -h
   ```

3. **Check PHP-FPM**:
   ```bash
   sudo systemctl status php8.1-fpm
   ps aux | grep php-fpm
   ```

4. **Check MySQL**:
   ```bash
   sudo systemctl status mysql
   mysql -e "SHOW PROCESSLIST;"
   ```

5. **Check logs**:
   ```bash
   tail -100 /var/log/nginx/error.log
   tail -100 /var/log/php8.1-fpm.log
   tail -100 storage/logs/laravel.log
   ```

## 💡 Server Upgrade Recommendations

If server resources are the issue:

1. **Minimum Requirements**:
   - CPU: 2 cores
   - RAM: 4GB
   - Disk: SSD, 20GB+
   - Database: Same server or same datacenter

2. **Recommended**:
   - CPU: 4 cores
   - RAM: 8GB
   - Disk: SSD, 50GB+
   - Database: Same server

3. **For High Traffic**:
   - CPU: 8+ cores
   - RAM: 16GB+
   - Disk: NVMe SSD
   - Separate database server
   - Redis for caching
   - CDN for static assets
