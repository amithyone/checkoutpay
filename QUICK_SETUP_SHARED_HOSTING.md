# Quick Setup for Shared Hosting - Python Extraction

## ✅ Your Python is Ready!

You have **Python 3.6.8** installed. The simple extraction script works with Python 3.6+ (uses only standard library).

## Step 1: Upload Python Script

On your **local machine**, upload `python-extractor/extract_simple.py` to your server:

```bash
# Option A: Using SCP (from your local machine)
scp python-extractor/extract_simple.py checzspw@premium340.web-hosting.com:~/public_html/python-extractor/extract_simple.py

# Option B: Using FTP/SFTP client
# Upload extract_simple.py to: /home/checzspw/public_html/python-extractor/extract_simple.py
```

Or manually create the file on your server:

```bash
# On your server
mkdir -p ~/public_html/python-extractor
# Then create/edit extract_simple.py in that directory (copy content from local file)
```

## Step 2: Make Script Executable

On your server:

```bash
chmod +x ~/public_html/python-extractor/extract_simple.py
```

## Step 3: Test the Script

Test if it works:

```bash
cd ~/public_html/python-extractor

# Test with sample data
echo '{"text_body":"Your account credited with NGN 1000.00","html_body":"<table><tr><td>Amount</td><td>NGN 1,000.00</td></tr></table>","from_email":"noreply@gtbank.com"}' | python3 extract_simple.py
```

**Expected output:**
```json
{"success": true, "data": {"amount": 1000.0, "currency": "NGN", "confidence": 0.95, "source": "html_table", ...}, ...}
```

If you see JSON output with `"success": true`, the script works! ✅

## Step 4: Find Your Public HTML Path

Check where your Laravel app is located:

```bash
# Your Laravel app is likely at:
cd ~/public_html
pwd
# Should show something like: /home/checzspw/public_html
```

## Step 5: Configure Laravel .env

Add these lines to your Laravel `.env` file:

```env
# Python Extraction Service (Shared Hosting Mode)
PYTHON_EXTRACTOR_ENABLED=true
PYTHON_EXTRACTOR_MODE=script
PYTHON_EXTRACTOR_SCRIPT_PATH=/home/checzspw/public_html/python-extractor/extract_simple.py
PYTHON_EXTRACTOR_COMMAND=python3
PYTHON_EXTRACTOR_MIN_CONFIDENCE=0.7
```

**Important:** Make sure the path matches where you uploaded the script!

## Step 6: Verify Configuration

Test if Laravel can find Python:

```bash
cd ~/public_html
php artisan tinker

# In tinker, run:
config('services.python_extractor')
```

Should show your configuration.

## Step 7: Test Extraction

Try processing an email or create a test transaction. Check logs:

```bash
tail -f storage/logs/laravel.log | grep -i python
```

You should see logs about Python extraction attempts.

## Troubleshooting

### Script not found

If you get "Script not found" error, check:

```bash
# Verify script exists
ls -la ~/public_html/python-extractor/extract_simple.py

# Check permissions
chmod +x ~/public_html/python-extractor/extract_simple.py

# Verify path in .env matches actual path
```

### Python command not found

If you get "Python command not found":

```bash
# Find Python path
which python3
# Use full path in .env:
# PYTHON_EXTRACTOR_COMMAND=/usr/bin/python3
```

### Permission denied

If you get permission errors:

```bash
# Make script executable
chmod +x ~/public_html/python-extractor/extract_simple.py

# Check file ownership
ls -la ~/public_html/python-extractor/extract_simple.py
# Should be owned by you (checzspw)
```

### Script returns error

Test the script directly to see the error:

```bash
cd ~/public_html/python-extractor
echo '{"text_body":"NGN 1000.00","html_body":"","from_email":""}' | python3 extract_simple.py 2>&1
```

This will show any Python errors.

## Quick Test Command

Run this complete test on your server:

```bash
# Create test directory
mkdir -p ~/public_html/python-extractor

# Create test file (if extract_simple.py not uploaded yet)
# Upload extract_simple.py first, then:

# Test script
cd ~/public_html/python-extractor
echo '{"text_body":"Amount: NGN 5000.00","html_body":"<td>Amount</td><td>NGN 5,000.00</td>","from_email":"test@bank.com"}' | python3 extract_simple.py

# Should output JSON with success: true
```

## Fallback: Use PHP Extraction

If Python setup fails, you can always use PHP extraction:

```env
PYTHON_EXTRACTOR_ENABLED=false
```

The system will automatically use PHP extraction (existing logic).

## Next Steps

1. ✅ Upload `extract_simple.py` to server
2. ✅ Make it executable (`chmod +x`)
3. ✅ Test script directly
4. ✅ Configure `.env` file
5. ✅ Test in Laravel
6. ✅ Monitor logs for Python extraction

If anything fails, check the logs and use PHP extraction as fallback!
