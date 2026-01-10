#!/bin/bash
# Automated setup script for Python extraction on shared hosting
# Run this on your server: bash setup_python_on_server.sh

set -e  # Exit on error

echo "========================================="
echo "Python Extraction Setup Script"
echo "========================================="
echo ""

# Step 1: Check Python
echo "Step 1: Checking Python installation..."
if command -v python3 &> /dev/null; then
    PYTHON_VERSION=$(python3 --version)
    echo "✓ Python found: $PYTHON_VERSION"
else
    echo "✗ Python 3 not found. Please install Python 3."
    exit 1
fi

# Step 2: Find Laravel root
echo ""
echo "Step 2: Finding Laravel root directory..."
if [ -f "artisan" ]; then
    LARAVEL_ROOT=$(pwd)
    echo "✓ Laravel root found: $LARAVEL_ROOT"
elif [ -f "public_html/artisan" ]; then
    LARAVEL_ROOT="$HOME/public_html"
    echo "✓ Laravel root found: $LARAVEL_ROOT"
else
    echo "? Laravel root not found in current directory."
    read -p "Enter full path to Laravel root (where artisan is): " LARAVEL_ROOT
    if [ ! -f "$LARAVEL_ROOT/artisan" ]; then
        echo "✗ Laravel root not found at: $LARAVEL_ROOT"
        exit 1
    fi
fi

cd "$LARAVEL_ROOT"

# Step 3: Create Python script directory
echo ""
echo "Step 3: Creating Python script directory..."
PYTHON_DIR="$LARAVEL_ROOT/python-extractor"
mkdir -p "$PYTHON_DIR"
echo "✓ Directory created: $PYTHON_DIR"

# Step 4: Check if script exists
echo ""
echo "Step 4: Checking if extract_simple.py exists..."
if [ -f "$PYTHON_DIR/extract_simple.py" ]; then
    echo "✓ Script already exists: $PYTHON_DIR/extract_simple.py"
else
    echo "⚠ Script not found. You need to upload extract_simple.py to:"
    echo "   $PYTHON_DIR/extract_simple.py"
    echo ""
    read -p "Press Enter after uploading the file, or Ctrl+C to cancel..."
fi

# Step 5: Make script executable
echo ""
echo "Step 5: Making script executable..."
chmod +x "$PYTHON_DIR/extract_simple.py"
echo "✓ Script is now executable"

# Step 6: Test script
echo ""
echo "Step 6: Testing Python script..."
TEST_INPUT='{"text_body":"NGN 1000.00","html_body":"<table><tr><td>Amount</td><td>NGN 1,000.00</td></tr></table>","from_email":"test@bank.com"}'
TEST_OUTPUT=$(echo "$TEST_INPUT" | python3 "$PYTHON_DIR/extract_simple.py" 2>&1)

if echo "$TEST_OUTPUT" | grep -q '"success": true'; then
    echo "✓ Script test successful!"
    echo "   Output: $(echo "$TEST_OUTPUT" | head -c 200)..."
else
    echo "✗ Script test failed!"
    echo "   Output: $TEST_OUTPUT"
    exit 1
fi

# Step 7: Configure .env
echo ""
echo "Step 7: Configuring Laravel .env file..."
ENV_FILE="$LARAVEL_ROOT/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "✗ .env file not found at: $ENV_FILE"
    exit 1
fi

# Get absolute path for script
SCRIPT_PATH="$PYTHON_DIR/extract_simple.py"
ABS_SCRIPT_PATH=$(readlink -f "$SCRIPT_PATH" 2>/dev/null || echo "$SCRIPT_PATH")

echo "   Script path: $ABS_SCRIPT_PATH"

# Check if config already exists
if grep -q "PYTHON_EXTRACTOR_ENABLED" "$ENV_FILE"; then
    echo "⚠ Python extraction config already exists in .env"
    read -p "Do you want to update it? (y/n): " UPDATE_CONFIG
    if [ "$UPDATE_CONFIG" = "y" ] || [ "$UPDATE_CONFIG" = "Y" ]; then
        # Remove old config
        sed -i '/^PYTHON_EXTRACTOR_/d' "$ENV_FILE"
        echo "   Removed old configuration"
    else
        echo "   Keeping existing configuration"
        UPDATE_CONFIG="n"
    fi
else
    UPDATE_CONFIG="y"
fi

# Add new config
if [ "$UPDATE_CONFIG" = "y" ] || [ "$UPDATE_CONFIG" = "Y" ]; then
    echo "" >> "$ENV_FILE"
    echo "# Python Extraction Service (Shared Hosting - Script Mode)" >> "$ENV_FILE"
    echo "PYTHON_EXTRACTOR_ENABLED=true" >> "$ENV_FILE"
    echo "PYTHON_EXTRACTOR_MODE=script" >> "$ENV_FILE"
    echo "PYTHON_EXTRACTOR_SCRIPT_PATH=$ABS_SCRIPT_PATH" >> "$ENV_FILE"
    echo "PYTHON_EXTRACTOR_COMMAND=python3" >> "$ENV_FILE"
    echo "PYTHON_EXTRACTOR_MIN_CONFIDENCE=0.7" >> "$ENV_FILE"
    echo "PYTHON_EXTRACTOR_TIMEOUT=10" >> "$ENV_FILE"
    echo "✓ Configuration added to .env"
else
    echo "   Skipped configuration update"
fi

# Step 8: Clear Laravel cache
echo ""
echo "Step 8: Clearing Laravel cache..."
if [ -f "artisan" ]; then
    php artisan config:clear 2>/dev/null || echo "⚠ Could not clear config cache"
    php artisan cache:clear 2>/dev/null || echo "⚠ Could not clear cache"
    echo "✓ Cache cleared"
else
    echo "⚠ Could not find artisan file, skipping cache clear"
fi

# Step 9: Test Laravel integration
echo ""
echo "Step 9: Testing Laravel integration..."
if [ -f "test_python_extraction.php" ]; then
    echo "   Running test script..."
    php test_python_extraction.php 2>&1 | head -50
    echo ""
    echo "✓ Test script executed (check output above)"
else
    echo "⚠ test_python_extraction.php not found, skipping test"
fi

# Summary
echo ""
echo "========================================="
echo "Setup Complete!"
echo "========================================="
echo ""
echo "Summary:"
echo "  ✓ Python: $PYTHON_VERSION"
echo "  ✓ Script location: $ABS_SCRIPT_PATH"
echo "  ✓ Laravel root: $LARAVEL_ROOT"
echo "  ✓ Configuration added to .env"
echo ""
echo "Next steps:"
echo "  1. Verify configuration in .env file"
echo "  2. Test with: php test_python_extraction.php"
echo "  3. Try processing an email from admin panel"
echo "  4. Check logs: tail -f storage/logs/laravel.log"
echo ""
echo "If you encounter issues, check:"
echo "  - Script exists and is executable"
echo "  - .env configuration is correct"
echo "  - Laravel cache is cleared"
echo ""
