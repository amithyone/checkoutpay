# Email Webhook Setup Guide - Bypass Firewall Issues

## 🎯 Quick Solution

Instead of IMAP (blocked by firewall), forward emails to a webhook URL using HTTPS (port 443).

## ✅ Setup Steps

### Step 1: Get Your Webhook URL

Your webhook endpoint is ready:
```
https://check-outpay.com/api/v1/webhook/email
```

### Step 2: Choose a Service (Pick One)

#### Option A: Zapier (Recommended - Free Tier Available)

1. **Sign up:** https://zapier.com (free tier: 100 tasks/month)

2. **Create Zap:**
   - Trigger: Gmail > New Email
   - Filter: Only emails matching payment keywords
   - Action: Webhooks by Zapier > POST
   - URL: `https://check-outpay.com/api/v1/webhook/email`
   - Method: POST
   - Data: Send email fields (subject, from, body, etc.)

3. **Test:** Send a test email to your Gmail

**Zapier Format:**
```json
{
  "subject": "{{Subject}}",
  "from": "{{From}}",
  "to": "{{To}}",
  "text": "{{Plain Body}}",
  "html": "{{HTML Body}}",
  "date": "{{Date}}"
}
```

---

#### Option B: Make.com (Free Tier Available)

1. **Sign up:** https://make.com (free tier: 1000 operations/month)

2. **Create Scenario:**
   - Trigger: Gmail > Watch Emails
   - Action: HTTP > Make a Request
   - Method: POST
   - URL: `https://check-outpay.com/api/v1/webhook/email`
   - Body: Map email fields

3. **Test:** Send a test email

---

#### Option C: Email Parser Services

**Services that convert emails to webhooks:**
- **Email Parser** (https://emailparser.io)
- **Parseur** (https://parseur.com)
- **Mailparser** (https://mailparser.io)

**Setup:**
1. Create account
2. Set up email forwarding from Gmail
3. Configure parser to extract email fields
4. Set webhook URL: `https://check-outpay.com/api/v1/webhook/email`

---

### Step 3: Configure Gmail Filter (Optional)

To only forward payment-related emails:

1. Go to Gmail Settings > Filters and Blocked Addresses
2. Create filter:
   - **From:** Your bank email addresses
   - **Subject contains:** "transfer", "deposit", "payment", etc.
   - **Has attachment:** (optional)
3. **Forward to:** Your forwarding service email

---

## 🧪 Test Your Webhook

### Manual Test (cURL)

```bash
curl -X POST https://check-outpay.com/api/v1/webhook/email \
  -H "Content-Type: application/json" \
  -d '{
    "subject": "Bank Transfer Notification",
    "from": "bank@example.com",
    "to": "fastifysales@gmail.com",
    "text": "You received 5000 from John Doe",
    "date": "2024-01-07 12:00:00"
  }'
```

### Expected Response

```json
{
  "success": true,
  "message": "Email received and queued for processing"
}
```

---

## 📋 Webhook Data Format

The webhook accepts multiple formats. Here are examples:

### Format 1: Simple
```json
{
  "subject": "Bank Transfer",
  "from": "bank@example.com",
  "text": "Email body text"
}
```

### Format 2: Complete
```json
{
  "subject": "Bank Transfer",
  "from": "Bank Name <bank@example.com>",
  "to": "fastifysales@gmail.com",
  "text": "Plain text body",
  "html": "<p>HTML body</p>",
  "date": "2024-01-07 12:00:00"
}
```

### Format 3: Nested
```json
{
  "email": {
    "subject": "Bank Transfer",
    "from": "bank@example.com",
    "text": "Email body"
  }
}
```

---

## ✅ Advantages

- ✅ **No firewall issues** - Uses HTTPS (port 443)
- ✅ **Works immediately** - Set up in 10 minutes
- ✅ **Reliable** - Uses standard web protocols
- ✅ **Free tier available** - Most services offer free plans
- ✅ **No code changes needed** - Webhook endpoint already created

---

## 🔍 Monitoring

Check logs to see if webhooks are being received:

```bash
tail -f storage/logs/laravel.log | grep "Email webhook"
```

---

## 🚀 Next Steps

1. Choose a service (Zapier recommended)
2. Set up email forwarding
3. Configure webhook URL
4. Test with a sample email
5. Monitor logs to verify it's working

**Your webhook is ready to receive emails!** 🎉
