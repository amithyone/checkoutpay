# Check Email Account Settings in Database

## ✅ Good News: Server Can Connect!

Your terminal test showed: **✅ Works on server!**

This means:
- ✅ PHP IMAP extension is installed
- ✅ Server can reach IMAP server  
- ✅ Credentials are correct
- ✅ Network/firewall is working

## 🔍 The Issue is in Laravel Database

The problem is likely **wrong settings stored in your database**. Let's check what's actually stored:

## 📋 What to Check

### Step 1: Check Current Settings in Admin Panel

1. Go to **Admin Panel → Email Accounts**
2. Find `notify@check-outpay.com`
3. Click **Edit**
4. Verify these settings match exactly:
   - **Host:** `check-outpay.com` (not `mail.check-outpay.com` or `premium340.web-hosting.com`)
   - **Port:** `993` (not `465` or `587`)
   - **Encryption:** `SSL` (not `TLS`)
   - **Password:** `Enter0text@` (make sure it has the `@` at the end!)
   - **Validate Certificate:** Unchecked (false)

### Step 2: Update Password (Most Important!)

**The password might be stored WITHOUT the `@` symbol!**

1. In the edit form, **clear the password field completely**
2. **Re-enter the password exactly:** `Enter0text@` (with the `@` symbol)
3. **Click Update**
4. **Click Test Connection**

### Step 3: Verify Settings Match Working Config

Since this works from terminal:
```bash
check-outpay.com:993/ssl/novalidate-cert
notify@check-outpay.com
Enter0text@
```

Your database settings must match EXACTLY:
- Host: `check-outpay.com`
- Port: `993`
- Encryption: `ssl`
- Validate Cert: `false`
- Email: `notify@check-outpay.com`
- Password: `Enter0text@` (with @)

## 🧪 Debug Script

I've created `debug_email_account.php` - upload it to your server and run:

```bash
php debug_email_account.php
```

This will show:
- What settings are stored in the database
- If password has the `@` symbol
- If settings match the working configuration
- Will test connection with database settings

## 🎯 Most Likely Issues

1. **Password Missing `@` Symbol** (90% likely)
   - Database has: `Enter0text`
   - Should be: `Enter0text@`
   - **Fix:** Clear password field, re-enter with `@`, save

2. **Wrong Host** (5% likely)
   - Database might have: `premium340.web-hosting.com` or `mail.check-outpay.com`
   - Should be: `check-outpay.com`
   - **Fix:** Update host to `check-outpay.com`

3. **Wrong Port** (5% likely)
   - Database might still have: `465` (old SMTP port)
   - Should be: `993`
   - **Fix:** Update port to `993`

## 🔧 Quick Fix Steps

1. **Go to Admin Panel → Email Accounts**
2. **Edit** `notify@check-outpay.com`
3. **Check/Update:**
   - Host: `check-outpay.com`
   - Port: `993`
   - Encryption: `SSL`
   - **Password: Clear field and re-enter:** `Enter0text@` ⚠️ **MOST IMPORTANT**
   - Validate Cert: **Unchecked**
4. **Click Update**
5. **Click Test Connection**
6. Should work now! ✅

## 📝 Why This Happens

When you first created the account, you might have:
- Used port 465 (SMTP) instead of 993 (IMAP)
- Entered password without the `@` symbol
- Used wrong hostname

Since the terminal test works, we know the **credentials and server are correct** - we just need to make sure Laravel is using the same settings!

---

**Action:** Update the password field in admin panel - that's most likely the issue!
