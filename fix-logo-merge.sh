#!/bin/bash
# Script to fix logo file merge conflict on production server
# Run this script on your production server before pulling

cd /var/www/checkout || exit 1

echo "Checking for untracked logo files..."

# Check if files exist and are untracked
if git ls-files --error-unmatch public/logo.png public/favicon.png "public/black logo.png" public/flylogo.png >/dev/null 2>&1; then
    echo "Logo files are already tracked in git."
    echo "Pulling latest changes..."
    git pull origin main
else
    echo "Logo files exist but are untracked. Removing them to allow pull..."
    
    # Backup the files first (just in case)
    mkdir -p /tmp/logo-backup-$(date +%Y%m%d-%H%M%S)
    BACKUP_DIR="/tmp/logo-backup-$(date +%Y%m%d-%H%M%S)"
    
    [ -f "public/logo.png" ] && cp "public/logo.png" "$BACKUP_DIR/" && echo "Backed up logo.png"
    [ -f "public/favicon.png" ] && cp "public/favicon.png" "$BACKUP_DIR/" && echo "Backed up favicon.png"
    [ -f "public/black logo.png" ] && cp "public/black logo.png" "$BACKUP_DIR/" && echo "Backed up black logo.png"
    [ -f "public/flylogo.png" ] && cp "public/flylogo.png" "$BACKUP_DIR/" && echo "Backed up flylogo.png"
    
    echo "Backup created in: $BACKUP_DIR"
    
    # Remove untracked logo files
    rm -f "public/logo.png" "public/favicon.png" "public/black logo.png" "public/flylogo.png"
    
    echo "Removed untracked logo files. Now pulling from remote..."
    git pull origin main
    
    echo "Pull completed. Logo files should now be restored from git."
fi

echo "Done!"
