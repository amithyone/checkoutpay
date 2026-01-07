# Zapier Setup Prompt - Copy & Paste This

## 📋 Instructions for Zapier Setup

**Copy and paste this entire prompt to Zapier support, a developer, or use it as your setup guide:**

---

## 🎯 Task: Set Up Gmail to Payment Gateway Email Forwarding

I need to set up a Zapier automation that forwards Gmail emails to my payment gateway webhook endpoint for instant processing.

### **Goal:**
When a new email arrives in Gmail from my bank (GTBank), forward it instantly to my payment gateway webhook so payments can be matched and approved automatically.

---

## 📝 Step-by-Step Instructions

### **Step 1: Create Zap**

1. Go to Zapier dashboard
2. Click **"Create Zap"**
3. Name it: **"Gmail to Payment Gateway"**

---

### **Step 2: Set Up Trigger (Gmail)**

1. **Trigger App:** Search and select **"Gmail"**
2. **Trigger Event:** Select **"New Email"**
3. Click **"Continue"**

4. **Connect Gmail Account:**
   - Click **"Sign in to Gmail"**
   - Authorize Zapier to access Gmail
   - Select the Gmail account that receives bank transaction emails
   - Click **"Continue"**

5. **Set Up Trigger Settings:**
   - **Folder:** Select **"INBOX"** (or specific folder where bank emails arrive)
   - **Only Emails Matching:** (Optional but recommended)
     - **From:** `alerts@gtbank.com` (or your bank's email address)
     - **Subject Contains:** `Transaction Notification` (or your bank's subject pattern)
   - Click **"Continue"**

6. **Test Trigger:**
   - Zapier will fetch a recent email
   - Review the test data to ensure it's working
   - Click **"Continue"**

---

### **Step 3: Set Up Action (Webhook)**

1. **Action App:** Search and select **"Webhooks by Zapier"**
2. **Action Event:** Select **"POST"**
3. Click **"Continue"**

4. **Set Up Webhook:**
   - **URL:** `https://check-outpay.com/api/v1/email/webhook`
   - **Method:** `POST`
   - **Data Pass-Through:** `No`
   - Click **"Continue"**

5. **Set Up Data Fields:**

   Click **"Show all options"** or expand the data section, then add these fields:

   **Field Name → Value (use "Insert Data" button to select from Gmail):**

   ```
   from → {{From}}
   to → {{To}}
   subject → {{Subject}}
   text → {{Plain Body}} (or {{Body Plain}})
   html → {{HTML Body}} (or {{Body HTML}})
   date → {{Date}}
   message_id → {{Message ID}}
   ```

   **How to add fields:**
   - Click in the field name box, type: `from`
   - Click in the value box
   - Click **"Insert Data"** button (or type `{{From}}`)
   - Select **"From"** from the Gmail trigger data
   - Repeat for all fields above

   **Alternative method (typing directly):**
   - In the value field, type: `{{From}}` (with double curly braces)
   - Zapier will auto-complete with available fields

6. **Test Action:**
   - Click **"Test & Continue"**
   - Zapier will send a test POST request
   - Check the response - should show `"success": true`
   - If you see an error, verify:
     - URL is correct: `https://check-outpay.com/api/v1/email/webhook`
     - All field names match exactly (case-sensitive)
     - HTML Body field is not empty

---

### **Step 4: Turn On Zap**

1. Review your Zap settings
2. Click **"Turn on Zap"** button
3. Your Zap is now **ACTIVE** ✅

---

## 🔍 Field Mapping Reference

**Gmail Fields Available in Zapier:**

| Field Name | Zapier Variable | Description |
|------------|----------------|-------------|
| From | `{{From}}` | Sender email address |
| To | `{{To}}` | Recipient email address |
| Subject | `{{Subject}}` | Email subject line |
| Plain Body | `{{Plain Body}}` or `{{Body Plain}}` | Text version of email |
| HTML Body | `{{HTML Body}}` or `{{Body HTML}}` | HTML version of email |
| Date | `{{Date}}` | Email date/time |
| Message ID | `{{Message ID}}` | Unique email identifier |

---

## ✅ Expected Webhook Response

**Success Response:**
```json
{
  "success": true,
  "message": "Email received and payment matched",
  "matched": true,
  "payment_id": 123,
  "transaction_id": "TXN-1234567890-ABC123"
}
```

**Or if no match:**
```json
{
  "success": true,
  "message": "Email received and stored",
  "matched": false,
  "email_id": 456
}
```

---

## 🧪 Testing Checklist

- [ ] Zap is turned ON
- [ ] Gmail account is connected
- [ ] Webhook URL is correct
- [ ] All fields are mapped correctly
- [ ] Test action returns success
- [ ] Send test email from bank
- [ ] Check Zapier task history - shows "Task ran"
- [ ] Check payment gateway admin panel - email appears in Inbox
- [ ] Verify payment matching works

---

## 🚨 Troubleshooting

**If webhook returns error:**
- Check URL: `https://check-outpay.com/api/v1/email/webhook`
- Verify field names match exactly: `from`, `to`, `subject`, `text`, `html`, `date`, `message_id`
- Ensure HTML Body field is populated (not empty)
- Check Zapier task history for error details

**If Zap not triggering:**
- Verify email matches filter criteria (From, Subject)
- Check Zap is turned ON
- Wait 1-2 minutes (Zapier checks every minute)
- Check Gmail connection is active

**If payment not matching:**
- Verify HTML Body contains email content
- Check email format matches expected structure
- Use "Check Match" button in admin panel to debug

---

## 📊 Webhook Endpoint Details

**URL:** `https://check-outpay.com/api/v1/email/webhook`  
**Method:** `POST`  
**Content-Type:** `application/json`  
**Authentication:** None required (public endpoint)

**Required Fields:**
- `from` (string, required)
- `to` (string, required)
- `subject` (string, optional)
- `text` (string, optional)
- `html` (string, optional)
- `date` (string, optional - defaults to current time)
- `message_id` (string, optional - auto-generated if not provided)

---

## 💡 Pro Tips

1. **Add Filter Step** (Optional but recommended):
   - Add "Filter by Zapier" step between Gmail and Webhook
   - Only forward emails from `@gtbank.com`
   - Saves Zapier tasks (free tier limit)

2. **Monitor Usage:**
   - Check Zapier dashboard for task count
   - Free tier: 100 tasks/month
   - Upgrade if needed: $19.99/month for 750 tasks

3. **Multiple Email Accounts:**
   - Create separate Zap for each email account
   - Each Zap processes its own emails independently

---

## 📞 Support

**Zapier Support:**
- Help Center: https://help.zapier.com
- Community: https://community.zapier.com

**Payment Gateway:**
- Webhook URL: `https://check-outpay.com/api/v1/email/webhook`
- Admin Panel: Check Email Inbox for processed emails
- Logs: Check `storage/logs/laravel.log` for errors

---

## ✨ Summary

**What this Zap does:**
1. Monitors Gmail inbox for new emails
2. Filters emails from bank (GTBank)
3. Forwards email data to payment gateway webhook
4. Payment gateway processes email instantly
5. Matches payment and approves automatically

**Result:** Payments approved in < 1 second instead of 5-10 minutes! ⚡

---

**Copy this entire prompt and use it to set up your Zapier automation!**
