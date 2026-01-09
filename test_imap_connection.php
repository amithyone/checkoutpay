<?php
/**
 * Test IMAP Connection Script
 * Run this on your server to find the correct IMAP settings
 * 
 * Usage: php test_imap_connection.php
 */

echo "🔍 Testing IMAP Connection for notify@check-outpay.com\n";
echo "=====================================================\n\n";

$email = 'notify@check-outpay.com';
$password = 'Enter0text';

// Check if IMAP extension is installed
echo "1. Checking PHP IMAP Extension...\n";
if (!function_exists('imap_open')) {
    echo "❌ PHP IMAP extension is NOT installed!\n";
    echo "   Contact your hosting provider to install php-imap extension.\n\n";
    exit(1);
}
echo "✅ PHP IMAP extension is installed.\n\n";

// Test different IMAP server configurations
$configs = [
    // Most common for cPanel-hosted domains
    ['host' => 'mail.check-outpay.com', 'port' => 993, 'encryption' => 'ssl', 'desc' => 'Standard cPanel IMAP (SSL)'],
    ['host' => 'mail.check-outpay.com', 'port' => 143, 'encryption' => 'tls', 'desc' => 'Standard cPanel IMAP (TLS)'],
    
    // Alternative hostnames
    ['host' => 'imap.check-outpay.com', 'port' => 993, 'encryption' => 'ssl', 'desc' => 'Alternative IMAP host (SSL)'],
    ['host' => 'imap.check-outpay.com', 'port' => 143, 'encryption' => 'tls', 'desc' => 'Alternative IMAP host (TLS)'],
    
    // Using server hostname (sometimes works)
    ['host' => 'premium340.web-hosting.com', 'port' => 993, 'encryption' => 'ssl', 'desc' => 'Server hostname with IMAP port (SSL)'],
    ['host' => 'premium340.web-hosting.com', 'port' => 143, 'encryption' => 'tls', 'desc' => 'Server hostname with IMAP port (TLS)'],
    
    // Just the domain (sometimes works)
    ['host' => 'check-outpay.com', 'port' => 993, 'encryption' => 'ssl', 'desc' => 'Domain only (SSL)'],
    ['host' => 'check-outpay.com', 'port' => 143, 'encryption' => 'tls', 'desc' => 'Domain only (TLS)'],
];

echo "2. Testing Different IMAP Configurations...\n";
echo "===========================================\n\n";

$workingConfigs = [];

foreach ($configs as $index => $config) {
    $testNum = $index + 1;
    echo "[$testNum/{count($configs)}] Testing: {$config['desc']}\n";
    echo "   Host: {$config['host']}:{$config['port']} ({$config['encryption']})\n";
    
    // Build connection string
    $validateCert = '/novalidate-cert'; // Skip certificate validation for testing
    $folder = 'INBOX';
    $connectionString = "{{$config['host']}:{$config['port']}/{$config['encryption']}{$validateCert}}{$folder}";
    
    echo "   Connection string: " . str_replace($password, '***', $connectionString) . "\n";
    
    // Try to connect
    $connection = @imap_open($connectionString, $email, $password, OP_HALFOPEN);
    
    if ($connection) {
        echo "   ✅ SUCCESS! This configuration works!\n";
        imap_close($connection);
        $workingConfigs[] = $config;
        echo "\n";
    } else {
        $error = imap_last_error();
        if (empty($error)) {
            $error = 'Unknown error';
        }
        echo "   ❌ Failed: " . trim($error) . "\n\n";
    }
    
    // Clear IMAP errors for next test
    imap_errors();
    imap_alerts();
}

echo "\n3. Results Summary\n";
echo "==================\n\n";

if (empty($workingConfigs)) {
    echo "❌ No working configurations found!\n\n";
    echo "Possible issues:\n";
    echo "1. Firewall blocking outbound IMAP connections\n";
    echo "2. Wrong IMAP hostname - check cPanel → Email Accounts → Configure Email Client\n";
    echo "3. Wrong password\n";
    echo "4. IMAP not enabled for this email account\n";
    echo "5. Server firewall restrictions\n\n";
    
    echo "Next steps:\n";
    echo "1. Log in to cPanel\n";
    echo "2. Go to Email Accounts\n";
    echo "3. Click on notify@check-outpay.com → Configure Email Client\n";
    echo "4. Look for 'Incoming Server (IMAP)' settings\n";
    echo "5. Copy the server name and port shown there\n\n";
    
    echo "Or contact your hosting provider and ask:\n";
    echo "- What is the IMAP server hostname for check-outpay.com?\n";
    echo "- What port should I use? (usually 993 or 143)\n";
    echo "- What encryption? (usually SSL for 993, TLS for 143)\n";
} else {
    echo "✅ Found " . count($workingConfigs) . " working configuration(s):\n\n";
    foreach ($workingConfigs as $config) {
        echo "   Configuration: {$config['desc']}\n";
        echo "   Host: {$config['host']}\n";
        echo "   Port: {$config['port']}\n";
        echo "   Encryption: {$config['encryption']}\n";
        echo "   Folder: INBOX\n\n";
    }
    
    // Recommend the first working config
    $best = $workingConfigs[0];
    echo "📋 Recommended Settings (use in your admin panel):\n";
    echo "   IMAP Host: {$best['host']}\n";
    echo "   Port: {$best['port']}\n";
    echo "   Encryption: " . strtoupper($best['encryption']) . "\n";
    echo "   Folder: INBOX\n";
    echo "   Validate Certificate: No (unchecked)\n";
}

echo "\n4. Additional Information\n";
echo "========================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "IMAP Extension: " . (function_exists('imap_open') ? 'Installed' : 'Not Installed') . "\n";

// Try to get server hostname
if (function_exists('gethostname')) {
    echo "Server Hostname: " . gethostname() . "\n";
}

echo "\nDone!\n";
