# Deployment Commands

This file contains all the commands needed to deploy and update the application on your server.

## Initial Setup

```bash
# 1. Clone/Pull latest code
git pull

# 2. Install/Update dependencies (if composer.json changed)
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php artisan migrate --force

# 4. Run seeders (creates GTBank template if it doesn't exist)
php artisan db:seed --force

# 5. Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 6. Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Update GTBank Template

If you need to update the GTBank template with new extraction patterns:

```bash
# Update existing GTBank template in database
php artisan template:update-gtbank
```

Or if the template doesn't exist yet:

```bash
# Run GTBank template seeder
php artisan db:seed --class=GtbankTemplateSeeder --force
```

## After Code Updates

```bash
# 1. Pull latest changes
git pull

# 2. Run new migrations
php artisan migrate --force

# 3. Run seeders (safe to run multiple times)
php artisan db:seed --force

# 4. Clear caches
php artisan cache:clear
php artisan config:clear

# 5. Re-optimize if needed
php artisan config:cache
php artisan route:cache
```

## Email Processing Commands

```bash
# Read emails directly from filesystem (bypasses IMAP)
php artisan payment:read-emails-direct --all

# Monitor emails via IMAP
php artisan payment:monitor-emails

# Expire old payments
php artisan payment:expire
```

## Troubleshooting

```bash
# Check application status
php artisan about

# Check queue status
php artisan queue:work --once

# View logs
tail -f storage/logs/laravel.log

# Test email connection
php artisan tinker
>>> $account = \App\Models\EmailAccount::first();
>>> $account->testConnection();
```

## Database Maintenance

```bash
# Backup database (adjust connection as needed)
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# Check table sizes
php artisan tinker
>>> DB::select("SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb FROM information_schema.TABLES WHERE table_schema = DATABASE() ORDER BY size_mb DESC");
```
