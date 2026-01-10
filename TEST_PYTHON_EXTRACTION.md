# Testing Python Extraction in Laravel

## Quick Test Commands for Tinker

Open tinker:
```bash
cd ~/public_html  # or wherever your Laravel app is
php artisan tinker
```

### Test 1: Check if Service is Available

In tinker, type each command **one at a time** and press Enter:

```php
$service = new App\Services\PythonExtractionService();
```

Press Enter, then:

```php
$service->isAvailable();
```

Should return `true` or `false`.

### Test 2: Test Full Extraction

```php
$emailData = [
    'processed_email_id' => 1,
    'subject' => 'Credit Alert',
    'from' => 'noreply@gtbank.com',
    'text_body' => 'Your account credited with NGN 1000.00',
    'html_body' => '<table><tr><td>Amount</td><td>NGN 1,000.00</td></tr></table>',
    'date' => null,
];
```

Press Enter, then:

```php
$result = $service->extractPaymentInfo($emailData);
```

Press Enter, then:

```php
$result;
```

This will show the extraction result.

### Test 3: Check Configuration

```php
config('services.python_extractor');
```

This will show your Python extraction configuration.

## Alternative: Test via Artisan Command

Instead of tinker, you can create a simple test command:

```bash
php artisan make:command TestPythonExtraction
```

Then test with:
```bash
php artisan test:python-extraction
```

## Common Tinker Issues

### Issue: Parse Error

If you get parse errors:
- Type each command **one line at a time**
- Press Enter after each command
- Don't copy-paste multiple lines at once
- Use semicolons at the end: `$service = new App\Services\PythonExtractionService();`

### Issue: Undefined Variable

Make sure you press Enter after each command. Tinker executes one statement at a time.

### Issue: Class Not Found

Check if the class is loaded:
```php
class_exists('App\Services\PythonExtractionService');
```

Should return `true`.

## Quick One-Liner Test

Instead of tinker, you can test directly:

```bash
cd ~/public_html
php artisan tinker --execute="(new App\Services\PythonExtractionService())->isAvailable()"
```

Should output `true` or `false`.

## Test via PHP Script

Create a test file `test_python.php` in your Laravel root:

```php
<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new App\Services\PythonExtractionService();

echo "Is Available: " . ($service->isAvailable() ? 'YES' : 'NO') . "\n";

$result = $service->extractPaymentInfo([
    'processed_email_id' => 1,
    'subject' => 'Credit Alert',
    'from' => 'noreply@gtbank.com',
    'text_body' => 'Your account credited with NGN 1000.00',
    'html_body' => '<table><tr><td>Amount</td><td>NGN 1,000.00</td></tr></table>',
]);

print_r($result);
```

Run:
```bash
php test_python.php
```
