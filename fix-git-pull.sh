#!/bin/bash
# Script to backup untracked files and allow git pull

cd /var/www/checkout || exit

# Create backup directory with timestamp
BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Move untracked files that would conflict
echo "Backing up untracked files..."

# Backup files
mv -f .env.save "$BACKUP_DIR/" 2>/dev/null
mv -f .env.save.1 "$BACKUP_DIR/" 2>/dev/null
mv -f .env.save.2 "$BACKUP_DIR/" 2>/dev/null
mv -f .htaccess.backup "$BACKUP_DIR/" 2>/dev/null
mv -f 0.95 "$BACKUP_DIR/" 2>/dev/null
mv -f 2026-01-10T17:20:06.355184Z "$BACKUP_DIR/" 2>/dev/null
mv -f 480 "$BACKUP_DIR/" 2>/dev/null
mv -f 712 "$BACKUP_DIR/" 2>/dev/null
mv -f Array "$BACKUP_DIR/" 2>/dev/null
mv -f NGN "$BACKUP_DIR/" 2>/dev/null
mv -f Transaction "$BACKUP_DIR/" 2>/dev/null
mv -f app.zip "$BACKUP_DIR/" 2>/dev/null
mv -f credit "$BACKUP_DIR/" 2>/dev/null
mv -f error_log "$BACKUP_DIR/" 2>/dev/null
mv -f html_table "$BACKUP_DIR/" 2>/dev/null
mv -f noreply@gtbank.com "$BACKUP_DIR/" 2>/dev/null
mv -f public/error_log "$BACKUP_DIR/" 2>/dev/null

# Backup storage settings images (they might be needed, but we'll move them)
mkdir -p "$BACKUP_DIR/storage_app_public_settings"
mv -f storage/app/public/settings/*.png "$BACKUP_DIR/storage_app_public_settings/" 2>/dev/null

echo "Files backed up to: $BACKUP_DIR"
echo ""
echo "Now running git pull..."
git pull

echo ""
echo "Done! Backup is in: $BACKUP_DIR"
echo "If you need any files from backup, restore them manually."
