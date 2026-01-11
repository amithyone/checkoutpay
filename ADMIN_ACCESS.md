# How to Access Admin Panel

## 🚪 Admin Panel Access

### URL
```
https://check-outpay.com/admin
```

## 🔐 Default Login Credentials

After running the seeder, use these credentials:

**Email:** `admin@paymentgateway.com`  
**Password:** `password`

⚠️ **IMPORTANT:** Change this password immediately after first login!

## 📋 Steps to Access

### Step 1: Make Sure Setup is Complete

If you haven't run migrations and seeders yet:

```bash
cd /home/checzspw/public_html

# Run migrations
php artisan migrate --force

# Run seeders (creates admin user)
php artisan db:seed --class=AdminSeeder --force
```

### Step 2: Access Admin Panel

1. **Visit:** `https://check-outpay.com/admin`
2. **Login with:**
   - Email: `admin@paymentgateway.com`
   - Password: `password`

### Step 3: Change Password

After logging in, go to your profile settings and change the password!

## 🔧 If You Can't Access

### Issue 1: Setup Not Complete

If you see an error or redirect to setup:
- Visit: `https://check-outpay.com/setup`
- Complete the database configuration
- Run migrations and seeders

### Issue 2: Admin User Doesn't Exist

Create admin user manually:

```bash
php artisan tinker
```

Then in tinker:
```php
$admin = new App\Models\Admin();
$admin->name = 'Super Admin';
$admin->email = 'admin@paymentgateway.com';
$admin->password = bcrypt('password');
$admin->role = 'super_admin';
$admin->is_active = true;
$admin->save();
exit
```

### Issue 3: Forgot Password

Reset password via tinker:

```bash
php artisan tinker
```

```php
$admin = App\Models\Admin::where('email', 'admin@paymentgateway.com')->first();
$admin->password = bcrypt('newpassword');
$admin->save();
exit
```

## 📍 Admin Routes

- **Login:** `/admin/login`
- **Dashboard:** `/admin`
- **Payments:** `/admin/payments`
- **Businesses:** `/admin/businesses`
- **Account Numbers:** `/admin/account-numbers`
- **Withdrawals:** `/admin/withdrawals`
- **Transaction Logs:** `/admin/transaction-logs`

## 🎯 Quick Access

1. Go to: `https://check-outpay.com/admin`
2. Login with default credentials
3. Change password immediately
4. Start managing your payment gateway!

---

**Default credentials are for initial setup only - change them immediately!** 🔐
