# Database Setup Guide - MySQL

## 🗄️ Switching from SQLite to MySQL

This guide will help you set up MySQL database for the Email Payment Gateway.

## 📋 Prerequisites

1. **MySQL Server** installed and running
   - Download: https://dev.mysql.com/downloads/mysql/
   - Or use XAMPP/WAMP/MAMP which includes MySQL

2. **PHP MySQL Extension** enabled
   - Usually enabled by default in PHP 8.1+

## 🚀 Setup Steps

### Step 1: Create MySQL Database

**Option A: Using MySQL Command Line**
```bash
mysql -u root -p
```

Then run:
```sql
CREATE DATABASE payment_gateway CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'payment_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON payment_gateway.* TO 'payment_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**Option B: Using phpMyAdmin**
1. Open phpMyAdmin (usually at http://localhost/phpmyadmin)
2. Click "New" to create database
3. Name: `payment_gateway`
4. Collation: `utf8mb4_unicode_ci`
5. Click "Create"

### Step 2: Configure .env File

Your `.env` file should have:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=payment_gateway
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

**For custom MySQL user:**
```env
DB_USERNAME=payment_user
DB_PASSWORD=your_secure_password
```

### Step 3: Test Database Connection

```bash
php artisan migrate:status
```

If you see "No migrations found" or migration list, connection is working!

### Step 4: Run Migrations

```bash
php artisan migrate
```

This will create all tables:
- `payments`
- `businesses`
- `account_numbers`
- `withdrawal_requests`
- `admins`
- `transaction_logs`
- `jobs`
- `failed_jobs`
- `sessions`
- `cache`
- `cache_locks`

### Step 5: Seed Initial Data

```bash
# Create admin users
php artisan db:seed --class=AdminSeeder

# Create sample pool account numbers
php artisan db:seed --class=AccountNumberSeeder
```

## 🔧 Troubleshooting

### Error: "Access denied for user"

**Solution:**
- Check MySQL username and password in `.env`
- Verify MySQL user has permissions:
```sql
GRANT ALL PRIVILEGES ON payment_gateway.* TO 'your_user'@'localhost';
FLUSH PRIVILEGES;
```

### Error: "Unknown database 'payment_gateway'"

**Solution:**
- Create the database first (see Step 1)
- Or change `DB_DATABASE` in `.env` to existing database name

### Error: "SQLSTATE[HY000] [2002] Connection refused"

**Solution:**
- Make sure MySQL server is running
- Check `DB_HOST` is correct (usually `127.0.0.1` or `localhost`)
- Check `DB_PORT` is correct (usually `3306`)

### Error: "PDOException: could not find driver"

**Solution:**
- Enable MySQL PDO extension in `php.ini`:
```ini
extension=pdo_mysql
extension=mysqli
```
- Restart web server

## 📊 Database Structure

After migrations, you'll have:

- **payments** - Payment requests and status
- **businesses** - Business accounts
- **account_numbers** - Pool and business-specific accounts
- **withdrawal_requests** - Withdrawal requests
- **admins** - Admin users
- **transaction_logs** - Complete audit trail
- **jobs** - Queue jobs
- **sessions** - User sessions
- **cache** - Application cache

## 🔄 Switching Back to SQLite

If you need to switch back to SQLite:

1. Update `.env`:
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

2. Create SQLite file:
```bash
touch database/database.sqlite
```

3. Run migrations:
```bash
php artisan migrate
```

## ✅ Verification

After setup, verify everything works:

```bash
# Check database connection
php artisan db:show

# Check migrations
php artisan migrate:status

# Test admin login
# Go to: http://localhost:8000/admin
# Login: admin@paymentgateway.com / password
```

## 🎯 Production Recommendations

1. **Use Strong Passwords**: Don't use default MySQL root password
2. **Create Dedicated User**: Use a specific user for the application
3. **Limit Permissions**: Grant only necessary permissions
4. **Enable SSL**: Use SSL connections in production
5. **Regular Backups**: Set up automated database backups
6. **Connection Pooling**: Configure connection pooling for better performance

## 📝 Example Production .env

```env
DB_CONNECTION=mysql
DB_HOST=your-db-host.com
DB_PORT=3306
DB_DATABASE=payment_gateway_prod
DB_USERNAME=payment_app_user
DB_PASSWORD=very_secure_password_here
```

---

**That's it!** Your database is now configured for MySQL. 🎉
