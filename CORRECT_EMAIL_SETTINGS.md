# Correct Email Account Settings

## ✅ Correct IMAP Settings for notify@check-outpay.com

Based on your cPanel configuration:

### IMAP Settings (Incoming Email)
- **Email:** `notify@check-outpay.com`
- **IMAP Host:** `check-outpay.com`
- **Port:** `993`
- **Encryption:** `SSL`
- **Username:** `notify@check-outpay.com`
- **Password:** `Enter0text@` (note: includes @ at the end)
- **Folder:** `INBOX`
- **Validate Certificate:** Unchecked (false)

### SMTP Settings (Outgoing Email - for reference only)
- **SMTP Host:** `check-outpay.com`
- **SMTP Port:** `465`
- **SMTP Encryption:** `SSL`

⚠️ **Note:** SMTP settings are for **sending** emails. For your payment monitoring system, you only need **IMAP** settings (for **receiving** emails).

## 📋 Settings to Use in Admin Panel

Go to **Admin Panel → Email Accounts** and update/create the account with these settings:

```
Account Name: notify@check-outpay.com (or any friendly name)
Email Address: notify@check-outpay.com
Method: IMAP (or Native IMAP)
IMAP Host: check-outpay.com
Port: 993
Encryption: SSL
Password: Enter0text@
Folder: INBOX
Validate Certificate: ❌ (unchecked)
Active: ✅ (checked)
```

## 🧪 Quick Test

Test the connection with this command:

```bash
php -r "\$conn = @imap_open('{check-outpay.com:993/ssl/novalidate-cert}INBOX', 'notify@check-outpay.com', 'Enter0text@'); echo \$conn ? '✅ SUCCESS! Settings are correct.' : '❌ Failed: ' . imap_last_error();"
```

## ⚠️ Important Notes

1. **Host is `check-outpay.com`** (not `mail.check-outpay.com`) - This is correct based on your cPanel settings
2. **Password includes `@` symbol** - Make sure to include `Enter0text@` (not just `Enter0text`)
3. **Port 993 uses SSL** - Not TLS, use SSL encryption
4. **Port 465 is SMTP** - Only use this for sending emails, not for IMAP

## 🔍 Troubleshooting

If connection still fails, check:

1. **Password** - Ensure you're using `Enter0text@` (with @ at the end)
2. **IMAP Enabled** - Verify IMAP is enabled for this email account in cPanel
3. **Firewall** - Check if server firewall allows outbound connections on port 993
4. **PHP IMAP Extension** - Verify `php-imap` extension is installed on server

## 📝 Update Your Email Account

1. Log in to Admin Panel
2. Go to Email Accounts
3. Edit `notify@check-outpay.com` (or create new if it doesn't exist)
4. Update settings:
   - Host: `check-outpay.com`
   - Port: `993`
   - Encryption: `SSL`
   - Password: `Enter0text@`
5. Click **Test Connection**
6. If successful, click **Save**

---

**These settings should work now!** The earlier issue was using port 465 (SMTP) instead of port 993 (IMAP), and possibly using the wrong password format.
