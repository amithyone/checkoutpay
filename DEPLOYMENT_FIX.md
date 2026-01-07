# Fix: Missing Vendor Folder Error

## 🔴 Error
```
Failed to open stream: No such file or directory in /home/checzspw/public_html/public/index.php
```

## ✅ Solution

The `vendor` folder is missing. You need to install Composer dependencies on your server.

### Option 1: Run Composer Install on Server (Recommended)

**Via SSH:**
```bash
# Connect to your server via SSH
ssh your-username@your-server.com

# Navigate to your project directory
cd /home/checzspw/public_html

# Install dependencies
composer install --no-dev --optimize-autoloader
```

**Via cPanel Terminal:**
1. Go to cPanel → **Terminal**
2. Navigate to your project:
```bash
cd public_html
```
3. Run:
```bash
composer install --no-dev --optimize-autoloader
```

### Option 2: Upload Vendor Folder

If you can't run composer on server:

1. **On your local machine**, run:
```bash
composer install --no-dev --optimize-autoloader
```

2. **Upload vendor folder** to server:
   - Upload entire `vendor` folder to `/home/checzspw/public_html/vendor`
   - This is a large folder (100+ MB), use FTP/SFTP

### Option 3: Use Git Deployment

If you have Git access on server:

```bash
cd /home/checzspw/public_html
git pull origin main
composer install --no-dev --optimize-autoloader
```

## 📋 Complete Deployment Checklist

### 1. Upload All Files
Make sure these folders/files are uploaded:
- ✅ `app/` folder
- ✅ `bootstrap/` folder
- ✅ `config/` folder
- ✅ `database/` folder
- ✅ `public/` folder
- ✅ `resources/` folder
- ✅ `routes/` folder
- ✅ `storage/` folder
- ✅ `vendor/` folder (or install via composer)
- ✅ `.env` file
- ✅ `artisan` file
- ✅ `composer.json` file

### 2. Set Permissions
```bash
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 3. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 4. Generate App Key
```bash
php artisan key:generate
```

### 5. Run Migrations
```bash
php artisan migrate --force
```

### 6. Seed Data
```bash
php artisan db:seed --class=AdminSeeder --force
php artisan db:seed --class=AccountNumberSeeder --force
```

### 7. Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 8. Optimize for Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 🚨 Important Notes

1. **Don't upload `.env`** - Create it on server with production values
2. **Upload `vendor`** or run `composer install` on server
3. **Set correct permissions** on `storage` and `bootstrap/cache`
4. **Update `.env`** with production database credentials
5. **Ensure `public` folder is document root**

## 🔧 Quick Fix Script

Create `deploy.sh` on server:

```bash
#!/bin/bash
cd /home/checzspw/public_html
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

Run it:
```bash
chmod +x deploy.sh
./deploy.sh
```

## 📞 Still Having Issues?

Check:
1. ✅ PHP version >= 8.1
2. ✅ Composer installed on server
3. ✅ All files uploaded correctly
4. ✅ `.env` file exists and configured
5. ✅ Database created and accessible
6. ✅ Permissions set correctly
