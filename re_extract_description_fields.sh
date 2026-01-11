#!/bin/bash

# Script to re-extract description fields from existing processed emails
# Run this on your server after pulling the latest code

echo "🔄 Re-extracting description fields from existing emails..."
echo ""

# Navigate to public_html
cd ~/public_html || exit 1

# Step 1: Run migration to add description_field column
echo "📦 Step 1: Running migration to add description_field column..."
php artisan migrate --force

if [ $? -ne 0 ]; then
    echo "❌ Migration failed!"
    exit 1
fi

echo "✅ Migration completed!"
echo ""

# Step 2: Re-extract description fields
echo "🔄 Step 2: Re-extracting description fields from existing emails..."
php artisan payment:re-extract-description-fields

if [ $? -ne 0 ]; then
    echo "❌ Re-extraction failed!"
    exit 1
fi

echo ""
echo "✅ All done!"
