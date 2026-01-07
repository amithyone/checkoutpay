# Fix Git Pull Conflict

## 🔴 Error
```
error: Your local changes to the following files would be overwritten by merge:
        composer.lock
        public/index.php
```

## ✅ Solution

You have local changes that conflict with the remote. Here are your options:

### Option 1: Stash Changes (Recommended)

```bash
cd /home/checzspw/public_html

# Stash your local changes
git stash

# Pull latest changes
git pull origin main

# If you need your stashed changes back:
# git stash pop
```

### Option 2: Discard Local Changes

If you don't need the local changes:

```bash
cd /home/checzspw/public_html

# Discard changes to composer.lock (will be regenerated anyway)
git checkout -- composer.lock

# Discard changes to public/index.php (we have the fixed version)
git checkout -- public/index.php

# Now pull
git pull origin main
```

### Option 3: Commit Local Changes First

If you want to keep your changes:

```bash
cd /home/checzspw/public_html

# Add and commit local changes
git add composer.lock public/index.php
git commit -m "Local changes before pull"

# Pull (may need to merge)
git pull origin main
```

## 🎯 Recommended: Option 2

Since `composer.lock` should be regenerated and `public/index.php` has the fix in the repo:

```bash
cd /home/checzspw/public_html

# Discard local changes
git checkout -- composer.lock
git checkout -- public/index.php

# Pull latest
git pull origin main

# Regenerate composer.lock
composer install --no-dev --optimize-autoloader
```

---

**Use Option 2 - it's the cleanest solution!** 🎯
