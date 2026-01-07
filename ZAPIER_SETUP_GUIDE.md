# Zapier Email Forwarding Setup Guide

## Why Zapier is the Best Option ✅

**Zapier Advantages:**
- ✅ **Free tier:** 100 tasks/month (perfect for testing)
- ✅ **Easy setup:** Visual interface, no coding
- ✅ **Reliable:** 99.9% uptime
- ✅ **Instant:** Emails processed in < 1 second
- ✅ **No server load:** All processing happens on Zapier's servers
- ✅ **Visual workflow:** Easy to see what's happening

**Cost:** Free for 100 emails/month, then $19.99/month for 750 tasks

---

## Step-by-Step Setup

### Step 1: Create Zapier Account

1. Go to **https://zapier.com**
2. Sign up for free account
3. Verify your email

---

### Step 2: Create Your Zap

1. Click **"Create Zap"** button
2. Name it: **"Gmail to Payment Gateway"**

---

### Step 3: Set Up Trigger (Gmail)

1. **Trigger App:** Search for **"Gmail"**
2. **Trigger Event:** Select **"New Email"**
3. **Click "Continue"**

4. **Connect Gmail Account:**
   - Click **"Sign in to Gmail"**
   - Authorize Zapier to access your Gmail
   - Select the Gmail account that receives bank emails
   - Click **"Continue"**

5. **Set Up Trigger:**
   - **Folder:** Select **"INBOX"** (or specific folder)
   - **Only Emails Matching:** (Optional - recommended)
     - **From:** `alerts@gtbank.com` (or your bank email)
     - **Subject Contains:** `Transaction Notification`
   - Click **"Continue"**

6. **Test Trigger:**
   - Zapier will fetch a recent email
   - Review the test data
   - Click **"Continue"**

---

### Step 4: Set Up Action (Webhook)

1. **Action App:** Search for **"Webhooks by Zapier"**
2. **Action Event:** Select **"POST"**
3. **Click "Continue"**

4. **Set Up Webhook:**
   - **URL:** `https://check-outpay.com/api/v1/email/webhook`
   - **Method:** `POST`
   - **Data Pass-Through:** `No`
   - **Click "Continue"**

5. **Set Up Data (IMPORTANT):**
   
   Click **"Show all options"** and fill in:

   ```
   from: {{From}}
   to: {{To}}
   subject: {{Subject}}
   text: {{Plain Body}}
   html: {{HTML Body}}
   date: {{Date}}
   message_id: {{Message ID}}
   ```

   **How to add fields:**
   - Click in the field
   - Click **"Insert Data"** button
   - Select the field from Gmail trigger (e.g., "From", "Subject", etc.)
   - Or type `{{Field Name}}` directly

   **Field Mapping:**
   ```
   from → {{From}}
   to → {{To}}
   subject → {{Subject}}
   text → {{Plain Body}} (or {{Body Plain}})
   html → {{HTML Body}} (or {{Body HTML}})
   date → {{Date}}
   message_id → {{Message ID}}
   ```

6. **Test Action:**
   - Click **"Test & Continue"**
   - Zapier will send a test POST request
   - Check the response - should show `"success": true`
   - If error, check the URL and field names

---

### Step 5: Turn On Your Zap

1. Review your Zap settings
2. Click **"Turn on Zap"**
3. Your Zap is now **ACTIVE** ✅

---

## Testing

### Test 1: Send Test Email

1. Send an email to your Gmail from `alerts@gtbank.com`
2. Subject: "Transaction Notification"
3. Wait 10-30 seconds
4. Check Zapier dashboard - should show "Task ran"
5. Check your payment gateway admin panel - email should appear in Inbox

### Test 2: Check Webhook Logs

1. In Zapier, click on your Zap
2. Click **"Task History"**
3. See all emails processed
4. Click on a task to see details

### Test 3: Verify Payment Matching

1. Create a test payment in admin panel
2. Send matching email via Gmail
3. Payment should be approved instantly (< 1 second)

---

## Zapier Field Reference

**Available Gmail Fields in Zapier:**

