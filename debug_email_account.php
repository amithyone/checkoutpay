<?php
/**
 * Debug Email Account Settings
 * Run this on your server to check what's stored in the database
 * Usage: php debug_email_account.php
 * 
 * Make sure to set your database connection in .env first
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmailAccount;
use Illuminate\Support\Facades\Crypt;

echo "========================================\n";
echo "Email Account Debug Tool\n";
echo "========================================\n\n";

// Find the email account
$emailAccount = EmailAccount::where('email', 'notify@check-outpay.com')->first();

if (!$emailAccount) {
    echo "❌ Email account not found in database!\n";
    echo "   Email: notify@check-outpay.com\n\n";
    echo "Create the account first in the admin panel.\n";
    exit(1);
}

echo "✅ Found email account: {$emailAccount->email}\n\n";

echo "Current Settings in Database:\n";
echo "----------------------------------------\n";
echo "ID: {$emailAccount->id}\n";
echo "Name: {$emailAccount->name}\n";
echo "Email: {$emailAccount->email}\n";
echo "Host: {$emailAccount->host}\n";
echo "Port: {$emailAccount->port}\n";
echo "Encryption: {$emailAccount->encryption}\n";
echo "Validate Cert: " . ($emailAccount->validate_cert ? 'Yes' : 'No') . "\n";
echo "Folder: {$emailAccount->folder}\n";
echo "Method: {$emailAccount->method}\n";
echo "Active: " . ($emailAccount->is_active ? 'Yes' : 'No') . "\n\n";

// Get decrypted password
try {
    $password = $emailAccount->getPasswordAttribute($emailAccount->attributes['password'] ?? '');
    echo "Password (decrypted): " . str_repeat('*', strlen($password)) . " (length: " . strlen($password) . ")\n";
    echo "Password contains @: " . (strpos($password, '@') !== false ? 'Yes' : 'No') . "\n";
    echo "Password matches 'Enter0text@': " . ($password === 'Enter0text@' ? 'Yes ✅' : 'No ❌') . "\n";
    
    if ($password !== 'Enter0text@') {
        echo "\n⚠️  WARNING: Password does not match expected value!\n";
        echo "   Expected: Enter0text@\n";
        echo "   Actual length: " . strlen($password) . "\n";
        echo "   First character: " . (isset($password[0]) ? "'{$password[0]}'" : 'empty') . "\n";
        echo "   Last character: " . (substr($password, -1) !== false ? "'" . substr($password, -1) . "'" : 'empty') . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error decrypting password: " . $e->getMessage() . "\n";
    $password = '';
}

echo "\n";

// Check if settings match working configuration
echo "Settings Comparison:\n";
echo "----------------------------------------\n";
$correctHost = 'check-outpay.com';
$correctPort = 993;
$correctEncryption = 'ssl';
$correctPassword = 'Enter0text@';

$issues = [];

if ($emailAccount->host !== $correctHost) {
    $issues[] = "Host mismatch: Database has '{$emailAccount->host}' but should be '{$correctHost}'";
}
if ($emailAccount->port != $correctPort) {
    $issues[] = "Port mismatch: Database has '{$emailAccount->port}' but should be '{$correctPort}'";
}
if ($emailAccount->encryption !== $correctEncryption) {
    $issues[] = "Encryption mismatch: Database has '{$emailAccount->encryption}' but should be '{$correctEncryption}'";
}
if ($password !== $correctPassword) {
    $issues[] = "Password mismatch: Password does not match expected value '{$correctPassword}'";
}
if ($emailAccount->validate_cert) {
    $issues[] = "Validate Cert should be unchecked (false)";
}

if (empty($issues)) {
    echo "✅ All settings match the working configuration!\n\n";
} else {
    echo "❌ Issues found:\n";
    foreach ($issues as $issue) {
        echo "   - {$issue}\n";
    }
    echo "\n";
}

// Test connection with database password
echo "Testing Connection with Database Settings:\n";
echo "----------------------------------------\n";

if (empty($password)) {
    echo "❌ Cannot test - password is empty or could not be decrypted\n";
} else {
    if (!function_exists('imap_open')) {
        echo "❌ PHP IMAP extension is not installed!\n";
    } else {
        $encryption = $emailAccount->encryption === 'ssl' ? 'ssl' : ($emailAccount->encryption === 'tls' ? 'tls' : '');
        $validateCert = $emailAccount->validate_cert ? '' : '/novalidate-cert';
        $folder = $emailAccount->folder ?? 'INBOX';
        
        $connectionString = "{{$emailAccount->host}:{$emailAccount->port}";
        if ($encryption) {
            $connectionString .= "/{$encryption}";
        }
        $connectionString .= "{$validateCert}}{$folder}";
        
        echo "Connection string: {$connectionString}\n";
        echo "Email: {$emailAccount->email}\n";
        echo "Password length: " . strlen($password) . "\n\n";
        
        imap_errors();
        imap_alerts();
        
        $conn = @imap_open($connectionString, $emailAccount->email, $password, OP_HALFOPEN);
        
        if ($conn) {
            echo "✅ SUCCESS! Connection works with database settings!\n";
            imap_close($conn);
        } else {
            $error = imap_last_error();
            echo "❌ FAILED!\n";
            echo "Error: " . ($error ?: 'Unknown error') . "\n\n";
            
            $errors = imap_errors();
            if ($errors && is_array($errors)) {
                echo "All errors:\n";
                foreach ($errors as $err) {
                    if (trim($err)) {
                        echo "  - {$err}\n";
                    }
                }
            }
        }
        
        imap_errors();
        imap_alerts();
    }
}

echo "\n========================================\n";
echo "Recommendations:\n";
echo "========================================\n\n";

if (!empty($issues)) {
    echo "1. Go to Admin Panel → Email Accounts\n";
    echo "2. Edit the account for notify@check-outpay.com\n";
    echo "3. Update settings to match:\n";
    echo "   - Host: {$correctHost}\n";
    echo "   - Port: {$correctPort}\n";
    echo "   - Encryption: {$correctEncryption}\n";
    echo "   - Password: {$correctPassword} (make sure to include @)\n";
    echo "   - Validate Certificate: Unchecked\n";
    echo "4. Save and test connection\n";
} else {
    echo "Settings look correct! If connection still fails in admin panel,\n";
    echo "check Laravel logs: storage/logs/laravel.log\n";
}

echo "\nDone!\n";
