# Gmail → cPanel → Webhook Setup Guide (Instant Delivery)

## 🎯 Overview

This setup forwards Gmail emails to your webhook for **instant processing**. The webhook will automatically parse the email and extract payment information.

## 📋 Step-by-Step Setup

### Step 1: Forward Gmail to cPanel Email

1. **Login to Gmail:**
   - Go to: https://mail.google.com
   - Click Settings (gear icon) → See all settings

2. **Set up Forwarding:**
   - Go to **Forwarding and POP/IMAP** tab
   - Click **Add a forwarding address**
   - Enter your cPanel email: `alerts@yourdomain.com` (or any email on your cPanel)
   - Click **Next** → **Proceed**
   - Verify the forwarding address (check your cPanel email for verification code)

3. **Enable Forwarding:**
   - Select **Forward a copy of incoming mail to**
   - Choose your cPanel email address
   - Choose what to do with Gmail's copy (recommend: **Keep Gmail's copy in Inbox**)
   - Click **Save Changes**

### Step 2: Forward cPanel Email to Webhook

**⚠️ Important:** cPanel email forwarders typically forward to email addresses, NOT webhooks directly. 

**Option A: If cPanel supports "Forward to URL" (some versions do):**
1. **Login to cPanel:**
   - Go to your cPanel dashboard

2. **Set up Email Forwarder:**
   - Go to **Email** → **Forwarders**
   - Click **Add Forwarder**
   - **Address to Forward:** `payment@check-outpay.com` (or create new email)
   - **Destination:** Look for **"Forward to URL"** option
   - **URL:** `https://check-outpay.com/api/v1/email/webhook`
   - Click **Add Forwarder**

**Option B: If "Forward to URL" is NOT available (most common):**
Use **IMAP monitoring instead** (simpler and already built-in):
1. Forward Gmail → `payment@check-outpay.com` (cPanel email)
2. Add email account in Admin → Email Accounts
3. System checks every minute automatically
4. **See CPANEL_WEBHOOK_FORWARDING.md for detailed instructions**

### Step 3: Configure Webhook Secret (Security)

1. **Login to Admin Panel:**
   - Go to: `https://check-outpay.com/admin/settings`

2. **Set Webhook Secret:**
   - Scroll to **Security Settings**
   - Enter a secret key (e.g., generate random string)
   - Save

3. **Add to cPanel Forwarder:**
   - Go back to cPanel → Email Forwarders
   - Edit the forwarder
   - Add header: `X-Zapier-Secret: your-secret-key`
   - Save

### Step 4: Whitelist Email Addresses

1. **Login to Admin Panel:**
   - Go to: `https://check-outpay.com/admin/settings`

2. **Add Whitelisted Emails:**
   - Scroll to **Whitelisted Emails** section
   - Add bank email addresses:
     - `alerts@gtbank.com`
     - `@gtbank.com` (for all GTBank emails)
     - Add other bank email addresses as needed
   - Click **Add**

## ✅ How It Works

1. **Bank sends email** → Gmail inbox
2. **Gmail forwards** → cPanel email (`alerts@yourdomain.com`)
3. **cPanel forwards** → Your webhook (`/api/v1/email/webhook`)
4. **Webhook parses email** → Extracts payment info automatically
5. **System matches payment** → Processes transaction

## 🔍 Email Parsing

The webhook automatically:
- ✅ Extracts sender email address
- ✅ Parses email content (HTML/text)
- ✅ Extracts amount (handles formats like "NGN 800", "₦800", etc.)
- ✅ Extracts sender name
- ✅ Extracts transaction time
- ✅ Matches with pending payments

## 🧪 Testing

1. **Send a test email** to your Gmail account
2. **Check Zapier Logs** in admin panel:
   - Go to: `https://check-outpay.com/admin/zapier-logs`
   - You should see the email received
3. **Check Processed Emails:**
   - Go to: `https://check-outpay.com/admin/processed-emails`
   - Email should appear in inbox

## 🔒 Security Features

- ✅ **Webhook Secret:** Only accepts requests with correct secret
- ✅ **Whitelisted Emails:** Only processes emails from whitelisted addresses
- ✅ **Duplicate Prevention:** Prevents processing same email twice

## 📊 Monitoring

- **Zapier Logs:** See all incoming webhook requests
- **Processed Emails:** See all parsed emails
- **Transaction Logs:** See payment matching results

## ⚠️ Troubleshooting

**Email not being received:**
- Check Gmail forwarding is enabled
- Verify cPanel email exists
- Check cPanel forwarder is active
- Check webhook URL is correct

**Email received but not processed:**
- Check email is from whitelisted address
- Check webhook secret matches
- Check Zapier logs for errors
- Check processed emails for parsing issues

**Payment not matching:**
- Check amount format in email
- Check sender name matches
- Check transaction was created before email
- Check time window settings

## 💡 Pro Tips

- **Dedicated Email:** Use a separate Gmail account just for payment notifications
- **Gmail Filters:** Set up filters to organize payment emails
- **Monitor Logs:** Regularly check Zapier logs and processed emails
- **Test First:** Send test emails before going live

---

**Result: Instant email processing, FREE (if you have cPanel), no Zapier needed!** 🎉
