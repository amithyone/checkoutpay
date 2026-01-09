# Fix Port 465 Connection Error

## ❌ Problem

**Error:** `Connection failed to premium340.web-hosting.com:465`

**Email:** `notify@check-outpay.com`

**Issue:** Port 465 is for **SMTP (outgoing email)**, not **IMAP (incoming email)**.

## ✅ Solution

### For Custom Domain Emails (like notify@check-outpay.com)

Your email account settings need to be updated:

#### ❌ **Wrong Settings:**
- Host: `premium340.web-hosting.com`
- Port: `465`
- Encryption: `SSL`

#### ✅ **Correct Settings:**
- Host: `mail.check-outpay.com` (or `imap.check-outpay.com`)
- Port: `993` (for SSL/IMAP)
- Encryption: `SSL`
- OR
- Port: `143` (for TLS/IMAP)  
- Encryption: `TLS`

## 📋 Step-by-Step Fix

### Step 1: Find Your IMAP Server

For cPanel-hosted emails, the IMAP server is usually:
- `mail.yourdomain.com` (most common)
- `imap.yourdomain.com`

For your case (`notify@check-outpay.com`), try:
1. `mail.check-outpay.com` ← **Try this first**
2. `imap.check-outpay.com`
3. `check-outpay.com` (sometimes works)

### Step 2: Check in cPanel

1. Log in to cPanel
2. Go to **Email Accounts** → Click on `notify@check-outpay.com` → **Configure Email Client**
3. Look for **Incoming Server (IMAP)** settings
4. Copy the server name (usually `mail.check-outpay.com` or `mail.yourdomain.com`)
5. Note the port (usually **993** for SSL or **143** for TLS)

### Step 3: Update Email Account Settings

1. Go to **Admin Panel → Email Accounts**
2. Edit the account for `notify@check-outpay.com`
3. Update these fields:
   - **Host:** `mail.check-outpay.com` (or what you found in cPanel)
   - **Port:** `993` (if using SSL) or `143` (if using TLS)
   - **Encryption:** `SSL` (for port 993) or `TLS` (for port 143)
   - **Password:** `Enter0text` (verify this is correct)
4. Click **Test Connection**
5. If it works, click **Update**

## 🔍 Common Port Reference

| Port | Protocol | Purpose | Encryption |
|------|----------|---------|------------|
| **993** | IMAP | Incoming email (READ) | SSL |
| **143** | IMAP | Incoming email (READ) | TLS |
| **465** | SMTP | Outgoing email (SEND) | SSL |
| **587** | SMTP | Outgoing email (SEND) | TLS |
| **25** | SMTP | Outgoing email (SEND) | None/TLS |

**IMAP = Incoming (what we need)**  
**SMTP = Outgoing (not what we need)**

## 🧪 Quick Test

After updating settings, test the connection:

```bash
# Test with port 993 (SSL)
php -r "
\$conn = @imap_open('{mail.check-outpay.com:993/ssl/novalidate-cert}INBOX', 'notify@check-outpay.com', 'Enter0text');
echo \$conn ? '✅ Works!' : '❌ Failed: ' . imap_last_error();
"

# If port 993 doesn't work, try port 143 (TLS)
php -r "
\$conn = @imap_open('{mail.check-outpay.com:143/tls/novalidate-cert}INBOX', 'notify@check-outpay.com', 'Enter0text');
echo \$conn ? '✅ Works!' : '❌ Failed: ' . imap_last_error();
"
```

## 🚨 Still Having Issues?

### Issue 1: "Can't connect" or "Connection refused"
- **Check firewall:** Your server may block outbound connections
- **Check host:** Try `mail.check-outpay.com` instead of server hostname
- **Contact hosting:** Ask them to verify IMAP is enabled for your domain

### Issue 2: "Authentication failed"
- **Verify password:** Make sure password is correct (including spaces: `Enter0text`)
- **Check email:** Ensure email is exactly `notify@check-outpay.com`
- **Try alternative:** Some hosts require full email as username, others just the part before @

### Issue 3: "Network unreachable"
- **Server firewall:** Contact hosting provider to allow outbound IMAP connections
- **Port blocked:** Ask hosting to allow ports 993 and 143 for outbound connections

## 📧 Contact Hosting Provider Template

If you need to contact your hosting provider:

```
Subject: IMAP Server Details for check-outpay.com

Hello,

I need the IMAP server settings for my email account: notify@check-outpay.com

Could you please provide:
1. IMAP server hostname (usually mail.check-outpay.com)
2. IMAP port (usually 993 or 143)
3. Encryption type (SSL or TLS)
4. Whether IMAP is enabled for this account

I'm trying to set up email monitoring for my application.

Thank you!
```

---

**Summary:** Change port from **465** (SMTP) to **993** (IMAP/SSL) or **143** (IMAP/TLS), and update host to `mail.check-outpay.com`.
