# Fix "Network is unreachable" Error

## 🔴 Error
```
Can't connect to imap.gmail.com,993: Network is unreachable
```

## ✅ This Means:
The server **cannot reach Gmail's IMAP server** due to:
- Firewall blocking outbound connections on port 993
- Network restrictions
- Server-level firewall rules

## 🔧 Solutions

### Solution 1: Contact Hosting Provider (Recommended)

Contact your hosting provider (cPanel support) and ask them to:

1. **Allow outbound IMAP connections**
   - Port 993 (SSL/IMAP)
   - Port 143 (TLS/IMAP - alternative)
   - Port 587 (TLS/SMTP - alternative)

2. **Whitelist Gmail servers:**
   - `imap.gmail.com`
   - `*.gmail.com`

### Solution 2: Use Alternative Port (If Available)

Try port 143 with TLS instead:

```bash
# Test port 143
php -r "
\$conn = @imap_open('{imap.gmail.com:143/tls/novalidate-cert}INBOX', 'fastifysales@gmail.com', 'juqdqfdy mqks txgu');
echo \$conn ? '✅ Works!' : '❌ Failed: ' . imap_last_error();
"
```

If this works, update your email account settings:
- Port: `143`
- Encryption: `TLS`

### Solution 3: Check Server Firewall Rules

If you have SSH/root access:

```bash
# Check firewall status
sudo iptables -L -n | grep 993

# Allow outbound IMAP (if you have root access)
sudo iptables -A OUTPUT -p tcp --dport 993 -j ACCEPT
sudo iptables -A OUTPUT -p tcp --dport 143 -j ACCEPT
```

### Solution 4: Use SMTP Instead (Workaround)

If IMAP ports are blocked, you could use SMTP to send emails, but this won't work for **receiving** emails (which we need for payment monitoring).

## 🚨 About Regular Password

**Gmail does NOT allow regular passwords for IMAP anymore.**

- ❌ Regular password won't work
- ✅ Must use App Password (16-character code)
- ✅ App Passwords are more secure anyway

Even if we could use regular passwords, the network issue would still prevent connection.

## 📋 Action Items

1. **Contact hosting provider** - Ask them to allow outbound IMAP connections
2. **Try alternative port** - Test port 143 with TLS
3. **Check firewall** - If you have access, verify firewall rules
4. **Wait for fix** - Once firewall is opened, connection should work

## 🎯 Quick Test After Firewall Fix

Once your hosting provider opens the firewall:

```bash
# Test connection
php artisan email:test-connection fastifysales@gmail.com

# Or use the PHP script
php test_imap.php
```

---

**The issue is server firewall, not credentials! Contact hosting support.** 🔧
