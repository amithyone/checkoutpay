# Database Restore Instructions

## Option 1: Upload via SCP/SFTP
1. Upload your `chec.zip` file to: `/var/www/checkout/database/backups/`
2. Then run the restore commands below

## Option 2: Direct SQL Import
If your backup contains a `.sql` file:
1. Extract the SQL file from the zip
2. Upload it to: `/var/www/checkout/database/backups/`
3. Run: `mysql -u checkoutpay_user -p checkoutpay < database/backups/your_backup.sql`

## Option 3: Via Laravel
If you have a SQL dump file, you can restore it using:
```bash
cd /var/www/checkout
mysql -u checkoutpay_user -p'checkoutpay_pass_2024' checkoutpay < database/backups/your_backup.sql
```

## After Restore
Run migrations to ensure all tables are up to date:
```bash
php artisan migrate
```
