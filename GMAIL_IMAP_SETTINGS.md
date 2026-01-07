# Gmail IMAP Settings for Payment Gateway

## ⚠️ Important: We Use IMAP, NOT POP

Our payment gateway uses **IMAP** to monitor emails in real-time. POP downloads emails and doesn't sync, so it won't work for our use case.

## ✅ Correct Gmail IMAP Settings

### For Incoming Mail (IMAP):

| Setting | Value |
|---------|-------|
| **Server Type** | IMAP |
| **Host** | `imap.gmail.com` |
| **Port** | `993` |
| **Encryption** | `SSL` (NOT TLS) |
| **Requires SSL** | Yes |
| **Validate Certificate** | No (unchecked) |
| **Username** | Your full Gmail address (e.g., `fastifysales@gmail.com`) |
| **Password** | App Password (16-character code, NOT your regular password) |
| **Folder** | `INBOX` |

### For Outgoing Mail (SMTP) - Not Used by Gateway:
We don't use SMTP, but if you need it:
- **Host:** `smtp.gmail.com`
- **Port:** `587`
- **Encryption:** `TLS`

## 🔑 Getting Gmail App Password

1. **Enable 2-Step Verification:**
   - Go to: https://myaccount.google.com/security
   - Enable "2-Step Verification"

2. **Generate App Password:**
   - Go to: https://myaccount.google.com/apppasswords
   - Select app: "Mail" (or "Other" if Mail isn't available)
   - Select device: "Other (Custom name)"
   - Enter name: "Payment Gateway"
   - Click "Generate"
   - Copy the 16-character password (looks like: `abcd efgh ijkl mnop`)
   - **Remove spaces** when pasting: `abcdefghijklmnop`

## 📋 Settings Checklist for Your Account

Based on your email `fastifysales@gmail.com`, use these exact settings:

- ✅ **Name:** Fastify Sales Email
- ✅ **Email:** fastifysales@gmail.com
- ✅ **Host:** imap.gmail.com
- ✅ **Port:** 993
- ✅ **Encryption:** SSL (NOT TLS!)
- ✅ **Password:** Your 16-character App Password
- ✅ **Folder:** INBOX
- ✅ **Validate Certificate:** Unchecked (false)
- ✅ **Active:** Checked

## 🚨 Common Mistakes

1. **Using POP instead of IMAP**
   - ❌ Wrong: pop.gmail.com, port 995
   - ✅ Right: imap.gmail.com, port 993

2. **Using TLS with port 993**
   - ❌ Wrong: Port 993 + TLS
   - ✅ Right: Port 993 + SSL

3. **Using regular password**
   - ❌ Wrong: Your Gmail password
   - ✅ Right: 16-character App Password

4. **Wrong folder name**
   - ❌ Wrong: inbox, Inbox, INBOXES
   - ✅ Right: INBOX (all caps)

## 🧪 Test Your Settings

After configuring, click "Test Connection" in the admin panel. You should see:
- ✅ "Connection successful! Successfully connected to fastifysales@gmail.com"

If you see an error, check:
1. Is encryption set to SSL (not TLS)?
2. Is port 993?
3. Are you using App Password (not regular password)?
4. Is 2-Step Verification enabled?

## 📞 Still Not Working?

Check the server logs:
```bash
tail -f storage/logs/laravel.log | grep -i "email\|connection\|imap"
```

The error message will tell you exactly what's wrong!

---

**Remember: IMAP for real-time monitoring, not POP!** 📧
