# Create Laravel Database (Following WordPress Pattern)

## ✅ WordPress Database Works!

Since WordPress database works, we know:
- MySQL is working correctly
- Host `localhost` works
- Database format: `checzspw_dbname`
- Username format: `checzspw_dbname` (same as database)

## 🎯 Solution: Create New Database for Laravel

### Step 1: Create Database in cPanel

1. Go to **cPanel → MySQL Databases**
2. Under **Create New Database**:
   - Database name: `checkout` (will become `checzspw_checkout`)
   - Click **Create Database**

### Step 2: Create Database User

1. Still in **MySQL Databases**
2. Under **Add New User**:
   - Username: `checkout` (will become `checzspw_checkout`)
   - Password: Generate a strong password (or use simple one for testing)
   - Click **Create User**

### Step 3: Add User to Database

1. Scroll to **Add User To Database**
2. Select:
   - User: `checzspw_checkout`
   - Database: `checzspw_checkout`
3. Click **Add**
4. **IMPORTANT:** Check **ALL PRIVILEGES**
5. Click **Make Changes**

### Step 4: Update .env File

Edit your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=checzspw_checkout
DB_USERNAME=checzspw_checkout
DB_PASSWORD=your_password_here
```

**Important:**
- Use `localhost` (same as WordPress)
- Database name format: `checzspw_checkout`
- Username format: `checzspw_checkout` (same as database)
- Password: The one you set when creating user
- NO spaces around `=`
- NO quotes around password

### Step 5: Test Connection

```bash
cd /home/checzspw/public_html

# Clear config
php artisan config:clear

# Test connection
php artisan db:show
```

### Step 6: If Still Not Working - Check Existing Database

If you already created `checzspw_checkout` database:

1. Go to **cPanel → MySQL Databases**
2. Under **Current Users**, find `checzspw_checkout`
3. Click **Change Password**
4. Set a new password
5. Scroll to **Add User To Database**
6. Make sure `checzspw_checkout` user is added to `checzspw_checkout` database
7. Make sure **ALL PRIVILEGES** is checked

## 🔍 Compare with Working WordPress Config

**WordPress (Working):**
```php
DB_NAME: 'checzspw_wp869'
DB_USER: 'checzspw_wp869'
DB_PASSWORD: '4SEN]Y60p!93R)[)'
DB_HOST: 'localhost'
```

**Laravel (Should be):**
```env
DB_DATABASE=checzspw_checkout
DB_USERNAME=checzspw_checkout
DB_PASSWORD=your_password
DB_HOST=localhost
```

**Key Points:**
- ✅ Same host format: `localhost`
- ✅ Same naming pattern: `checzspw_dbname`
- ✅ Username = Database name
- ✅ Password can have special characters

## 🚨 If Password Has Special Characters

If your password has special characters like `!@#$%^&*()`, make sure:

1. **No quotes** in `.env`:
```env
# ✅ CORRECT
DB_PASSWORD=4SEN]Y60p!93R)[)

# ❌ WRONG
DB_PASSWORD="4SEN]Y60p!93R)[)"
```

2. **No spaces**:
```env
# ✅ CORRECT
DB_PASSWORD=4SEN]Y60p!93R)[)

# ❌ WRONG
DB_PASSWORD = 4SEN]Y60p!93R)[)
```

## 🎯 Quick Test Script

```bash
cd /home/checzspw/public_html

# 1. Test MySQL connection directly (like WordPress does)
mysql -u checzspw_checkout -p -h localhost checzspw_checkout
# Enter password - if this works, credentials are correct

# 2. Clear Laravel config
php artisan config:clear

# 3. Test Laravel connection
php artisan db:show
```

## ✅ Success Checklist

- [ ] Database created: `checzspw_checkout`
- [ ] User created: `checzspw_checkout`
- [ ] User added to database with **ALL PRIVILEGES**
- [ ] `.env` updated with correct credentials
- [ ] `DB_HOST=localhost` (same as WordPress)
- [ ] No spaces or quotes in `.env`
- [ ] Config cache cleared
- [ ] MySQL connection works manually
- [ ] Laravel connection works

## 🔧 Alternative: Use WordPress Database (Not Recommended)

If you want to test quickly, you could temporarily use WordPress database:

```env
DB_DATABASE=checzspw_wp869
DB_USERNAME=checzspw_wp869
DB_PASSWORD=4SEN]Y60p!93R)[)
DB_HOST=localhost
```

**But create a separate database for Laravel for production!**

---

**Follow the WordPress pattern exactly and it should work!** 🎯
