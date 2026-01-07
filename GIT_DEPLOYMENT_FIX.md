# Fix: Git Merge Error on Live Server

## 🔴 Error
```
error: The following untracked working tree files would be overwritten by merge:
.htaccess
Please move or remove them before you merge. Aborting
```

## ✅ Solution

The server has a local `.htaccess` file that conflicts with the one in Git. Here are the solutions:

### Option 1: Remove Local File and Pull (Recommended)

**Via SSH:**
```bash
cd /home/checzspw/public_html

# Backup the existing .htaccess (just in case)
cp .htaccess .htaccess.backup

# Remove the local .htaccess
rm .htaccess

# Now pull from Git
git pull origin main
```

**Via cPanel File Manager:**
1. Go to **File Manager**
2. Navigate to `public_html`
3. Rename `.htaccess` to `.htaccess.backup`
4. Go to **Git Version Control** in cPanel
5. Click **Pull or Deploy**

### Option 2: Stash Local Changes

```bash
cd /home/checzspw/public_html

# Stash local changes (saves them)
git stash

# Pull from Git
git pull origin main

# If you need the old .htaccess, restore it:
# git stash pop
```

### Option 3: Force Overwrite (Use with Caution)

```bash
cd /home/checzspw/public_html

# Remove the file
rm .htaccess

# Reset to match remote
git reset --hard origin/main

# Pull again
git pull origin main
```

### Option 4: Add and Commit Local File First

If your local `.htaccess` has important server-specific settings:

```bash
cd /home/checzspw/public_html

# Add the local file
git add .htaccess

# Commit it
git commit -m "Add server-specific .htaccess"

# Now pull (may need to merge)
git pull origin main
```

## 🎯 Recommended Steps

1. **Backup existing .htaccess:**
```bash
cp .htaccess .htaccess.backup
```

2. **Remove it:**
```bash
rm .htaccess
```

3. **Pull from Git:**
```bash
git pull origin main
```

4. **Verify the new .htaccess works:**
   - Check if your site loads correctly
   - If not, restore backup: `cp .htaccess.backup .htaccess`

## 📝 After Pulling

Make sure to run:
```bash
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan config:cache
php artisan route:cache
```

## 🔍 Why This Happened

- The `.htaccess` file exists on your server but wasn't tracked in Git
- Git wants to create/update it from the repository
- Git won't overwrite untracked files to prevent data loss

## ✅ Prevention

Always commit `.htaccess` changes to Git so server and repository stay in sync.
