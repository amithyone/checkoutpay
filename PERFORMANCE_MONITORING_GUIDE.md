# Performance Monitoring Guide

## Overview

The performance monitoring system automatically logs slow requests and provides tools to analyze bottlenecks.

## What Gets Logged

The `PerformanceMonitor` middleware automatically logs:

1. **Slow Requests** (> 500ms):
   - Request duration
   - Database query count
   - Total query time
   - Memory usage
   - Slow queries (> 100ms)
   - URL and route information

2. **Account Assignment Endpoints** (always logged):
   - All `/payment-request` endpoints
   - All `/checkout` endpoints
   - All `/api/v1/payment` endpoints

3. **Very Slow Requests** (> 2000ms):
   - Logged as ERROR level
   - Includes full query details

## Log Levels

- **ERROR**: Requests > 2000ms
- **WARNING**: Requests > 1000ms
- **INFO**: Requests > 500ms or with slow queries

## Viewing Logs

### Real-time Monitoring

```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log | grep "slow_request\|Account assignment"

# Filter for slow requests only
tail -f storage/logs/laravel.log | grep -E "slow_request|Slow request|Very slow"
```

### Analyze Slow Requests

```bash
# Analyze slow requests from last 24 hours
php artisan performance:analyze-slow

# Analyze from last 12 hours
php artisan performance:analyze-slow --hours=12

# Show only very slow requests (> 1000ms)
php artisan performance:analyze-slow --min-duration=1000

# Show top 50 slowest requests
php artisan performance:analyze-slow --top=50

# Combined options
php artisan performance:analyze-slow --hours=48 --min-duration=500 --top=30
```

## Example Output

```
Analyzing slow requests from last 24 hours...
Minimum duration: 500ms

Found 15 slow requests. Showing top 20:

+----------------+----------+-----------------+-------------+--------+--------------------------------------------------+
| Duration (ms)  | Queries  | Query Time (ms) | Memory (MB) | Method | URL                                               |
+----------------+----------+-----------------+-------------+--------+--------------------------------------------------+
| 2345.67        | 45       | 1234.56         | 12.34       | POST   | https://check-outpay.com/api/v1/payment-request  |
| 1234.56        | 23       | 567.89          | 8.90        | POST   | https://check-outpay.com/api/v1/payment-request  |
+----------------+----------+-----------------+-------------+--------+--------------------------------------------------+

Slow Query Analysis:
Total slow queries found: 8

Top Slow Queries:
+-------+-----------------+---------------+---------------+--------------------------------------------------------------+
| Count | Total Time (ms) | Max Time (ms) | Avg Time (ms) | SQL                                                          |
+-------+-----------------+---------------+---------------+--------------------------------------------------------------+
| 3     | 450.23          | 234.56        | 150.08        | select * from payments where status = ? and account_number... |
+-------+-----------------+---------------+---------------+--------------------------------------------------------------+

Summary Statistics:
Total slow requests: 15
Average duration: 856.78ms
Maximum duration: 2345.67ms
Average queries per request: 28.5
Total slow queries: 8
```

## What to Look For

### 1. High Query Count
- **Problem**: > 50 queries per request
- **Solution**: Add eager loading, use caching, optimize queries

### 2. Slow Queries (> 100ms)
- **Problem**: Individual queries taking too long
- **Solution**: Add database indexes, optimize query structure

### 3. High Memory Usage
- **Problem**: > 50MB per request
- **Solution**: Reduce data loaded, use pagination, optimize collections

### 4. Long Duration
- **Problem**: Request takes > 1000ms
- **Solution**: Check query count, slow queries, external API calls

## Log Format

### Slow Request Log Example:
```json
{
  "type": "slow_request",
  "method": "POST",
  "url": "https://check-outpay.com/api/v1/payment-request",
  "route": "api.payment.store",
  "duration_ms": 1234.56,
  "memory_mb": 8.90,
  "query_count": 23,
  "total_query_time_ms": 567.89,
  "avg_query_time_ms": 24.69,
  "slow_queries": [
    {
      "sql": "select * from payments where status = ? and account_number = ?",
      "bindings": ["pending", "1234567890"],
      "time": "234.56ms"
    }
  ],
  "ip": "192.168.1.1",
  "user_agent": "Mozilla/5.0...",
  "timestamp": "2026-01-26 20:00:00"
}
```

### Account Assignment Log Example:
```json
{
  "type": "account_assignment",
  "method": "POST",
  "url": "https://check-outpay.com/api/v1/payment-request",
  "duration_ms": 45.23,
  "query_count": 2,
  "total_query_time_ms": 12.34,
  "memory_mb": 2.45,
  "slow_queries": []
}
```

## Troubleshooting

### If logs are too verbose:

1. **Increase threshold** (edit middleware):
   ```php
   // Change from 500ms to 1000ms
   $isSlow = $duration > 1000 || !empty($slowQueries) || $queryCount > 50;
   ```

2. **Disable for specific routes** (edit middleware):
   ```php
   protected function shouldSkipLogging(Request $request): bool
   {
       // Add routes to skip
       if (str_contains($request->path(), 'admin/dashboard')) {
           return true;
       }
       // ... existing code
   }
   ```

### If logs are missing:

1. Check log file permissions:
   ```bash
   chmod -R 775 storage/logs
   ```

2. Check Laravel log configuration:
   ```bash
   php artisan config:show logging
   ```

3. Verify middleware is registered:
   ```bash
   php artisan route:list | grep payment-request
   ```

## Best Practices

1. **Monitor regularly**: Run analysis daily
2. **Set alerts**: Monitor ERROR level logs
3. **Track trends**: Compare performance over time
4. **Focus on top issues**: Fix slowest requests first
5. **Optimize queries**: Add indexes for slow queries

## Performance Targets

- **Account Assignment**: < 100ms
- **Payment Creation**: < 200ms
- **General API**: < 500ms
- **Query Count**: < 20 per request
- **Memory Usage**: < 20MB per request

## Next Steps

After identifying bottlenecks:

1. **Slow Queries**: Add database indexes
2. **High Query Count**: Use eager loading, caching
3. **Memory Issues**: Optimize data loading
4. **External APIs**: Add caching, increase timeouts
