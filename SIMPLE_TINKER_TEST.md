# Simple Tinker Test Commands

## Correct Way to Use Tinker

**Important:** Type each command **ONE LINE AT A TIME** and press Enter after each.

### Step 1: Open Tinker

```bash
cd ~/public_html  # or wherever your Laravel app is
php artisan tinker
```

### Step 2: Test Service Availability

In tinker, type this **exactly** (one line):

```php
$service = new App\Services\PythonExtractionService();
```

Press **Enter**. Should see:
```
=> App\Services\PythonExtractionService {#1234}
```

If you see an error, there's a problem with the class.

Then type (next line):

```php
$service->isAvailable();
```

Press **Enter**. Should see `true` or `false`.

### Step 3: Test Extraction

Type this (one line):

```php
$emailData = ['processed_email_id' => 1, 'subject' => 'Test', 'from' => 'test@bank.com', 'text_body' => 'NGN 1000.00', 'html_body' => '<table><tr><td>Amount</td><td>NGN 1,000.00</td></tr></table>'];
```

Press **Enter**.

Then type:

```php
$result = $service->extractPaymentInfo($emailData);
```

Press **Enter**.

Then type:

```php
$result
```

Press **Enter**. Should see the extraction result.

### Common Tinker Mistakes

❌ **Wrong:** Copy-pasting multiple lines at once
❌ **Wrong:** Forgetting semicolons
❌ **Wrong:** Using `hp` or `php` inside tinker
❌ **Wrong:** Copy-pasting class definitions

✅ **Right:** One command per line
✅ **Right:** Press Enter after each command
✅ **Right:** Use semicolons: `$service = new App\Services\PythonExtractionService();`

## Alternative: Use Test Script (Easier!)

Instead of tinker, use the test script:

```bash
cd ~/public_html
php test_python_extraction.php
```

This will:
- Check configuration
- Test service availability
- Test extraction
- Show detailed results

Much easier than tinker!
