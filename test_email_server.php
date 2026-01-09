<?php
/**
 * Simple Email Connection Test
 * Upload this to your server and run: php test_email_server.php
 * Or access via browser: https://your-domain.com/test_email_server.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Settings from your cPanel
$host = 'check-outpay.com';
$port = 993;
$email = 'notify@check-outpay.com';
$password = 'Enter0text@';  // Make sure this matches exactly

echo "========================================\n";
echo "Email Connection Diagnostic Test\n";
echo "========================================\n\n";

// Test 1: Check PHP IMAP Extension
echo "1. Checking PHP IMAP Extension...\n";
if (!function_exists('imap_open')) {
    echo "   ❌ PHP IMAP extension is NOT installed!\n";
    echo "   → Contact your hosting provider to install php-imap extension\n\n";
    exit(1);
}
echo "   ✅ PHP IMAP extension is installed\n\n";

// Test 2: Check PHP Version
echo "2. PHP Version: " . PHP_VERSION . "\n\n";

// Test 3: Try Different Connection Configurations
echo "3. Testing Connection Configurations...\n";
echo "   Host: {$host}\n";
echo "   Port: {$port}\n";
echo "   Email: {$email}\n";
echo "   Password: " . str_repeat('*', strlen($password)) . "\n\n";

$configs = [
    ['name' => 'SSL without certificate validation (recommended)', 'string' => "{{$host}:{$port}/ssl/novalidate-cert}INBOX"],
    ['name' => 'SSL with certificate validation', 'string' => "{{$host}:{$port}/ssl}INBOX"],
    ['name' => 'TLS without certificate validation', 'string' => "{{$host}:143/tls/novalidate-cert}INBOX"],
    ['name' => 'TLS with certificate validation', 'string' => "{{$host}:143/tls}INBOX"],
];

$worked = false;

foreach ($configs as $config) {
    echo "   Testing: {$config['name']}\n";
    echo "   Connection string: {$config['string']}\n";
    
    // Clear any previous errors
    imap_errors();
    imap_alerts();
    
    $conn = @imap_open($config['string'], $email, $password, OP_HALFOPEN);
    
    if ($conn) {
        echo "   ✅ SUCCESS! This configuration works!\n\n";
        
        // Try to get some info
        $mailboxes = @imap_list($conn, $config['string'], '*');
        if ($mailboxes) {
            echo "   Found " . count($mailboxes) . " mailbox(es)\n";
        }
        
        imap_close($conn);
        $worked = true;
        break;
    } else {
        $error = imap_last_error();
        echo "   ❌ Failed: " . ($error ?: 'Unknown error') . "\n\n";
        
        // Show all errors
        $errors = imap_errors();
        if ($errors && is_array($errors)) {
            foreach ($errors as $err) {
                if (trim($err)) {
                    echo "      Error detail: {$err}\n";
                }
            }
        }
        echo "\n";
    }
}

// Summary
echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n\n";

if ($worked) {
    echo "✅ Connection successful!\n";
    echo "Your server CAN connect to the IMAP server.\n";
    echo "If the admin panel still doesn't work, check:\n";
    echo "  - Password stored correctly in database\n";
    echo "  - Settings match the working configuration above\n";
    echo "  - Laravel logs for any application errors\n";
} else {
    echo "❌ All connection attempts failed!\n\n";
    echo "Possible issues:\n";
    echo "  1. Server firewall blocking outbound IMAP connections (MOST LIKELY)\n";
    echo "     → Contact hosting provider to allow outbound port 993\n\n";
    echo "  2. Wrong host/port/credentials\n";
    echo "     → Double-check settings match your Mac Mail configuration\n\n";
    echo "  3. IMAP not enabled for this email account\n";
    echo "     → Check in cPanel Email Accounts → Configure Email Client\n\n";
    echo "  4. Network restrictions on shared hosting\n";
    echo "     → Ask hosting provider about IMAP connection restrictions\n\n";
    echo "\nNext Steps:\n";
    echo "  1. Compare these settings with your Mac Mail settings\n";
    echo "  2. Contact hosting provider about firewall/IMAP restrictions\n";
    echo "  3. Verify IMAP is enabled in cPanel for this email account\n";
}

// Check for common error patterns
$allErrors = imap_errors();
if ($allErrors && is_array($allErrors)) {
    foreach ($allErrors as $err) {
        $errLower = strtolower($err);
        if (strpos($errLower, 'network') !== false || strpos($errLower, 'unreachable') !== false) {
            echo "\n⚠️  NETWORK ERROR DETECTED:\n";
            echo "   The server cannot reach the IMAP server.\n";
            echo "   This is likely a firewall issue.\n";
            echo "   Contact your hosting provider!\n";
            break;
        }
        if (strpos($errLower, 'authentication') !== false || strpos($errLower, 'login') !== false) {
            echo "\n⚠️  AUTHENTICATION ERROR DETECTED:\n";
            echo "   Check your email and password.\n";
            echo "   Make sure password is exactly: Enter0text@\n";
            break;
        }
        if (strpos($errLower, 'connection refused') !== false) {
            echo "\n⚠️  CONNECTION REFUSED:\n";
            echo "   The IMAP server is not accepting connections on this port.\n";
            echo "   Verify the port and host are correct.\n";
            break;
        }
    }
}

echo "\n========================================\n";
echo "Test Complete\n";
echo "========================================\n";

// Clean up
imap_errors();
imap_alerts();
