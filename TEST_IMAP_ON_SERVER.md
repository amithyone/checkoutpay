# Test IMAP Connection on Shared Hosting

## 🚀 Quick Test Commands

### Option 1: Using the Test Script (Recommended)

1. **Upload the test script** to your server:
   ```bash
   # Upload test_imap_connection.php to your server (via FTP/cPanel File Manager)
   # Place it in your public_html or root directory
   ```

2. **Run via SSH/Terminal** (if you have access):
   ```bash
   cd /home/username/public_html  # or wherever you uploaded it
   php test_imap_connection.php
   ```

3. **Or run via web browser**:
   ```
   https://your-domain.com/test_imap_connection.php
   ```
   (Note: This will show the password in the output, so only do this if you can delete the file immediately after)

### Option 2: Quick One-Liner Tests

If you have SSH/terminal access, try these commands one by one:

#### Test 1: Standard cPanel IMAP (most common)
```bash
php -r "\$conn = @imap_open('{mail.check-outpay.com:993/ssl/novalidate-cert}INBOX', 'notify@check-outpay.com', 'Enter0text'); echo \$conn ? '✅ WORKS! Use: mail.check-outpay.com:993/SSL' : '❌ Failed: ' . imap_last_error();"
```

#### Test 2: Alternative TLS port
```bash
php -r "\$conn = @imap_open('{mail.check-outpay.com:143/tls/novalidate-cert}INBOX', 'notify@check-outpay.com', 'Enter0text'); echo \$conn ? '✅ WORKS! Use: mail.check-outpay.com:143/TLS' : '❌ Failed: ' . imap_last_error();"
```

#### Test 3: Alternative hostname
```bash
php -r "\$conn = @imap_open('{imap.check-outpay.com:993/ssl/novalidate-cert}INBOX', 'notify@check-outpay.com', 'Enter0text'); echo \$conn ? '✅ WORKS! Use: imap.check-outpay.com:993/SSL' : '❌ Failed: ' . imap_last_error();"
```

#### Test 4: Server hostname with IMAP port
```bash
php -r "\$conn = @imap_open('{premium340.web-hosting.com:993/ssl/novalidate-cert}INBOX', 'notify@check-outpay.com', 'Enter0text'); echo \$conn ? '✅ WORKS! Use: premium340.web-hosting.com:993/SSL' : '❌ Failed: ' . imap_last_error();"
```

#### Test 5: Domain only
```bash
php -r "\$conn = @imap_open('{check-outpay.com:993/ssl/novalidate-cert}INBOX', 'notify@check-outpay.com', 'Enter0text'); echo \$conn ? '✅ WORKS! Use: check-outpay.com:993/SSL' : '❌ Failed: ' . imap_last_error();"
```

### Option 3: Check PHP IMAP Extension First

Before testing connections, verify IMAP is installed:

```bash
php -m | grep -i imap
```

Or:

```bash
php -r "echo function_exists('imap_open') ? '✅ IMAP installed' : '❌ IMAP NOT installed';"
```

## 📋 Step-by-Step Guide

### Step 1: Access Terminal/SSH

**Via cPanel:**
1. Log in to cPanel
2. Look for **"Terminal"** or **"SSH Access"** in the Advanced section
3. Click to open terminal

**Or via SSH client:**
```bash
ssh username@premium340.web-hosting.com
# or
ssh username@your-domain.com
```

### Step 2: Navigate to Your Project Directory

```bash
cd ~/public_html
# or wherever your Laravel project is
# Usually: cd ~/public_html/your-project
```

### Step 3: Check IMAP Extension

```bash
php -m | grep imap
```

**If you see `imap` in the list:** ✅ Extension is installed  
**If you don't see it:** ❌ Contact hosting to install it

### Step 4: Test Connections

Copy and paste each test command one at a time. When you find one that shows "✅ WORKS!", note down those settings!

### Step 5: Find Settings in cPanel (Alternative Method)

If terminal doesn't work or you prefer GUI:

1. **Log in to cPanel**
2. Go to **Email Accounts**
3. Click on **notify@check-outpay.com**
4. Click **"Configure Email Client"** or **"Connect Devices"**
5. Look for **"Incoming Server (IMAP)"** section
6. Copy the settings shown:
   - **Server:** (usually `mail.check-outpay.com`)
   - **Port:** (usually `993` or `143`)
   - **Security:** (usually `SSL` or `TLS`)

## 🎯 What to Look For

When a test **works**, you'll see:
```
✅ WORKS! Use: mail.check-outpay.com:993/SSL
```

When it **fails**, you'll see something like:
```
❌ Failed: Can't connect to mail.check-outpay.com,993: Connection refused
❌ Failed: Can't connect to mail.check-outpay.com,993: Network is unreachable
❌ Failed: [AUTHENTICATIONFAILED] Invalid credentials
```

## 🚨 Common Errors and What They Mean

| Error | Meaning | Solution |
|-------|---------|----------|
| `Connection refused` | Server not listening on that port | Try different port or host |
| `Network is unreachable` | Firewall blocking | Contact hosting |
| `Invalid credentials` | Wrong password/username | Check password |
| `Cannot connect` | Wrong host | Try different hostname |
| `Timeout` | Server not responding | Firewall or wrong host |

## 📝 After Finding Working Settings

Once you find a configuration that works:

1. **Note down the settings:**
   - Host: `mail.check-outpay.com` (example)
   - Port: `993` (example)
   - Encryption: `SSL` (example)

2. **Update in Admin Panel:**
   - Go to Admin Panel → Email Accounts
   - Edit `notify@check-outpay.com`
   - Update Host, Port, and Encryption
   - Click "Test Connection"
   - Save if successful!

3. **Delete test file** (if uploaded via web):
   ```bash
   rm test_imap_connection.php
   ```

## 🔒 Security Note

⚠️ **Important:** The test script and commands contain your password. After testing:
- Delete `test_imap_connection.php` from the server
- Clear terminal history if on shared server
- Don't leave these files accessible via web

## 💡 Tips

1. **Start with the most common:** `mail.check-outpay.com:993/SSL` (works for 90% of cPanel hosts)
2. **Try variations systematically:** Don't give up after the first few fail
3. **Check cPanel first:** The "Configure Email Client" option often shows the correct settings
4. **Contact hosting if stuck:** They can tell you the exact IMAP settings for your domain

---

**Good luck! Once you find the working configuration, updating your email account settings will be easy.** 🎉
