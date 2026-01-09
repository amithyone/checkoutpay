<?php
// Quick IMAP Test
// Run: php quick_imap_test.php

$host = 'check-outpay.com';
$port = 993;
$email = 'notify@check-outpay.com';
$password = 'Enter0text@';

echo "Testing IMAP connection...\n";
echo "Host: {$host}:{$port}\n";
echo "Email: {$email}\n\n";

// Check if IMAP extension exists
if (!function_exists('imap_open')) {
    echo "❌ PHP IMAP extension is NOT installed!\n";
    exit(1);
}
echo "✅ PHP IMAP extension is installed\n\n";

// Try to connect
$connectionString = "{{$host}:{$port}/ssl/novalidate-cert}INBOX";
echo "Connecting to: {$connectionString}\n";

$conn = @imap_open($connectionString, $email, $password, OP_HALFOPEN);

if ($conn) {
    echo "✅ SUCCESS! Connection works on server!\n";
    echo "\nYour server CAN connect to IMAP.\n";
    echo "If admin panel doesn't work, check:\n";
    echo "  1. Password stored correctly in database\n";
    echo "  2. Settings in admin panel match these:\n";
    echo "     - Host: {$host}\n";
    echo "     - Port: {$port}\n";
    echo "     - Encryption: SSL\n";
    echo "     - Validate Cert: false\n";
    imap_close($conn);
} else {
    $error = imap_last_error();
    echo "❌ FAILED!\n";
    echo "Error: " . ($error ?: 'Unknown error') . "\n\n";
    
    // Show all errors
    $errors = imap_errors();
    if ($errors && is_array($errors)) {
        echo "All errors:\n";
        foreach ($errors as $err) {
            if (trim($err)) {
                echo "  - {$err}\n";
            }
        }
    }
    
    echo "\nPossible issues:\n";
    echo "  1. Server firewall blocking outbound IMAP (contact hosting)\n";
    echo "  2. Wrong credentials (double-check password: Enter0text@)\n";
    echo "  3. IMAP not enabled for this email account\n";
}

imap_errors();
imap_alerts();
