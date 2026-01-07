# Email Connection Troubleshooting Guide

## 🔴 Common Connection Errors

### Error: "Authentication failed" or "Invalid credentials"

**For Gmail:**
1. **You MUST use an App Password, not your regular Gmail password**
   - Go to: https://myaccount.google.com/apppasswords
   - Generate a new App Password
   - Use that 16-character password (not your regular password)

2. **Make sure 2-Step Verification is enabled**
   - App Passwords only work with 2-Step Verification enabled
   - Go to: https://myaccount.google.com/security

3. **Check email address**
   - Use your full email: `yourname@gmail.com`
   - Not just `yourname`

**For Other Email Providers:**
- Check if IMAP is enabled in your email settings
- Verify the password is correct
- Some providers require special app passwords

### Error: "Connection failed" or "Timeout"

**Check these settings:**

1. **Host Settings:**
   - Gmail: `imap.gmail.com`
   - Outlook: `outlook.office365.com`
   - Yahoo: `imap.mail.yahoo.com`

2. **Port Settings:**
   - SSL: `993`
   - TLS: `587` or `993`
   - Make sure encryption matches port

3. **Encryption:**
   - For Gmail: Use `ssl` with port `993`
   - For TLS: Use `tls` with port `587`

4. **Validate Certificate:**
   - For Gmail: Keep this **unchecked** (false)
   - Some servers have certificate issues

### Error: "Folder not found"

- Default folder should be: `INBOX` (all caps)
- Some email providers use different folder names
- Check your email client to see folder names

## ✅ Step-by-Step Gmail Setup

### Step 1: Enable 2-Step Verification
1. Go to: https://myaccount.google.com/security
2. Enable "2-Step Verification"
3. Follow the setup process

### Step 2: Generate App Password
1. Go to: https://myaccount.google.com/apppasswords
2. Click "Select app" dropdown → Choose "Mail" (or "Other" if Mail isn't available)
3. Click "Select device" dropdown → Choose "Other (Custom name)"
4. Enter a name: "Payment Gateway" (or any name you prefer)
5. Click "Generate"
6. Copy the 16-character password (no spaces) - it will look like: `abcd efgh ijkl mnop`

### Step 3: Configure in Admin Panel
1. Go to: `/admin/email-accounts/create`
2. Fill in:
   - **Name:** Your Gmail Account
   - **Email:** yourname@gmail.com
   - **Host:** imap.gmail.com
   - **Port:** 993
   - **Encryption:** SSL (NOT TLS - port 993 requires SSL!)
   - **Password:** Paste the 16-character App Password (remove spaces if any)
   - **Folder:** INBOX
   - **Validate Certificate:** Unchecked
3. Click "Test Connection"
4. If successful, save!

**⚠️ IMPORTANT:** Port 993 MUST use SSL encryption, not TLS!

## 🔍 Testing Connection

### Manual Test (via Command Line)

```bash
# Test IMAP connection manually
telnet imap.gmail.com 993

# Or use openssl
openssl s_client -connect imap.gmail.com:993
```

### Check Laravel Logs

```bash
# View connection errors
tail -f storage/logs/laravel.log | grep -i "email\|connection\|imap"
```

## 🚨 Common Mistakes

1. **Using regular password instead of App Password**
   - ❌ Wrong: Your Gmail password
   - ✅ Right: 16-character App Password

2. **Wrong port/encryption combination**
   - ❌ Wrong: SSL with port 587
   - ✅ Right: SSL with port 993

3. **Validate Certificate checked for Gmail**
   - ❌ Wrong: Validate Certificate = true
   - ✅ Right: Validate Certificate = false

4. **Wrong folder name**
   - ❌ Wrong: inbox, Inbox, INBOXES
   - ✅ Right: INBOX (all caps)

## 📋 Quick Checklist

- [ ] 2-Step Verification enabled (Gmail)
- [ ] App Password generated (Gmail)
- [ ] Using App Password, not regular password
- [ ] Host is correct (imap.gmail.com)
- [ ] Port is 993 for SSL
- [ ] Encryption is "ssl"
- [ ] Validate Certificate is unchecked
- [ ] Folder is "INBOX"
- [ ] Email address is complete (with @gmail.com)

## 🎯 Still Having Issues?

1. **Check server logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Test with different settings:**
   - Try TLS instead of SSL
   - Try port 587 with TLS
   - Try different folder names

3. **Verify credentials:**
   - Double-check email address
   - Regenerate App Password
   - Test in another email client first

4. **Check firewall/network:**
   - Make sure server can access IMAP ports
   - Check if firewall blocks outbound connections

---

**Most common issue:** Using regular password instead of App Password for Gmail! 🔑
