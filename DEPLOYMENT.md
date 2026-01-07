# Deployment Guide

## 🚀 Deploying to Live Server

### Common Issue: "Index of" Page

If you see an "Index of" page, your web server is pointing to the wrong directory.

## ✅ Solution

### For Apache (cPanel, Shared Hosting, etc.)

**Option 1: Point Document Root to `public` folder (Recommended)**

1. In cPanel:
   - Go to **File Manager**
   - Navigate to your domain's root directory
   - Look for **Document Root** or **public_html** settings
   - Change it to point to `public` folder

2. Or create `.htaccess` in root (already created):
```apache
RewriteEngine On
RewriteRule ^(.*)$ public/$1 [L]
```

**Option 2: Move files to public_html**

If you can't change document root:
```bash
# Copy public folder contents to public_html
cp -r public/* public_html/

# Update paths in public_html/index.php
# Change: require __DIR__.'/../vendor/autoload.php';
# To: require __DIR__.'/../vendor/autoload.php';
```

### For Nginx

Update your Nginx configuration:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/your/project/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### For Shared Hosting (cPanel)

1. **Upload files** to your hosting account
2. **Set Document Root** to `public` folder:
   - cPanel → **File Manager**
   - Right-click `public` folder → **Change Permissions** → 755
   - Or contact hosting support to change document root

3. **Update `.env`** with production values:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

4. **Set permissions**:
```bash
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### Quick Fix: Create index.php Redirect

If you can't change document root, create `index.php` in root:

```php
<?php
header('Location: public/');
exit;
```

## 🔧 Post-Deployment Checklist

### 1. Environment Configuration
- [ ] Update `.env` with production values
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Update database credentials
- [ ] Update `APP_URL` to your domain

### 2. Generate App Key
```bash
php artisan key:generate
```

### 3. Run Migrations
```bash
php artisan migrate --force
```

### 4. Seed Data
```bash
php artisan db:seed --class=AdminSeeder --force
php artisan db:seed --class=AccountNumberSeeder --force
```

### 5. Set Permissions
```bash
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 6. Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 7. Optimize for Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 8. Setup Queue Worker
- Use supervisor/systemd to keep queue worker running
- Or use hosting's cron jobs

### 9. Setup Scheduler
Add to cron:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## 🐛 Troubleshooting

### Still seeing "Index of" page?

1. **Check document root**: Must point to `public` folder
2. **Check `.htaccess`**: Must exist in `public` folder
3. **Check mod_rewrite**: Must be enabled in Apache
4. **Check file permissions**: `public/index.php` should be readable

### Error: "No application encryption key"

```bash
php artisan key:generate
```

### Error: "SQLSTATE[HY000] [2002] Connection refused"

- Check database credentials in `.env`
- Verify database server is running
- Check firewall settings

### Error: "Permission denied"

```bash
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## 📝 cPanel Specific Steps

1. **Upload Files**:
   - Upload entire project to `public_html` or subdirectory
   - Or use Git to clone repository

2. **Set Document Root**:
   - cPanel → **Domains** → **Manage** → **Document Root**
   - Change to: `/home/username/public_html/public`
   - Or: `/home/username/public_html/your-project/public`

3. **Create Database**:
   - cPanel → **MySQL Databases**
   - Create database and user
   - Update `.env` with credentials

4. **Set Permissions**:
   - File Manager → Right-click `storage` → Permissions → 775
   - Right-click `bootstrap/cache` → Permissions → 775

5. **Run Commands** (via SSH or cPanel Terminal):
```bash
cd /home/username/public_html/your-project
php artisan migrate
php artisan db:seed --class=AdminSeeder
```

## 🔒 Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong database passwords
- [ ] `.env` file not accessible via web
- [ ] HTTPS enabled
- [ ] Strong admin passwords
- [ ] API keys secured
- [ ] File permissions set correctly

## 📞 Need Help?

Common hosting providers:
- **cPanel**: Point document root to `public` folder
- **Plesk**: Change document root in domain settings
- **Cloudways**: Already configured correctly
- **Laravel Forge**: Already configured correctly
- **DigitalOcean**: Configure Nginx/Apache properly

---

**Remember**: The web server MUST point to the `public` folder, not the project root! 🎯
