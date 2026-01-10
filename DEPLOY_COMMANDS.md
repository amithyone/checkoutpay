# Deployment Commands

## Quick Deploy Script

Run these commands on your server to deploy the latest changes:

```bash
# Navigate to project directory
cd /home/checzspw/public_html

# Pull latest changes from git
git pull origin main

# Run migrations
php artisan migrate --force

# Run seeders (if any)
php artisan db:seed --force

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize (for production)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Done!
```

## Single Line Command (Copy & Paste)

```bash
cd /home/checzspw/public_html && git pull origin main && php artisan migrate --force && php artisan db:seed --force && php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && echo "✅ Deployment complete!"
```

## Step by Step (If Errors Occur)

```bash
# 1. Navigate to project
cd /home/checzspw/public_html

# 2. Pull from git
git pull origin main

# 3. Run migrations (creates match_attempts table and updates processed_emails)
php artisan migrate --force

# 4. Run seeders (optional - only if you have seeders)
php artisan db:seed --force

# 5. Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 6. Rebuild caches (for performance)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Verify migrations ran successfully
php artisan migrate:status
```

## Troubleshooting

**If migration fails:**
```bash
# Check migration status
php artisan migrate:status

# Rollback last migration (if needed)
php artisan migrate:rollback --step=1

# Then try again
php artisan migrate --force
```

**If git pull fails:**
```bash
# Check git status
git status

# Reset local changes (careful - this discards local changes)
git reset --hard origin/main

# Then pull again
git pull origin main
```

**If permissions issue:**
```bash
# Fix storage permissions
chmod -R 775 storage bootstrap/cache
chown -R checzspw:checzspw storage bootstrap/cache
```

## What Will Happen

1. ✅ **git pull** - Gets latest code with match_attempts table, MatchAttempt model, MatchAttemptLogger, etc.
2. ✅ **migrate** - Creates `match_attempts` table and adds `last_match_reason`, `match_attempts_count`, `extraction_method` to `processed_emails`
3. ✅ **db:seed** - Runs any seeders (if you have them)
4. ✅ **cache:clear** - Clears application cache
5. ✅ **config:clear** - Clears config cache
6. ✅ **route:clear** - Clears route cache
7. ✅ **view:clear** - Clears compiled views
8. ✅ **config:cache** - Rebuilds config cache (faster)
9. ✅ **route:cache** - Rebuilds route cache (faster)
10. ✅ **view:cache** - Rebuilds view cache (faster)

## After Deployment

Check that migrations ran successfully:
```bash
php artisan migrate:status
```

You should see:
- ✅ `2026_01_20_000001_create_match_attempts_table`
- ✅ `2026_01_20_000002_add_match_reasoning_to_processed_emails_table`

## Verify New Tables

```bash
# Check if match_attempts table exists
php artisan tinker
>>> Schema::hasTable('match_attempts')
>>> Schema::hasColumn('processed_emails', 'last_match_reason')
>>> exit
```
