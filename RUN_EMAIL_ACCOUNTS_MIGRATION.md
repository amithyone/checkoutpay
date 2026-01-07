# Run Email Accounts Migration

## 🔴 Error
```
Table 'checzspw_checkout.email_accounts' doesn't exist
```

## ✅ Solution: Run Migrations

The `email_accounts` table needs to be created. Run this on your server:

```bash
cd /home/checzspw/public_html

# Run migrations
php artisan migrate --force
```

This will create:
- `email_accounts` table
- Add `email_account_id` column to `businesses` table

## 🔍 Verify Migration

After running migrations, verify the table exists:

```bash
# Check if table exists
php artisan db:show

# Or check directly
mysql -u checzspw_checkout -p checzspw_checkout -e "SHOW TABLES LIKE 'email_accounts';"
```

## 🎯 Quick Fix

Run this complete command sequence:

```bash
cd /home/checzspw/public_html

# Pull latest changes (if not already done)
git pull origin main

# Run migrations
php artisan migrate --force

# Clear caches
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

## ✅ Expected Result

After running migrations, you should see:
```
Migrating: 2024_01_05_000001_create_email_accounts_table
Migrated:  2024_01_05_000001_create_email_accounts_table (XX.XXms)
Migrating: 2024_01_05_000002_add_email_account_id_to_businesses_table
Migrated:  2024_01_05_000002_add_email_account_id_to_businesses_table (XX.XXms)
```

Then the Email Accounts page should work! 🚀
