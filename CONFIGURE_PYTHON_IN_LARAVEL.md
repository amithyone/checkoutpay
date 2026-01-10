# Configure Python Extraction in Laravel

## ✅ Python Script Works!

Your Python extraction script is working perfectly on your server!

## Step 1: Find Your Laravel .env File

Find where your Laravel application is located:

```bash
# Check if Laravel is in public_html
cd ~/public_html
ls -la

# Or check if it's in a subdirectory
cd ~/public_html
find . -name ".env" -type f
```

The `.env` file should be in your Laravel root directory.

## Step 2: Get the Full Path to Python Script

Get the absolute path to your Python script:

```bash
cd ~/public_html/python-extractor
pwd
```

This will show something like: `/home/checzspw/public_html/python-extractor`

So the full path to the script is: `/home/checzspw/public_html/python-extractor/extract_simple.py`

## Step 3: Add Configuration to .env

Edit your Laravel `.env` file:

```bash
# Navigate to Laravel root (where .env is)
cd ~/public_html  # or wherever your Laravel app is

# Edit .env file
nano .env
# or
vi .env
```

Add these lines at the end of the `.env` file:

```env
# Python Extraction Service (Shared Hosting - Script Mode)
PYTHON_EXTRACTOR_ENABLED=true
PYTHON_EXTRACTOR_MODE=script
PYTHON_EXTRACTOR_SCRIPT_PATH=/home/checzspw/public_html/python-extractor/extract_simple.py
PYTHON_EXTRACTOR_COMMAND=python3
PYTHON_EXTRACTOR_MIN_CONFIDENCE=0.7
PYTHON_EXTRACTOR_TIMEOUT=10
```

**Important:** Make sure the path matches exactly what `pwd` showed in Step 2!

Save the file (Ctrl+X, then Y, then Enter if using nano).

## Step 4: Clear Laravel Cache

After updating `.env`, clear Laravel config cache:

```bash
cd ~/public_html  # or wherever your Laravel app is
php artisan config:clear
php artisan cache:clear
```

## Step 5: Test Laravel Can Call Python

Test if Laravel can execute the Python script:

```bash
cd ~/public_html  # Laravel root
php artisan tinker
```

In tinker, run:

```php
$service = new \App\Services\PythonExtractionService();
$available = $service->isAvailable();
var_dump($available);
exit
```

Should return `bool(true)` if everything is configured correctly.

## Step 6: Test Full Extraction

In tinker:

```php
$service = new \App\Services\PythonExtractionService();
$result = $service->extractPaymentInfo([
    'processed_email_id' => 1,
    'subject' => 'Credit Alert',
    'from' => 'noreply@gtbank.com',
    'text_body' => 'Your account credited with NGN 1000.00',
    'html_body' => '<table><tr><td>Amount</td><td>NGN 1,000.00</td></tr></table>',
]);
var_dump($result);
exit
```

Should return an array with `amount`, `method`, and `confidence`.

## Step 7: Test with Real Email

Try processing an email from your admin panel or check existing match attempts. The system should now use Python extraction!

Check logs:

```bash
tail -f storage/logs/laravel.log | grep -i python
```

You should see logs like:
- "Payment info extracted using Python service"
- Extraction method: "python_extractor" or "html_table"
- Confidence scores

## Troubleshooting

### Error: Script not found

Check the path is correct:

```bash
# Verify script exists at the path in .env
cat /home/checzspw/public_html/python-extractor/extract_simple.py | head -5

# If file doesn't exist, check actual location
find ~ -name "extract_simple.py" 2>/dev/null
```

### Error: Permission denied

Make sure script is executable:

```bash
chmod +x ~/public_html/python-extractor/extract_simple.py
ls -la ~/public_html/python-extractor/extract_simple.py
# Should show -rwxr-xr-x (executable)
```

### Error: Python command not found

Try using full path:

```bash
# Find Python path
which python3

# Update .env:
# PYTHON_EXTRACTOR_COMMAND=/usr/bin/python3
```

### Laravel can't find config

Clear cache and check:

```bash
php artisan config:clear
php artisan config:cache
php artisan tinker
config('services.python_extractor')
```

Should show your configuration.

## Verification Checklist

- [ ] Python script exists at the path in `.env`
- [ ] Script is executable (`chmod +x`)
- [ ] Script works when tested directly (you already did this ✅)
- [ ] `.env` file has correct path
- [ ] Laravel config cache cleared
- [ ] `$service->isAvailable()` returns `true`
- [ ] Test extraction returns valid data

## Next Steps

Once configured:
1. Try processing a real email
2. Check match attempts in admin panel
3. Look for "python_extractor" or "html_table" in extraction method
4. Monitor confidence scores in match attempts

If everything works, Python extraction is now active! 🎉
