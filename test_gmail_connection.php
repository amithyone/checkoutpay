<?php
/**
 * Standalone Gmail IMAP Connection Test
 * 
 * Run this file directly: php test_gmail_connection.php
 * Or upload to server and access via browser
 */

// Gmail IMAP Settings
$host = 'imap.gmail.com';
$port = 993;
$email = 'fastifysales@gmail.com';
$password = 'hftp gysf vnnl iqlj'; // Your App Password (remove spaces if any)
$folder = 'INBOX';

echo "========================================\n";
echo "Gmail IMAP Connection Test\n";
echo "========================================\n\n";

echo "Testing connection to: {$email}\n";
echo "Host: {$host}:{$port}\n";
echo "Encryption: SSL\n\n";

// Check if IMAP extension is available
if (!function_exists('imap_open')) {
    die("❌ ERROR: PHP IMAP extension is not installed!\n");
}
echo "✅ PHP IMAP extension is available\n\n";

// Test connection
echo "Attempting connection...\n";
$connectionString = "{{$host}:{$port}/ssl/novalidate-cert}{$folder}";

$connection = @imap_open($connectionString, $email, $password, OP_HALFOPEN);

if ($connection) {
    echo "\n✅✅✅ CONNECTION SUCCESSFUL! ✅✅✅\n\n";
    
    // Get mailbox info
    $mailboxInfo = imap_status($connection, "{{$host}:{$port}/ssl/novalidate-cert}{$folder}", SA_ALL);
    
    echo "Mailbox Information:\n";
    echo "- Messages: " . ($mailboxInfo->messages ?? 'N/A') . "\n";
    echo "- Recent: " . ($mailboxInfo->recent ?? 'N/A') . "\n";
    echo "- Unseen: " . ($mailboxInfo->unseen ?? 'N/A') . "\n";
    
    // Test reading a message
    echo "\nTesting message retrieval...\n";
    $messages = imap_search($connection, 'ALL');
    if ($messages) {
        echo "✅ Found " . count($messages) . " message(s) in inbox\n";
    } else {
        echo "ℹ️ No messages found (or search failed)\n";
    }
    
    imap_close($connection);
    
    echo "\n✅ Your credentials are CORRECT!\n";
    echo "The issue is with the server firewall blocking connections.\n";
    echo "Once firewall is opened, your email account will work.\n";
    
} else {
    $error = imap_last_error();
    echo "\n❌❌❌ CONNECTION FAILED ❌❌❌\n\n";
    echo "Error: " . ($error ?: 'Unknown error') . "\n\n";
    
    // Provide helpful diagnostics
    if (strpos($error, 'authentication') !== false || strpos($error, 'login') !== false) {
        echo "🔍 DIAGNOSIS: Authentication failed\n";
        echo "Possible causes:\n";
        echo "1. Wrong email address\n";
        echo "2. Wrong App Password (make sure you're using 16-character App Password, not regular password)\n";
        echo "3. IMAP not enabled in Gmail settings\n";
        echo "4. 2-Step Verification not enabled\n\n";
        echo "Fix:\n";
        echo "- Go to: https://myaccount.google.com/apppasswords\n";
        echo "- Generate a new App Password\n";
        echo "- Make sure IMAP is enabled: https://mail.google.com/mail/u/0/#settings/general\n";
    } elseif (strpos($error, 'unreachable') !== false || strpos($error, 'Network') !== false) {
        echo "🔍 DIAGNOSIS: Network/Firewall issue\n";
        echo "The server cannot reach Gmail's IMAP server.\n";
        echo "This is a firewall/network restriction.\n";
        echo "Contact your hosting provider to allow outbound IMAP connections.\n";
    } elseif (strpos($error, 'certificate') !== false || strpos($error, 'SSL') !== false) {
        echo "🔍 DIAGNOSIS: SSL/Certificate issue\n";
        echo "Try with /novalidate-cert flag (already included)\n";
    } else {
        echo "🔍 Check the error message above for details\n";
    }
}

echo "\n========================================\n";
?>