| Zapier Field | Description | Example |
|--------------|-------------|---------|
| `{{From}}` | Sender email | `alerts@gtbank.com` |
| `{{To}}` | Recipient email | `your-email@gmail.com` |
| `{{Subject}}` | Email subject | `Transaction Notification` |
| `{{Plain Body}}` | Text content | `Your transaction...` |
| `{{HTML Body}}` | HTML content | `<html>...</html>` |
| `{{Date}}` | Email date | `2026-01-07 10:30:00` |
| `{{Message ID}}` | Unique ID | `1234567890` |

---

## Advanced Configuration

### Filter Emails (Recommended)

**Add Filter Step between Trigger and Action:**

1. Click **"+"** between Gmail and Webhook
2. Select **"Filter by Zapier"**
3. Set conditions:
   - **From** contains `@gtbank.com`
   - **OR Subject** contains `Transaction`
4. Only matching emails will be forwarded

**Benefits:**
- Saves Zapier tasks (free tier limit)
- Only processes relevant emails
- Faster processing

---

### Multiple Email Accounts

**Set up separate Zaps for each email account:**

1. Create Zap 1: `account1@gmail.com` → Webhook
2. Create Zap 2: `account2@gmail.com` → Webhook
3. Each Zap processes its own emails

---

## Troubleshooting

### Problem: Zap not triggering

**Solutions:**
- Check Gmail connection is active
- Verify email matches filter criteria
- Check Zap is turned ON
- Wait 1-2 minutes (Zapier checks every minute)

### Problem: Webhook returns error

**Solutions:**
- Check URL is correct: `https://check-outpay.com/api/v1/email/webhook`
- Verify field names match exactly (case-sensitive)
- Check `{{HTML Body}}` is not empty
- Review webhook response in Zapier task history

### Problem: Payment not matching

**Solutions:**
- Check email HTML is being sent correctly
- Verify amount is extracted from email
- Check payment time window settings
- Use "Check Match" button in admin panel

### Problem: Duplicate emails

**Solutions:**
- Zapier uses `{{Message ID}}` to prevent duplicates
- If same email forwarded twice, check Message ID is unique
- System automatically skips duplicates

---

## Cost & Limits

**Free Tier:**
- ✅ 100 tasks/month
- ✅ 5 Zaps
- ✅ 15-minute update frequency

**Starter Plan ($19.99/month):**
- ✅ 750 tasks/month
- ✅ 20 Zaps
- ✅ 1-minute update frequency

**Professional Plan ($49/month):**
- ✅ 2,000 tasks/month
- ✅ Unlimited Zaps
- ✅ 1-minute update frequency

**For payment gateway:**
- Free tier: ~3 emails/day (100/month)
- Starter: ~25 emails/day (750/month)
- Professional: ~66 emails/day (2,000/month)

---

## Monitoring

### Check Zap Status

1. Go to Zapier dashboard
2. See all your Zaps
3. Green = Active, Red = Error

### View Task History

1. Click on your Zap
2. Click **"Task History"**
3. See all processed emails
4. Click task to see details and webhook response

### Set Up Alerts

1. In Zapier settings
2. Enable email notifications
3. Get alerts if Zap fails

---

## Best Practices

✅ **Use Filters:** Only forward relevant emails  
✅ **Monitor Usage:** Check task count regularly  
✅ **Test First:** Always test before going live  
✅ **Backup Plan:** Keep IMAP as backup (optional)  
✅ **Monitor Logs:** Check admin panel for processed emails  

---

## Quick Start Checklist

- [ ] Create Zapier account
- [ ] Connect Gmail account
- [ ] Set up Gmail trigger with filters
- [ ] Set up Webhook action
- [ ] Map all email fields correctly
- [ ] Test the Zap
- [ ] Turn on Zap
- [ ] Send test email
- [ ] Verify email appears in admin panel
- [ ] Test payment matching

---

## Support

**Zapier Support:**
- Help Center: https://help.zapier.com
- Community: https://community.zapier.com

**Payment Gateway Support:**
- Check logs: `storage/logs/laravel.log`
- Admin panel: Check Email Inbox
- Test webhook: Use cURL or Postman

---

## Summary

**Zapier is the BEST option because:**
- ⚡ **Instant** email processing
- 🎯 **Easy** visual setup
- 💰 **Free** tier available
- 🔒 **Reliable** 99.9% uptime
- 📊 **Monitorable** task history

**Set it up once, and emails will arrive instantly!** 🚀
