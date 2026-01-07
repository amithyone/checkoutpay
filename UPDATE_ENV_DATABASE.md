# Update .env File with Database Credentials

## ✅ Your Database Info

- **Database:** `checzspw_checkout`
- **Username:** `checzspw_checkout`
- **Password:** `Enter0text@@@#`
- **Host:** `localhost` (same as WordPress)

## 📝 Update .env File

Edit your `.env` file on the server:

```bash
cd /home/checzspw/public_html
nano .env
```

Or via cPanel File Manager:
1. Go to **File Manager**
2. Navigate to `public_html`
3. Click `.env` → **Edit**

## ✅ Correct .env Format

Update these lines in your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=checzspw_checkout
DB_USERNAME=checzspw_checkout
DB_PASSWORD=Enter0text@@@#
```

**CRITICAL:**
- ✅ NO spaces around `=`
- ✅ NO quotes around password
- ✅ Use exact password: `Enter0text@@@#`
- ✅ Use `localhost` (not `127.0.0.1`)

## 🚨 Common Mistakes to Avoid

```env
# ❌ WRONG - Spaces around =
DB_PASSWORD = Enter0text@@@#

# ❌ WRONG - Quotes around password
DB_PASSWORD="Enter0text@@@#"

# ❌ WRONG - Space after =
DB_PASSWORD= Enter0text@@@#

# ✅ CORRECT - No spaces, no quotes
DB_PASSWORD=Enter0text@@@#
```

## 🔧 After Updating .env

Run these commands:

```bash
cd /home/checzspw/public_html

# 1. Clear config cache
php artisan config:clear

# 2. Test MySQL connection directly
mysql -u checzspw_checkout -p -h localhost checzspw_checkout
# Enter password: Enter0text@@@#
# If this works, credentials are correct!

# 3. Test Laravel connection
php artisan db:show

# 4. If successful, run migrations
php artisan migrate --force
```

## ✅ Verification

After updating `.env`, verify it's correct:

```bash
# View database config from .env
cat .env | grep DB_

# Should show:
# DB_CONNECTION=mysql
# DB_HOST=localhost
# DB_PORT=3306
# DB_DATABASE=checzspw_checkout
# DB_USERNAME=checzspw_checkout
# DB_PASSWORD=Enter0text@@@#
```

## 🎯 Quick Copy-Paste for .env

Replace the database section in your `.env` with:

```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=checzspw_checkout
DB_USERNAME=checzspw_checkout
DB_PASSWORD=Enter0text@@@#
```

Then run:
```bash
php artisan config:clear
php artisan db:show
```

---

**Make sure there are NO spaces around the `=` sign and NO quotes around the password!** 🔐
