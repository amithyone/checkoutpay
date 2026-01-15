# Laravel Cache Clearing Commands

## Quick Cache Clear (All)
```bash
cd public_html && php artisan optimize:clear
```

## Individual Cache Clearing Commands

### Clear Application Cache
```bash
cd public_html && php artisan cache:clear
```

### Clear Configuration Cache
```bash
cd public_html && php artisan config:clear
```

### Clear Route Cache
```bash
cd public_html && php artisan route:clear
```

### Clear View Cache
```bash
cd public_html && php artisan view:clear
```

### Clear Compiled Files
```bash
cd public_html && php artisan clear-compiled
```

### Optimize (Cache Everything)
```bash
cd public_html && php artisan optimize
```

## Complete Reset (Recommended for Production)
```bash
cd public_html && \
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
cd public_html && \
php artisan config:clear && \
php artisan view:clear && \
php artisan cache:clear
```
