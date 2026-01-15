# Laravel Cache Clearing Commands

## Quick Cache Clear (All)
```bash
cd /var/www/checkout && php artisan optimize:clear
```

## Individual Cache Clearing Commands

### Clear Application Cache
```bash
cd /var/www/checkout && php artisan cache:clear
```

### Clear Configuration Cache
```bash
cd /var/www/checkout && php artisan config:clear
```

### Clear Route Cache
```bash
cd /var/www/checkout && php artisan route:clear
```

### Clear View Cache
```bash
cd /var/www/checkout && php artisan view:clear
```

### Clear Compiled Files
```bash
cd /var/www/checkout && php artisan clear-compiled
```

### Optimize (Cache Everything)
```bash
cd /var/www/checkout && php artisan optimize
```

## Complete Reset (Recommended for Production)
```bash
cd /var/www/checkout && \
php artisan cache:clear && \
php artisan config:clear && \
php artisan route:clear && \
php artisan view:clear && \
php artisan config:cache && \
php artisan route:cache && \
php artisan view:cache
```

## For Logo Preview Issues Specifically
```bash
cd /var/www/checkout && \
php artisan config:clear && \
php artisan view:clear && \
php artisan cache:clear
```
