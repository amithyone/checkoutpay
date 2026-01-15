# Instructions to Fix Logo Merge Error on Production Server

## Problem
When pulling on production server via cPanel, you get this error:
```
error: The following untracked working tree files would be overwritten by merge:
public/black logo.png
public/favicon.png
public/flylogo.png
public/logo.png
```

## Solution

### Option 1: Use the Fix Script (Recommended)
1. SSH into your production server
2. Navigate to your checkout directory: `cd /var/www/checkout` (or your actual path)
3. Run the fix script: `bash fix-logo-merge.sh`
4. This will backup the files, remove them, pull from git, and restore them

### Option 2: Manual Fix via SSH
```bash
cd /var/www/checkout

# Backup files (optional but recommended)
mkdir -p /tmp/logo-backup
cp public/*.png /tmp/logo-backup/

# Remove untracked logo files
rm -f "public/logo.png" "public/favicon.png" "public/black logo.png" "public/flylogo.png"

# Pull from remote
git pull origin main

# Verify files are restored
ls -la public/*.png
```

### Option 3: Via cPanel File Manager
1. Go to cPanel → File Manager
2. Navigate to `public/` directory
3. Delete these files:
   - `logo.png`
   - `favicon.png`
   - `black logo.png`
   - `flylogo.png`
4. Then try pulling again via cPanel Git interface

## Why This Happens
The logo files exist on production as untracked files (not in git), but they also exist in the remote repository. When git tries to pull, it sees that pulling would overwrite your local untracked files, so it aborts to prevent data loss.

## After Fixing
Once you pull successfully, the logo files will be restored from the git repository and will be properly tracked. Future pulls should work without issues.
