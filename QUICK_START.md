# Quick Start Guide

## 🚀 Get Started in 5 Minutes

### Step 1: Install Dependencies
```bash
composer install
```

### Step 2: Setup Environment
```bash
# Copy .env.example to .env (already done)
# Edit .env and configure:
# - Database credentials (MySQL)
# - Email credentials (Gmail)
```

### Step 3: Generate App Key
```bash
php artisan key:generate
```

### Step 4: Create MySQL Database

**Using MySQL Command Line:**
```bash
mysql -u root -p
CREATE DATABASE payment_gateway CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

**Or using phpMyAdmin:**
- Go to http://localhost/phpmyadmin
- Create database: `payment_gateway`
- Collation: `utf8mb4_unicode_ci`

### Step 5: Configure Database in .env

Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=payment_gateway
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

### Step 6: Run Migrations
```bash
php artisan migrate
```

### Step 7: Seed Initial Data
```bash
php artisan db:seed --class=AdminSeeder
php artisan db:seed --class=AccountNumberSeeder
```

### Step 8: Configure Gmail (Optional)

Edit `.env`:
```env
EMAIL_USER=your-email@gmail.com
EMAIL_PASSWORD=your-app-password
```

See [GMAIL_SETUP.md](GMAIL_SETUP.md) for detailed Gmail setup.

### Step 9: Start Services

**Terminal 1 - Start Server:**
```bash
php artisan serve
```

**Terminal 2 - Start Queue Worker:**
```bash
php artisan queue:work
```

**Terminal 3 - Start Scheduler:**
```bash
php artisan schedule:work
```

### Step 10: Access Admin Panel

- **URL**: http://localhost:8000/admin
- **Email**: admin@paymentgateway.com
- **Password**: password

⚠️ **Change password immediately!**

## ✅ You're Ready!

- Admin Panel: http://localhost:8000/admin
- API Base URL: http://localhost:8000/api/v1
- Health Check: http://localhost:8000/api/health

## 📚 Next Steps

1. Create your first business in admin panel
2. Add account numbers (pool or business-specific)
3. Test payment flow
4. Configure webhook endpoints

See [SETUP_GUIDE.md](SETUP_GUIDE.md) for detailed instructions.
