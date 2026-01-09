# Debug: Email Works in Mac Mail But Not on Website

## 🔍 Common Reasons Why It Works on Mac But Not Server

### 1. **Server Firewall Blocking Outbound IMAP** (Most Likely)

**Problem:** Your server cannot make outbound connections to the IMAP server, even though your Mac can.

**Symptoms:**
- Works perfectly in Mac Mail app
- Fails on server with "Connection refused" or "Network unreachable"
- Server is on shared hosting (often has firewall restrictions)

**Solution:**
- Contact your hosting provider
- Ask them to allow outbound IMAP connections on port 993
- They may need to whitelist `check-outpay.com` for IMAP

### 2. **PHP IMAP Extension Not Installed**

**Problem:** PHP's IMAP extension is missing on the server.

**Check:**
```bash
php -m | grep imap
# or
php -r "echo function_exists('imap_open') ? '✅ Installed' : '❌ Not installed';"
```

**Solution:**
- Contact hosting to install `php-imap` extension
- May need to enable it in PHP configuration

### 3. **Different SSL/TLS Settings**

**Problem:** Mac Mail auto-negotiates, but PHP IMAP needs exact settings.

**Mac Mail might be using:**
- Auto-detect encryption
- Certificate validation disabled by default
- Different SSL/TLS negotiation

**Your server needs:**
- Exact encryption type: `SSL` (not auto)
- Port: `993` (not auto-negotiate)
- Certificate validation: Usually disabled (`/novalidate-cert`)

**Fix:** Make sure your settings are:
- Host: `check-outpay.com`
- Port: `993`
- Encryption: `SSL` (not TLS, not auto)
- Validate Certificate: **Unchecked** (false)

### 4. **Password Encoding/Encryption Issues**

**Problem:** Special characters in password (`@` symbol) might be encoded differently.

**Check:**
- Is password stored as `Enter0text@` with the `@` symbol?
- Or stored as `Enter0text%40` (URL encoded)?
- Spaces might be converted to `+` or `%20`

**Fix:**
1. Clear the password field in admin panel
2. Re-enter password exactly: `Enter0text@`
3. Make sure no spaces before or after
4. Save and test again

### 5. **Server Network Restrictions**

**Problem:** Shared hosting often blocks certain outbound ports/protocols.

**Check with hosting:**
- Can the server make outbound IMAP connections?
- Are there firewall rules blocking port 993?
- Is IMAP traffic allowed?

### 6. **Certificate Validation**

**Problem:** Server has stricter certificate validation than Mac Mail.

**Fix:** Make sure "Validate Certificate" is **unchecked** (false) in your email account settings.

### 7. **Different Authentication Method**

**Problem:** Mac Mail might use OAuth or different auth method.

**Check:** Mac Mail settings - is it using:
- Password authentication? ✅ (What we need)
- OAuth? ❌ (Not supported by PHP IMAP)
- Other? ❌ (May not work)

## 🔧 Step-by-Step Debugging

### Step 1: Test Connection from Server Terminal

SSH into your server and test directly:

```bash
php -r "\$conn = @imap_open('{check-outpay.com:993/ssl/novalidate-cert}INBOX', 'notify@check-outpay.com', 'Enter0text@'); echo \$conn ? '✅ Works on server!' : '❌ Failed: ' . imap_last_error();"
```

**If this works:** The issue is in your Laravel code/configuration  
**If this fails:** The issue is server-side (firewall, IMAP extension, network)

### Step 2: Check PHP IMAP Extension

```bash
php -m | grep imap
```

**If no output:** Extension is not installed - contact hosting

### Step 3: Check Server Logs

Look at Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

Then try connecting from admin panel and see what errors appear.

### Step 4: Verify Exact Settings Match Mac Mail

1. Open Mac Mail
2. Go to Mail → Settings → Accounts
3. Select `notify@check-outpay.com`
4. Click "Server Settings" or "Advanced"
5. Note the exact settings:
   - Server name: `check-outpay.com` ✅
   - Port: `993` ✅
   - Use SSL: `Yes` ✅
   - Authentication: `Password` ✅

Compare these with your admin panel settings - they should match exactly.

### Step 5: Test with Simple PHP Script

Create a test file on server:

```php
<?php
// test-imap.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing IMAP Connection...\n\n";

// Test 1: Check if IMAP extension exists
if (!function_exists('imap_open')) {
    die("❌ PHP IMAP extension is NOT installed!\n");
}
echo "✅ PHP IMAP extension is installed\n\n";

// Test 2: Try to connect
$host = 'check-outpay.com';
$port = 993;
$user = 'notify@check-outpay.com';
$pass = 'Enter0text@';

echo "Connecting to: {$host}:{$port}\n";
echo "Username: {$user}\n";
echo "Password: " . str_repeat('*', strlen($pass)) . "\n\n";

$connectionString = "{{$host}:{$port}/ssl/novalidate-cert}INBOX";

echo "Connection string: {$connectionString}\n\n";

$conn = @imap_open($connectionString, $user, $pass, OP_HALFOPEN);

if ($conn) {
    echo "✅ SUCCESS! Connection works!\n";
    imap_close($conn);
} else {
    $error = imap_last_error();
    echo "❌ FAILED!\n";
    echo "Error: " . ($error ?: 'Unknown error') . "\n\n";
    
    // Show all IMAP errors
    $errors = imap_errors();
    if ($errors) {
        echo "All errors:\n";
        foreach ($errors as $err) {
            echo "  - {$err}\n";
        }
    }
}

// Clear errors
imap_errors();
imap_alerts();
?>
```

Upload this to your server and access via browser or run via terminal:
```bash
php test-imap.php
```

## 🎯 Most Likely Solution

Based on "works on Mac but not server", the **most likely issue** is:

**Server firewall blocking outbound IMAP connections**

**Action:** Contact your hosting provider and ask:

```
Subject: Outbound IMAP Connection Blocked on Port 993

Hello,

I'm trying to connect to my IMAP email server from my shared hosting account, but connections are failing.

Server: premium340.web-hosting.com
IMAP Server: check-outpay.com:993
Email: notify@check-outpay.com

The connection works fine from my local Mac, but fails from the server, which suggests the server firewall is blocking outbound IMAP connections.

Could you please:
1. Allow outbound IMAP connections on port 993
2. Whitelist check-outpay.com for IMAP access
3. Verify PHP IMAP extension is installed and enabled

Thank you!
```

## 📋 Quick Checklist

- [ ] PHP IMAP extension installed? (`php -m | grep imap`)
- [ ] Settings match Mac Mail exactly? (host, port, SSL)
- [ ] Password entered correctly? (`Enter0text@` with @ symbol)
- [ ] Validate Certificate unchecked?
- [ ] Tested from server terminal? (not just admin panel)
- [ ] Checked server logs for errors?
- [ ] Contacted hosting about firewall?

## 🔍 Get Detailed Error

Add this to your email account test to see the exact error:

In `app/Models/EmailAccount.php`, the `testConnection()` method already logs errors. Check:
- `storage/logs/laravel.log` for detailed error messages
- Browser console if testing via admin panel

---

**Next Step:** Run the terminal test command from Step 1 above - that will tell us if it's a server issue or code issue!
