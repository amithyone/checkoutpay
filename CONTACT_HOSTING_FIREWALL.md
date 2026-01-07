# Contact Hosting Provider - Firewall Issue

## 🚨 Problem

Your server cannot connect to Gmail's IMAP server due to firewall restrictions.

**Error:** `Network is unreachable` or `Connection failed`

## 📧 Email Template for Hosting Support

Copy and send this to your hosting provider's support:

---

**Subject:** Request to Allow Outbound IMAP Connections

Hello,

I need to allow outbound IMAP connections from my server for email monitoring functionality.

**Server Details:**
- Domain: check-outpay.com
- Server: premium340
- Username: checzspw

**Required Changes:**
1. Allow outbound IMAP connections on:
   - Port 993 (SSL/IMAP)
   - Port 143 (TLS/IMAP - alternative)

2. Whitelist/allow connections to:
   - `imap.gmail.com`
   - `*.gmail.com` (if possible)

**Purpose:**
My application needs to monitor Gmail inbox for bank transfer notifications in real-time using IMAP protocol.

**Current Issue:**
When attempting to connect, I receive "Network is unreachable" error, indicating the firewall is blocking outbound IMAP connections.

Please let me know if you need any additional information.

Thank you!

---

## 🔧 Alternative: Check cPanel Firewall Settings

If you have access to cPanel firewall settings:

1. Go to: **cPanel → Security → Firewall** (or similar)
2. Look for outbound connection rules
3. Add rule to allow port 993
4. Save changes

## 🎯 Quick Test After Firewall Fix

Once firewall is opened, test:

```bash
# Test connection
php artisan email:test-connection fastifysales@gmail.com

# Or use native PHP test
php -r "
\$conn = @imap_open('{imap.gmail.com:993/ssl/novalidate-cert}INBOX', 'fastifysales@gmail.com', 'juqdqfdy mqks txgu');
echo \$conn ? '✅ Works!' : '❌ Failed: ' . imap_last_error();
"
```

## ⏰ Expected Timeline

- **Hosting Support Response:** Usually 24-48 hours
- **Firewall Update:** Can be done immediately if they have access
- **Testing:** Once firewall is open, connection should work immediately

---

**This is a server configuration issue that only your hosting provider can fix.** 📞
