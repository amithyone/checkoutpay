# Fix Email Connection Issues on Server

## 🔴 Connection Failed Error

If you're getting "Connection failed" even with correct credentials, it's likely a **server-side issue**.

## ✅ Step 1: Check PHP IMAP Extension

The server needs the PHP IMAP extension installed:

```bash
# Check if IMAP extension is installed
php -m | grep imap

# If not installed, install it (varies by system)
# For Ubuntu/Debian:
sudo apt-get install php-imap
sudo systemctl restart php-fpm  # or apache2

# For cPanel servers, enable it in:
# cPanel → Select PHP Version → Extensions → Enable "imap"
```

## ✅ Step 2: Test Connection from Server

Run this command on your server:

```bash
cd /home/checzspw/public_html

# Test email connection
php artisan email:test-connection fastifysales@gmail.com
```

This will show:
- ✅ If IMAP extension is installed
- ✅ Detailed connection error
- ✅ What's actually failing

## ✅ Step 3: Check Server Logs

```bash
# View detailed error logs
tail -f storage/logs/laravel.log | grep -i "email\|connection\|imap"
```

## ✅ Step 4: Test Network Connectivity

Check if server can reach Gmail:

```bash
# Test IMAP port connectivity
telnet imap.gmail.com 993

# Or use openssl
openssl s_client -connect imap.gmail.com:993

# If connection works, you should see SSL certificate info
```

## ✅ Step 5: Check Firewall

Some servers block outbound IMAP connections:

```bash
# Check if port 993 is accessible
nc -zv imap.gmail.com 993

# Should show: Connection to imap.gmail.com 993 port [tcp/imaps] succeeded!
```

## 🔧 Common Server Issues

### Issue 1: PHP IMAP Extension Not Installed

**Symptoms:**
- Connection fails immediately
- Error mentions "imap" or "extension"

**Fix:**
- Enable IMAP extension in cPanel → Select PHP Version
- Or install via SSH: `sudo apt-get install php-imap`

### Issue 2: Firewall Blocking Outbound Connections

**Symptoms:**
- Connection timeout
- "Connection refused" error

**Fix:**
- Contact hosting provider to allow outbound connections on port 993
- Or use a different port (587 with TLS)

### Issue 3: SSL Certificate Issues

**Symptoms:**
- SSL handshake errors
- Certificate validation errors

**Fix:**
- Make sure "Validate Certificate" is **unchecked**
- Update server's CA certificates: `sudo update-ca-certificates`

## 🎯 Quick Diagnostic Script

Run this on your server to check everything:

```bash
cd /home/checzspw/public_html

echo "=== Checking PHP IMAP Extension ==="
php -m | grep imap || echo "❌ IMAP extension NOT installed"

echo -e "\n=== Testing Network Connectivity ==="
timeout 5 bash -c 'cat < /dev/null > /dev/tcp/imap.gmail.com/993' && echo "✅ Port 993 accessible" || echo "❌ Port 993 blocked"

echo -e "\n=== Testing Email Connection ==="
php artisan email:test-connection fastifysales@gmail.com

echo -e "\n=== Recent Email Errors ==="
tail -20 storage/logs/laravel.log | grep -i "email\|connection"
```

## 📋 Checklist

- [ ] PHP IMAP extension installed (`php -m | grep imap`)
- [ ] Port 993 accessible from server (`telnet imap.gmail.com 993`)
- [ ] Firewall allows outbound IMAP connections
- [ ] Gmail IMAP enabled in account settings
- [ ] Using App Password (not regular password)
- [ ] Correct settings: imap.gmail.com:993 with SSL

## 🚨 If Still Not Working

1. **Check cPanel PHP Settings:**
   - Go to: cPanel → Select PHP Version
   - Make sure IMAP extension is enabled
   - Save and restart PHP

2. **Contact Hosting Provider:**
   - Ask if outbound IMAP connections are allowed
   - Request to open port 993 for outbound connections
   - Verify PHP IMAP extension is available

3. **Try Alternative Port:**
   - Port 143 with TLS (less secure, but might work)
   - Port 587 with TLS (SMTP, but some servers allow it)

---

**Most common issue: PHP IMAP extension not installed on server!** 🔧
