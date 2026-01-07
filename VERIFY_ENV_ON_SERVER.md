# Verify .env File on Server

## 🔴 Still Getting Access Denied

The password is being read ("using password: YES"), but access is denied. This means:

1. ✅ Laravel is reading `.env` file
2. ✅ Password is being passed
3. ❌ Either password is wrong OR user doesn't have permissions

## ✅ Step-by-Step Fix

### Step 1: Verify .env File on Server

Run this on your server:

```bash
cd /home/checzspw/public_html

# View database section
cat .env | grep DB_
```

Should show:
```
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=checzspw_checkout
DB_USERNAME=checzspw_checkout
DB_PASSWORD=Enter0text@@@#
```

### Step 2: Check for Hidden Characters

```bash
# View exact content (shows hidden chars)
cat -A .env | grep DB_PASSWORD
```

Should show: `DB_PASSWORD=Enter0text@@@#$` (no extra spaces or characters)

### Step 3: Reset Password in cPanel

1. Go to **cPanel → MySQL Databases**
2. Find user `checzspw_checkout`
3. Click **Change Password**
4. Set a **simple password** (no special characters) for testing:
   - Example: `TestPassword123`
5. Copy the password exactly

### Step 4: Update .env with New Password

```bash
nano .env
```

Update:
```env
DB_PASSWORD=TestPassword123
```

**Make sure:**
- No spaces around `=`
- No quotes
- Exact password from cPanel

### Step 5: Verify User Has Database Access

1. Go to **cPanel → MySQL Databases**
2. Scroll to **Add User To Database**
3. Check if `checzspw_checkout` is listed under database `checzspw_checkout`
4. If NOT listed:
   - Select user: `checzspw_checkout`
   - Select database: `checzspw_checkout`
   - Click **Add**
   - Check **ALL PRIVILEGES**
   - Click **Make Changes**

### Step 6: Test MySQL Connection

```bash
# Test with new password
mysql -u checzspw_checkout -p -h localhost checzspw_checkout
# Enter password: TestPassword123
# If this works, credentials are correct!
```

### Step 7: Clear Laravel Config

```bash
cd /home/checzspw/public_html

# Clear all caches
php artisan config:clear
php artisan cache:clear

# Rebuild config
php artisan config:cache

# Test Laravel connection
php artisan db:show
```

### Step 8: If Still Not Working - Check User Permissions

Run this in MySQL:

```bash
mysql -u checzspw_checkout -p -h localhost checzspw_checkout
```

Then in MySQL prompt:

```sql
-- Check current user
SELECT USER(), CURRENT_USER();

-- Check permissions
SHOW GRANTS FOR 'checzspw_checkout'@'localhost';

-- If no grants, grant permissions
GRANT ALL PRIVILEGES ON checzspw_checkout.* TO 'checzspw_checkout'@'localhost';
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

## 🎯 Quick Test Script

Run this complete test:

```bash
cd /home/checzspw/public_html

# 1. View current .env
echo "=== Current .env DB settings ==="
cat .env | grep DB_

# 2. Test MySQL directly
echo "=== Testing MySQL connection ==="
mysql -u checzspw_checkout -p -h localhost checzspw_checkout <<EOF
SELECT 'Connection successful!' AS status;
EXIT;
EOF

# 3. Clear Laravel config
echo "=== Clearing Laravel config ==="
php artisan config:clear

# 4. Test Laravel
echo "=== Testing Laravel connection ==="
php artisan db:show
```

## 🚨 Most Common Issues

1. **Password has special characters** - Try simple password first
2. **User not added to database** - Must add in cPanel
3. **No ALL PRIVILEGES** - User needs full access
4. **Spaces in .env** - No spaces around `=`
5. **Wrong host** - Use `localhost` not `127.0.0.1`

## ✅ Final Checklist

- [ ] Password reset in cPanel
- [ ] `.env` updated with new password (no spaces, no quotes)
- [ ] User added to database in cPanel
- [ ] User has ALL PRIVILEGES
- [ ] MySQL connection works directly
- [ ] Laravel config cleared
- [ ] Laravel connection tested

---

**Try resetting password to a simple one first to eliminate special character issues!** 🔐
