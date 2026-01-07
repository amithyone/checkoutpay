# Test Email Connection on Server

## ✅ IMAP Extension is Installed

Good! The PHP IMAP extension is installed. Now let's test the connection.

## 🔧 Step 1: Pull Latest Changes

```bash
cd /home/checzspw/public_html
git pull origin main
```

## 🔧 Step 2: Test Network Connectivity

Since `telnet` isn't available, use `openssl`:

```bash
# Test SSL connection to Gmail
timeout 10 openssl s_client -connect imap.gmail.com:993 -quiet

# If this works, you'll see SSL certificate info
# Press Ctrl+C to exit
```

## 🔧 Step 3: Test Email Connection Command

After pulling, test the connection:

```bash
php artisan email:test-connection fastifysales@gmail.com
```

## 🔧 Step 4: Check Detailed Error Logs

```bash
# View full error details
tail -100 storage/logs/laravel.log | grep -A 10 "Email connection failed"
```

## 🚨 If Connection Still Fails

The error "connection failed" usually means:

1. **Firewall blocking outbound connections**
   - Contact hosting provider
   - Ask them to allow outbound connections on port 993

2. **Gmail blocking the connection**
   - Check Gmail security settings
   - Make sure "Less secure app access" is enabled (if using regular password)
   - Or use App Password (recommended)

3. **SSL/TLS handshake issues**
   - Try with "Validate Certificate" unchecked
   - Update server CA certificates

## 🎯 Alternative: Test with PHP Script

Create a test file to see the exact error:

```bash
cat > /home/checzspw/public_html/test_imap.php << 'EOF'
<?php
$host = 'imap.gmail.com';
$port = 993;
$username = 'fastifysales@gmail.com';
$password = 'juqdqfdy mqks txgu'; // Remove spaces

echo "Testing connection to {$host}:{$port}\n";
echo "Username: {$username}\n\n";

$connection = @imap_open(
    "{{$host}:{$port}/ssl/novalidate-cert}INBOX",
    $username,
    $password
);

if ($connection) {
    echo "✅ Connection successful!\n";
    imap_close($connection);
} else {
    echo "❌ Connection failed!\n";
    echo "Error: " . imap_last_error() . "\n";
}
EOF

# Run the test
php test_imap.php

# Clean up
rm test_imap.php
```

This will show the exact error message from PHP's IMAP library.

## 📋 What to Check

1. **Gmail Settings:**
   - IMAP enabled: https://mail.google.com/mail/u/0/#settings/general
   - 2-Step Verification enabled
   - App Password generated

2. **Server Settings:**
   - PHP IMAP extension installed ✅ (confirmed)
   - Outbound port 993 accessible (needs testing)
   - Firewall allows IMAP connections

3. **Credentials:**
   - Email: fastifysales@gmail.com
   - Password: App Password (16 characters, no spaces)

---

**Run the PHP test script above to see the exact error!** 🔍
