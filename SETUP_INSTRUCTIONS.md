# Checkout Project Setup Instructions

## Database Setup

The database `checkoutpay` needs to be created. You can do this in one of two ways:

### Option 1: Using phpMyAdmin (Recommended)
1. Open phpMyAdmin in your browser
2. Click on the "SQL" tab
3. Copy and paste the following SQL command:
   ```sql
   CREATE DATABASE IF NOT EXISTS checkoutpay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. Click "Go" to execute
5. The database will be created and ready for you to upload your data

### Option 2: Using MySQL Command Line
If you have MySQL root password, run:
```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS checkoutpay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## Database Configuration

The `.env` file has been configured with:
- Database Name: `checkoutpay`
- Database Host: `127.0.0.1`
- Database Port: `3306`
- Database User: `root`
- Database Password: (empty - update if needed)

**Important:** If your MySQL root user has a password, update the `DB_PASSWORD` in `/var/www/checkout/.env`

## Next Steps

1. Create the database using one of the methods above
2. Upload your data through phpMyAdmin to the `checkoutpay` database
3. Run migrations (if needed):
   ```bash
   cd /var/www/checkout
   php artisan migrate
   ```
4. Set up your web server to point to `/var/www/checkout/public`

## Project Location
- Project Path: `/var/www/checkout`
- Web Root: `/var/www/checkout/public`

## Notes
- All existing databases remain untouched
- Only the new `checkoutpay` database will be created
- The project is ready for data import through phpMyAdmin
