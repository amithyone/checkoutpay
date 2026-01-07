# Use Setup Wizard to Fix Database Connection

## 🔴 Current Error
```
Access denied for user 'checzspw_checkout'@'localhost' (using password: YES)
```

The database credentials in `.env` are still incorrect. Use the setup wizard to fix this!

## ✅ Solution: Use Setup Wizard

### Step 1: Access Setup Wizard

Visit:
```
http://check-outpay.com/setup
```

### Step 2: Fill in Database Credentials

In the setup form, enter:

- **Host:** `localhost`
- **Port:** `3306`
- **Database:** `checzspw_checkout`
- **Username:** `checzspw_checkout`
- **Password:** Your actual database password

### Step 3: Test Connection

1. Click **"Test Connection"** button
2. Wait for result:
   - ✅ **Green message** = Connection works!
   - ❌ **Red message** = Check credentials

### Step 4: Save & Complete Setup

1. If test is successful, click **"Save & Continue"**
2. The wizard will:
   - Save credentials to `.env` file
   - Run migrations automatically
   - Run seeders automatically
   - Redirect to admin panel

## 🔧 If Setup Wizard Doesn't Work

### Manual Fix: Update .env File

```bash
cd /home/checzspw/public_html

# Edit .env file
nano .env
```

Make sure database section looks exactly like this (NO spaces around =):

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=checzspw_checkout
DB_USERNAME=checzspw_checkout
DB_PASSWORD=your_actual_password_here
```

**Critical:**
- No spaces around `=`
- No quotes around password
- Use `localhost` not `127.0.0.1`
- Password exactly as set in cPanel

### Then Clear Config

```bash
php artisan config:clear
php artisan config:cache
```

### Test Database Connection

```bash
# Test MySQL directly
mysql -u checzspw_checkout -p -h localhost checzspw_checkout
# Enter password - if this works, credentials are correct

# Test Laravel connection
php artisan db:show
```

## 🎯 Quick Checklist

- [ ] Access setup wizard: `http://check-outpay.com/setup`
- [ ] Enter database credentials
- [ ] Test connection (should show ✅)
- [ ] Save & Continue
- [ ] Wait for migrations to complete
- [ ] Redirected to admin panel
- [ ] Login with: `admin@paymentgateway.com` / `password`

## 🚨 If Password Still Doesn't Work

1. **Reset password in cPanel:**
   - Go to cPanel → MySQL Databases
   - Find user `checzspw_checkout`
   - Click **Change Password**
   - Set a **simple password** (no special characters)
   - Copy the password

2. **Use setup wizard again** with new password

3. **Or update .env manually** with new password

---

**Use the setup wizard - it will test and save credentials correctly!** 🎯
