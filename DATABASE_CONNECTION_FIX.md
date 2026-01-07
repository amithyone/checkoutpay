# Fix: Database Connection Error

## 🔴 Error
```
SQLSTATE[28000] [1045] Access denied for user 'checzspw_checkout'@'localhost' (using password: YES)
```

## ✅ Solution

The database credentials in your `.env` file are incorrect or the database user doesn't have proper permissions.

### Step 1: Check Database Credentials

**Via cPanel:**
1. Go to **cPanel** → **MySQL Databases**
2. Find your database name (should be something like `checzspw_checkout`)
3. Find your database user (should be something like `checzspw_checkout`)
4. Note the exact database name and username

### Step 2: Update .env File

Edit your `.env` file on the server:

```bash
cd /home/checzspw/public_html
nano .env
```

Or via cPanel File Manager:
1. Go to **File Manager**
2. Navigate to `public_html`
3. Click on `.env` file
4. Click **Edit**

Update these lines:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=checzspw_checkout
DB_USERNAME=checzspw_checkout
DB_PASSWORD=your_actual_password_here
```

**Important:**
- Database name format: Usually `username_dbname` (e.g., `checzspw_checkout`)
- Username format: Usually same as database name
- Password: The password you set when creating the database user
- Host: Usually `127.0.0.1` or `localhost` for cPanel

### Step 3: Verify Database User Permissions

**Via cPanel:**
1. Go to **cPanel** → **MySQL Databases**
2. Scroll to **Add User To Database**
3. Make sure your user is added to your database
4. User should have **ALL PRIVILEGES**

**Or via MySQL Command Line:**
```bash
mysql -u root -p

# Check if user exists
SELECT User, Host FROM mysql.user WHERE User='checzspw_checkout';

# Grant permissions (if needed)
GRANT ALL PRIVILEGES ON checzspw_checkout.* TO 'checzspw_checkout'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 4: Test Database Connection

After updating `.env`, test the connection:

```bash
cd /home/checzspw/public_html

# Clear config cache
php artisan config:clear

# Test connection
php artisan db:show
```

If it works, you should see database information.

### Step 5: Run Migrations and Seeders

```bash
# Run migrations
php artisan migrate --force

# Run seeders
php artisan db:seed --class=AdminSeeder --force
php artisan db:seed --class=AccountNumberSeeder --force
```

## 🔍 Common Issues

### Issue 1: Wrong Database Name
- Check exact database name in cPanel
- Database names are case-sensitive
- Format is usually `username_dbname`

### Issue 2: Wrong Username
- Check exact username in cPanel
- Usernames are case-sensitive
- Format is usually same as database name

### Issue 3: Wrong Password
- Reset password in cPanel → MySQL Databases
- Click **Change Password** next to your user
- Update `.env` with new password

### Issue 4: User Not Added to Database
- Go to cPanel → MySQL Databases
- Scroll to **Add User To Database**
- Select user and database
- Click **Add**
- Make sure **ALL PRIVILEGES** is selected

### Issue 5: Wrong Host
- Try `127.0.0.1` instead of `localhost`
- Or try `localhost` instead of `127.0.0.1`
- Some servers require specific host format

## 📝 cPanel Database Setup Checklist

1. ✅ **Create Database**
   - cPanel → MySQL Databases → Create New Database
   - Name: `checkout` (will become `checzspw_checkout`)

2. ✅ **Create User**
   - cPanel → MySQL Databases → Create New User
   - Username: `checkout` (will become `checzspw_checkout`)
   - Password: Strong password (save it!)

3. ✅ **Add User to Database**
   - Scroll to **Add User To Database**
   - Select user: `checzspw_checkout`
   - Select database: `checzspw_checkout`
   - Click **Add**
   - Check **ALL PRIVILEGES**
   - Click **Make Changes**

4. ✅ **Update .env**
   - Use exact database name and username
   - Use the password you set

## 🚨 Security Note

**Never commit `.env` file to Git!**

Make sure `.env` is in `.gitignore`:
```
.env
.env.backup
.env.production
```

## ✅ Quick Test Script

After updating `.env`:

```bash
cd /home/checzspw/public_html

# Clear config
php artisan config:clear

# Test connection
php artisan db:show

# If successful, run migrations
php artisan migrate --force
```

## 📞 Still Having Issues?

1. **Double-check credentials** in cPanel
2. **Reset database password** and update `.env`
3. **Verify user has ALL PRIVILEGES** on database
4. **Check database name format** (username_dbname)
5. **Try different host** (`127.0.0.1` vs `localhost`)

---

**Remember:** Database credentials are case-sensitive! Use exact names from cPanel. 🔐
