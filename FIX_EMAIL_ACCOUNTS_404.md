# Fix Email Accounts 404 Error

## ✅ Changes Made

1. ✅ Added Email Accounts link to navigation menu
2. ✅ Routes are properly registered in `routes/admin.php`
3. ✅ Controller exists: `app/Http/Controllers/Admin/EmailAccountController.php`

## 🔧 If Still Getting 404

### Step 1: Pull Latest Changes

```bash
cd /home/checzspw/public_html
git pull origin main
```

### Step 2: Run Migrations

```bash
# Run new migrations for email_accounts table
php artisan migrate --force
```

### Step 3: Clear All Caches

```bash
# Clear route cache
php artisan route:clear

# Clear config cache
php artisan config:clear

# Clear view cache
php artisan view:clear

# Clear application cache
php artisan cache:clear
```

### Step 4: Rebuild Caches (Optional)

```bash
# Rebuild route cache
php artisan route:cache

# Rebuild config cache
php artisan config:cache
```

### Step 5: Verify Routes

```bash
# Check if email-accounts route exists
php artisan route:list | grep email-accounts
```

Should show:
```
GET|HEAD  admin/email-accounts ................ admin.email-accounts.index › EmailAccountController@index
POST      admin/email-accounts ................ admin.email-accounts.store › EmailAccountController@store
GET|HEAD  admin/email-accounts/create ......... admin.email-accounts.create › EmailAccountController@create
GET|HEAD  admin/email-accounts/{emailAccount} . admin.email-accounts.show › EmailAccountController@show
PUT|PATCH admin/email-accounts/{emailAccount} . admin.email-accounts.update › EmailAccountController@update
DELETE    admin/email-accounts/{emailAccount} . admin.email-accounts.destroy › EmailAccountController@destroy
```

### Step 6: Check File Permissions

```bash
# Ensure files are readable
chmod -R 755 app/Http/Controllers/Admin/EmailAccountController.php
chmod -R 755 resources/views/admin/email-accounts/
```

## 🎯 Quick Fix Script

Run this on your server:

```bash
cd /home/checzspw/public_html

# Pull changes
git pull origin main

# Run migrations
php artisan migrate --force

# Clear all caches
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan cache:clear

# Verify route
php artisan route:list | grep email-accounts
```

## 📍 Navigation Location

The Email Accounts link should now appear in the sidebar navigation:
- **Position:** Between Dashboard and Account Numbers
- **Icon:** Envelope icon (📧)
- **Route:** `/admin/email-accounts`

---

**After clearing caches, the Email Accounts page should be accessible!** 🚀
