# Testing Email Processing

## ✅ How It Works

The system is now configured to:
1. **Only check emails AFTER a payment request is created**
2. **Only match emails against PENDING payments**
3. **Ignore old emails** that arrived before payment requests

## 🧪 Testing Methods

### Method 1: Test Command (Recommended)

Run the test command to simulate email processing:

```bash
php artisan payment:test-email-processing
```

This will:
- Show all pending payments
- Simulate an email notification
- Process the email and match it to a payment
- Show the result

**Test specific transaction:**
```bash
php artisan payment:test-email-processing --transaction-id=TXN-123456
```

### Method 2: Real Email Test

1. **Create a Payment Request:**
   ```bash
   # Via API or Admin Panel
   POST /api/v1/payment-request
   {
     "amount": 5000,
     "payer_name": "John Doe",
     "webhook_url": "https://your-site.com/webhook"
   }
   ```

2. **Send a Real Email:**
   - Send an email to your monitored Gmail account
   - Email should contain:
     - Amount: ₦5000 (or 5000)
     - Payer name: John Doe
   - Example subject: "Bank Transfer Notification"
   - Example body: "You received ₦5000 from John Doe"

3. **Run Email Monitor:**
   ```bash
   php artisan payment:monitor-emails
   ```

4. **Check Result:**
   - Payment should be approved
   - Webhook should be sent
   - Check logs: `storage/logs/laravel.log`

### Method 3: Check Logs

Monitor logs in real-time:

```bash
tail -f storage/logs/laravel.log | grep -i "email\|payment\|match"
```

## 📋 Email Format Examples

### Example 1: Simple Format
```
Subject: Bank Transfer Notification
Body: You received ₦5000 from John Doe
```

### Example 2: Detailed Format
```
Subject: Credit Alert - ₦5000
Body: 
Amount: ₦5,000.00
From: John Doe
Account: 1234567890
Date: 2024-01-07
```

### Example 3: Bank Format
```
Subject: Transaction Alert
Body:
Dear Customer,
You have received a transfer of ₦5,000.00 from John Doe.
Transaction Date: 2024-01-07
```

## ✅ Verification Checklist

After testing, verify:

- [ ] Email was received (check logs)
- [ ] Payment info extracted (amount, name)
- [ ] Payment matched to pending transaction
- [ ] Payment status changed to APPROVED
- [ ] Webhook sent to business
- [ ] Business balance updated
- [ ] Transaction logged

## 🔍 Debugging

### Check Pending Payments
```bash
php artisan tinker
>>> App\Models\Payment::pending()->get(['transaction_id', 'amount', 'payer_name', 'created_at']);
```

### Check Email Processing
```bash
php artisan payment:monitor-emails --verbose
```

### View Recent Logs
```bash
tail -n 100 storage/logs/laravel.log | grep -A 5 -B 5 "email\|payment"
```

## 🎯 Key Points

1. **Only processes emails after payment request** - Old emails are ignored
2. **Only matches pending payments** - Already approved/rejected payments are skipped
3. **Amount must match exactly** - Within 0.01 tolerance
4. **Name must match if provided** - Case-insensitive, exact match required
5. **Emails are marked as read** - After processing to avoid duplicates

## 🚀 Quick Test

```bash
# 1. Create a test payment (via API or admin)
# 2. Run test command
php artisan payment:test-email-processing

# 3. Or monitor real emails
php artisan payment:monitor-emails
```

---

**The system is now safe - it won't process old emails!** ✅
