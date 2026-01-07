# Database Connection Troubleshooting Guide

## 🔴 Still Getting "Access Denied" Even With Correct Password?

### Step 1: Verify Exact Credentials in cPanel

1. **Go to cPanel → MySQL Databases**
2. **Check Current Users** section:
   - Find your user (e.g., `checzspw_checkout`)
   - Click **Change Password** next to it
   - Set a NEW password (even if it's the same)
   - **Copy the password exactly**

3. **Check Databases** section:
   - Find your database (e.g., `checzspw_checkout`)
   - Note the EXACT name (case-sensitive)

4. **Check "Add User To Database"** section:
   - Make sure user is added to database
   - User should have **ALL PRIVILEGES**

### Step 2: Check .env File Format

**Common Issues:**

1. **No quotes around values with special characters:**
```env
# ❌ WRONG - if password has special characters
DB_PASSWORD=my@password#123

# ✅ CORRECT - no quotes needed usually
DB_PASSWORD=my@password#123

# ✅ OR use quotes if it causes issues
DB_PASSWORD="my@password#123"
```

2. **Extra spaces:**
```env
# ❌ WRONG
DB_PASSWORD = mypassword

# ✅ CORRECT
DB_PASSWORD=mypassword
```

3. **Hidden characters or encoding issues:**
   - Make sure file is saved as UTF-8
   - No BOM (Byte Order Mark)

### Step 3: Try Different Host Formats

Edit `.env` and try these one at a time:

**Option 1:**
```env
DB_HOST=127.0.0.1
```

**Option 2:**
```env
DB_HOST=localhost
```

**Option 3:**
```env
DB_HOST=localhost
DB_SOCKET=/tmp/mysql.sock
```

### Step 4: Verify Username Format

cPanel usernames are usually prefixed. Check:

**In cPanel MySQL Databases:**
- Your actual username might be: `checzspw_checkout`
- But cPanel might show it as: `checzspw_checkout` (with prefix)

**Try both formats in .env:**
```env
# Try with prefix
DB_USERNAME=checzspw_checkout

# Or try without (if cPanel shows it differently)
DB_USERNAME=checkout
```

### Step 5: Reset Database User Password

**Via cPanel:**
1. Go to **MySQL Databases**
2. Find your user in **Current Users**
3. Click **Change Password**
4. Set a **simple password** (no special characters) for testing
5. Update `.env` with new password
6. Clear config: `php artisan config:clear`

### Step 6: Verify User Has Database Access

**Via cPanel:**
1. Go to **MySQL Databases**
2. Scroll to **Add User To Database**
3. If user is NOT listed:
   - Select user: `checzspw_checkout`
   - Select database: `checzspw_checkout`
   - Click **Add**
   - Make sure **ALL PRIVILEGES** is checked
   - Click **Make Changes**

### Step 7: Test Connection Manually

**Via SSH/Terminal:**
```bash
mysql -u checzspw_checkout -p checzspw_checkout
```

Enter password when prompted. If this works, the credentials are correct.

### Step 8: Check .env File Location

Make sure `.env` is in the correct location:
```bash
cd /home/checzspw/public_html
ls -la .env
```

Should show `.env` file exists.

### Step 9: Clear All Caches

```bash
cd /home/checzspw/public_html

# Clear config cache
php artisan config:clear

# Clear all caches
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Try again
php artisan db:show
```

### Step 10: Check .env File Syntax

**View your .env file:**
```bash
cat .env | grep DB_
```

Should show:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=checzspw_checkout
DB_USERNAME=checzspw_checkout
DB_PASSWORD=yourpassword
```

**Common mistakes:**
- ❌ `DB_PASSWORD = password` (spaces around =)
- ❌ `DB_PASSWORD="password"` (quotes might cause issues)
- ❌ `DB_PASSWORD= password` (space after =)
- ✅ `DB_PASSWORD=password` (correct)

## 🔍 Debugging Commands

**Check what Laravel sees:**
```bash
php artisan tinker
>>> config('database.connections.mysql')
```

This will show what credentials Laravel is using.

**Test MySQL connection directly:**
```bash
mysql -u checzspw_checkout -p -h 127.0.0.1 checzspw_checkout
```

## 🎯 Quick Fix Checklist

- [ ] Password reset in cPanel (even if same)
- [ ] `.env` file has no spaces around `=`
- [ ] `.env` file has no quotes around password
- [ ] User added to database with ALL PRIVILEGES
- [ ] Tried both `127.0.0.1` and `localhost` for DB_HOST
- [ ] Config cache cleared (`php artisan config:clear`)
- [ ] Tested MySQL connection manually (`mysql -u user -p database`)
- [ ] Verified exact username format from cPanel

## 🚨 Still Not Working?

**Create a new database user:**
1. cPanel → MySQL Databases
2. Create New User
3. Username: `checkout_user`
4. Password: Simple password (no special chars)
5. Add user to database
6. Grant ALL PRIVILEGES
7. Update `.env` with new credentials

**Or contact hosting support** - they can verify:
- Database name format
- Username format
- Host requirements
- Any special configurations needed

---

**Most common fix:** Reset password in cPanel and update `.env` with NO spaces or quotes! 🔐
