# Email Forwarding Setup Guide

## Why Email Forwarding is Better

**Email Forwarding vs IMAP/Gmail API Polling:**

| Feature | IMAP/Gmail API Polling | Email Forwarding (Webhook) |
|---------|----------------------|---------------------------|
| **Speed** | 5-30 seconds per check | **Instant** (real-time) |
| **Server Load** | High (connects to email server) | **Low** (just receives HTTP POST) |
| **Latency** | Up to 5-10 minutes (polling interval) | **< 1 second** |
| **Reliability** | Depends on email server connection | **More reliable** (direct HTTP) |
| **Scalability** | Limited by polling frequency | **Unlimited** (handles as many as arrive) |

**Email forwarding is MUCH FASTER because:**
- ✅ Emails arrive **instantly** via HTTP POST
- ✅ No need to connect to email servers
- ✅ No polling delays (5-10 minutes)
- ✅ No fetching email bodies (already forwarded)
- ✅ Real-time processing

---

## Setup Instructions

### Option 1: Gmail Forwarding (Recommended)

1. **Set up Email Forwarding in Gmail:**
   - Go to Gmail Settings → Forwarding and POP/IMAP
   - Click "Add a forwarding address"
   - Enter your webhook URL: `https://check-outpay.com/api/v1/email/webhook`
   - Verify the forwarding address

2. **Create Forwarding Rule:**
   - Go to Gmail Settings → Filters and Blocked Addresses
   - Click "Create a new filter"
   - Set criteria:
     - **From:** `alerts@gtbank.com` (or your bank email)
     - **Subject contains:** `Transaction Notification` (or your keywords)
   - Click "Create filter"
   - Check "Forward it to:" and select your webhook address
   - Save

3. **Use Email Forwarding Service (Alternative):**
   - Services like **Zapier**, **IFTTT**, or **Microsoft Power Automate**
   - Set up: Gmail → When new email → HTTP POST to webhook URL

---

### Option 2: cPanel Email Forwarding

1. **Log into cPanel**
2. **Go to Email → Forwarders**
3. **Create Forwarder:**
   - Forward from: `your-email@yourdomain.com`
   - Forward to: `webhook@yourdomain.com` (create this as a forwarder)
   - Or use "Pipe to a program" with a script

4. **Create Pipe Script** (Advanced):
   ```bash
   #!/bin/bash
   # Save as /home/username/bin/email-webhook.sh
   
   # Read email from stdin
   EMAIL=$(cat)
   
   # Extract email parts
   FROM=$(echo "$EMAIL" | grep -i "^From:" | head -1)
   SUBJECT=$(echo "$EMAIL" | grep -i "^Subject:" | head -1)
   DATE=$(echo "$EMAIL" | grep -i "^Date:" | head -1)
   
   # Send to webhook
   curl -X POST https://check-outpay.com/api/v1/email/webhook \
     -H "Content-Type: application/json" \
     -d "{
       \"from\": \"$FROM\",
       \"to\": \"your-email@yourdomain.com\",
       \"subject\": \"$SUBJECT\",
       \"text\": \"$EMAIL\",
       \"html\": \"$EMAIL\",
       \"date\": \"$DATE\"
     }"
   ```

---

### Option 3: Email Forwarding Service (Easiest)

**Use a service like:**

1. **Zapier:**
   - Trigger: Gmail → New Email
   - Action: Webhook → POST to `https://check-outpay.com/api/v1/email/webhook`
   - Map email fields to webhook payload

2. **IFTTT:**
   - If: Gmail → New email from `alerts@gtbank.com`
   - Then: Webhook → POST to webhook URL

3. **Microsoft Power Automate:**
   - Trigger: When a new email arrives
   - Action: HTTP Request → POST to webhook URL

---

## Webhook Endpoint

**URL:** `https://check-outpay.com/api/v1/email/webhook`

**Method:** `POST`

**Content-Type:** `application/json`

**Payload Format:**
```json
{
  "from": "alerts@gtbank.com",
  "to": "your-email@yourdomain.com",
  "subject": "Transaction Notification",
  "text": "Email text content...",
  "html": "<html>Email HTML content...</html>",
  "date": "2026-01-07 10:30:00",
  "message_id": "unique-message-id-123"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Email received and payment matched",
  "matched": true,
  "payment_id": 123,
  "transaction_id": "TXN-1234567890-ABC123"
}
```

---

## Testing the Webhook

**Test with cURL:**
```bash
curl -X POST https://check-outpay.com/api/v1/email/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "from": "alerts@gtbank.com",
    "to": "test@example.com",
    "subject": "Transaction Notification",
    "text": "Test email content",
    "html": "<html><body>Test email content</body></html>",
    "date": "2026-01-07 10:30:00",
    "message_id": "test-123"
  }'
```

**Or use Postman:**
1. Create new POST request
2. URL: `https://check-outpay.com/api/v1/email/webhook`
3. Headers: `Content-Type: application/json`
4. Body (raw JSON): Use payload format above

---

## Benefits

✅ **Instant Processing:** Emails processed in < 1 second  
✅ **No Polling:** No need to check email servers every 5-10 minutes  
✅ **Real-time:** Payments matched immediately when email arrives  
✅ **Lower Server Load:** No IMAP/Gmail API connections  
✅ **More Reliable:** Direct HTTP POST, no connection issues  
✅ **Scalable:** Handles unlimited emails without delays  

---

## Migration from IMAP to Forwarding

1. **Set up email forwarding** (follow steps above)
2. **Keep IMAP as backup** (optional - can disable later)
3. **Test webhook** with a few emails
4. **Monitor logs** to ensure emails are being received
5. **Disable IMAP polling** once forwarding is working reliably

---

## Troubleshooting

**Email not received:**
- Check webhook URL is correct
- Verify forwarding is set up correctly
- Check server logs: `storage/logs/laravel.log`
- Test webhook endpoint manually with cURL

**Payment not matching:**
- Check email format matches expected structure
- Verify amount and sender name are extracted correctly
- Check payment time window settings
- Use "Check Match" button in admin panel to debug

**Duplicate emails:**
- Webhook uses `message_id` to prevent duplicates
- If forwarding sends same email twice, ensure `message_id` is unique

---

## Security

**Optional: Add API Key Authentication:**

Add to `routes/api.php`:
```php
Route::post('/email/webhook', [EmailWebhookController::class, 'receive'])
    ->middleware('api.key') // Add custom middleware
    ->name('email.webhook');
```

Create middleware to verify API key in request header.

---

## Summary

**Email forwarding is the FASTEST way to receive emails!**

- ⚡ **Instant** processing (< 1 second)
- 🚀 **No polling** delays
- 💪 **More reliable** than IMAP
- 📈 **Better scalability**

Set up forwarding once, and emails will arrive instantly! 🎉
