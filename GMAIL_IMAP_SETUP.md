# Gmail IMAP Setup Guide (FREE Alternative to Zapier)

## 🎯 Quick Setup - 5 Minutes

Since your transaction emails come to Gmail, you can use **IMAP monitoring** which is **FREE** and already built into your system!

## Step 1: Get Gmail App Password

1. **Enable 2-Step Verification:**
   - Go to: https://myaccount.google.com/security
   - Enable "2-Step Verification"

2. **Generate App Password:**
   - Go to: https://myaccount.google.com/apppasswords
   - Select app: "Mail"
   - Select device: "Other (Custom name)"
   - Enter name: "Payment Gateway"
   - Click "Generate"
   - Copy the 16-character password (remove spaces)

## Step 2: Add Email Account in Admin Panel

1. Login to admin: `https://check-outpay.com/admin`
2. Go to **Email Accounts** → **Add New**
3. Fill in:
   - **Name:** Gmail Transaction Account
   - **Email:** your-email@gmail.com
   - **Host:** `imap.gmail.com`
   - **Port:** `993`
   - **Encryption:** `SSL`
   - **Password:** Your 16-character App Password (no spaces)
   - **Folder:** `INBOX`
   - **Validate Certificate:** Unchecked (false)
   - **Active:** Checked (true)
   - **Method:** `imap`
4. Click **Save**

## Step 3: Test Connection

1. Click **Test Connection** button
2. Should show: ✅ Connection successful

## Step 4: Enable Cron Job

On your server, add this to cron:

```bash
* * * * * cd /home/checzspw/public_html && php artisan schedule:run >> /dev/null 2>&1
```

**Or via cPanel Cron Jobs:**
1. Login to cPanel
2. Go to **Cron Jobs**
3. Add new cron job:
   - **Minute:** `*`
   - **Hour:** `*`
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`
   - **Command:** `cd /home/checzspw/public_html && php artisan schedule:run`
4. Save

## Step 5: Verify It's Working

1. Go to **Admin Dashboard**
2. Click **Fetch Emails** button
3. Check **Inbox** to see if emails are being fetched
4. System will check every minute automatically

## ✅ Done!

Your system will now:
- ✅ Check Gmail every minute
- ✅ Process transaction emails automatically
- ✅ Match payments instantly
- ✅ **Cost: $0/month** (FREE!)

## 🔍 Troubleshooting

**If emails aren't being fetched:**

1. **Check App Password:**
   - Make sure you're using App Password (16 characters)
   - Not your regular Gmail password

2. **Check IMAP Settings:**
   - Host: `imap.gmail.com`
   - Port: `993`
   - Encryption: `SSL` (NOT TLS)
   - Validate Certificate: Unchecked

3. **Check Cron Job:**
   - Verify cron is running: `php artisan schedule:list`
   - Check logs: `storage/logs/laravel.log`

4. **Test Manually:**
   ```bash
   php artisan payment:monitor-emails
   ```

## 💡 Pro Tips

- **Dedicated Gmail Account:** Use a separate Gmail account just for payment notifications
- **Gmail Filters:** Set up filters to organize payment emails
- **Monitor Logs:** Check admin dashboard for email status

---

**No Zapier needed! Save $30+/month!** 🎉
