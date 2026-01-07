# Transaction Matching System Explained

## Overview

The transaction matching system connects **payment requests** from your website with **bank transfer notifications** received via email. When a customer initiates a payment, the system waits for the bank's email notification and automatically matches it to approve the payment.

---

## 🔄 Complete Flow

### Step 1: Payment Request Created
```
Website → API → PaymentService → Creates Payment Record
```

**What happens:**
- Customer initiates payment on your website
- Website sends POST request to `/api/v1/payment-request`
- System creates a `Payment` record with:
  - Unique `transaction_id` (e.g., "TXN-1234567890-ABC123")
  - `amount` (e.g., 5000.00)
  - `payer_name` (optional, e.g., "John Doe")
  - `business_id` (which business this payment belongs to)
  - `status` = "pending"
  - `account_number` (assigned from pool or business-specific)

**Immediate Check:**
- After creating payment, system checks **stored emails** in database
- If matching email found → Payment approved immediately
- If not found → Payment stays "pending" and waits for email

---

### Step 2: Email Monitoring
```
Cron Job / Manual Trigger → MonitorEmails Command → Fetches Emails
```

**What happens:**
- Cron job runs every 5-10 minutes (or manually triggered)
- System connects to email accounts (IMAP or Gmail API)
- Fetches emails **since last run** (optimized to avoid re-processing)
- Stores ALL emails in `processed_emails` table

**Email Storage:**
- Every email is stored with:
  - `subject`, `from_email`, `html_body`, `text_body`
  - `email_date` (when email was received)
  - `email_account_id` (which email account received it)

---

### Step 3: Payment Info Extraction
```
Email → PaymentMatchingService → Extracts Amount, Sender Name, etc.
```

**Extraction Process:**

1. **Check Bank Templates First:**
   - System checks if email matches any `bank_email_templates`
   - Templates have high priority (e.g., GTBank = 100)
   - Uses template-specific extraction patterns

2. **Template-Based Extraction:**
   - If GTBank template found → Uses GTBank parser
   - Extracts from HTML table structure:
     - Amount from "Amount" row
     - Sender name from "Description" row (FROM <NAME> TO pattern)
     - Account number from "Account Number" row

3. **Fallback Extraction:**
   - If no template matches → Uses default regex patterns
   - Searches for common patterns like:
     - `NGN 5,000.00` or `₦5000`
     - `FROM JOHN DOE TO`
     - Amount patterns in various formats

**Extracted Data:**
```php
[
    'amount' => 5000.00,
    'sender_name' => 'john doe',
    'account_number' => '1234567890',
    'email_subject' => 'Transaction Notification',
    'email_from' => 'alerts@gtbank.com'
]
```

---

### Step 4: Matching Process
```
Extracted Info → PaymentMatchingService → Checks Against Pending Payments
```

**Matching Criteria (ALL must pass):**

#### ✅ 1. Amount Match
```php
// Amount must match exactly (with 1 kobo tolerance)
$amountDiff = abs($payment->amount - $extractedInfo['amount']);
if ($amountDiff > 0.01) {
    return 'Amount mismatch';
}
```
- **Example:** Payment = ₦5,000.00, Email = ₦5,000.00 ✅
- **Example:** Payment = ₦5,000.00, Email = ₦5,000.01 ✅ (within tolerance)
- **Example:** Payment = ₦5,000.00, Email = ₦4,999.99 ❌ (too different)

#### ✅ 2. Name Match (if payer_name provided)
```php
// Uses 70% similarity algorithm
if (!$this->namesMatch($expectedName, $receivedName)) {
    return 'Name mismatch';
}
```

**Name Matching Algorithm:**
- **Exact Match:** "John Doe" = "John Doe" ✅
- **Order Variation:** "John Doe" matches "Doe John" ✅
- **Partial Match:** "Amithy One Media" matches "Amithy One" ✅ (2/3 words = 67%, but major words match)
- **Word Similarity:** Uses `similar_text()` with 70% threshold
- **Example:** "Innocent Solomon" matches "Solomon Innocent Amithy" ✅

**Why 70%?**
- Handles variations in name order
- Allows for middle names or extra words
- Prevents false matches while being flexible

#### ✅ 3. Time Window Validation
```php
// Email must arrive AFTER payment request
if ($emailTime < $paymentTime) {
    return 'Email received BEFORE transaction';
}

// Email must arrive within configured window (default: 120 minutes)
if ($timeDiff > $timeWindowMinutes) {
    return 'Time window exceeded';
}
```

**Time Rules:**
- Email **MUST** arrive **AFTER** payment request created
- Email must arrive within **120 minutes** (configurable in Settings)
- Both times normalized to `Africa/Lagos` timezone

**Example:**
- Payment created: `2026-01-07 10:00:00`
- Email received: `2026-01-07 10:15:00` ✅ (15 minutes later)
- Email received: `2026-01-07 12:30:00` ❌ (150 minutes = exceeds 120 min window)
- Email received: `2026-01-07 09:45:00` ❌ (arrived BEFORE payment)

#### ✅ 4. Email Account Matching
```php
// If business has email account assigned:
// - Only match emails from that account
// If business has NO email account:
// - Match from ANY email account
```

**Business Email Account Logic:**
- **Business A** has `email_account_id = 1` → Only matches emails from account 1
- **Business B** has `email_account_id = NULL` → Matches emails from ANY account
- This allows flexibility for businesses without dedicated email accounts

#### ✅ 5. Duplicate Prevention
```php
// Check for duplicate payments in last 1 hour
$duplicate = Payment::where('amount', $extractedInfo['amount'])
    ->where('payer_name', $extractedInfo['sender_name'])
    ->where('status', 'approved')
    ->where('created_at', '>=', now()->subHour())
    ->first();
```

**Prevents:**
- Same email matching multiple payments
- Re-processing already approved payments
- Double crediting business balance

---

### Step 5: Payment Approval
```
Match Found → Payment Approved → Webhook Sent → Business Balance Updated
```

**What happens when match found:**
1. **Mark Email as Matched:**
   ```php
   $storedEmail->markAsMatched($payment);
   ```

2. **Approve Payment:**
   ```php
   $payment->approve($emailData);
   // Sets status = 'approved'
   // Stores email details in payment record
   ```

3. **Update Business Balance:**
   ```php
   $payment->business->increment('balance', $payment->amount);
   ```

4. **Send Webhook:**
   ```php
   event(new PaymentApproved($payment));
   // Sends POST request to business webhook_url
   ```

5. **Log Transaction:**
   - Creates `transaction_log` entry
   - Records all details for audit trail

---

## 🏦 GTBank Transaction Matching

### Special GTBank Flow

**Detection:**
```php
// Checks if email is from GTBank
$isGtbankDomain = str_contains($from, '@gtbank.com');
$hasTransactionNotification = str_contains($subject, 'transaction notification');
```

**Parsing:**
- Uses `GtbankTransactionParser` service
- Extracts from HTML table structure:
  - Account Number
  - Amount (strips NGN/₦ and commas)
  - Sender Name (FROM <NAME> TO pattern)
  - Transaction Type (CREDIT/DEBIT)
  - Value Date
  - Narration (full description)

**Duplicate Prevention:**
```php
// Generates SHA256 hash
$hash = hash('sha256', json_encode([
    'account_number' => '1234567890',
    'amount' => '5000.00',
    'value_date' => '2026-01-07',
    'narration' => 'FROM JOHN DOE TO BUSINESS'
]));

// Checks if hash exists
if (GtbankTransaction::where('duplicate_hash', $hash)->exists()) {
    return null; // Skip duplicate
}
```

**Storage:**
- Creates `GtbankTransaction` record
- Links to `ProcessedEmail` and `BankEmailTemplate`
- Can be used for reconciliation and reporting

---

## 🔍 Matching Scenarios

### Scenario 1: Perfect Match
```
Payment Request:
- Amount: ₦5,000.00
- Payer: "John Doe"
- Created: 10:00 AM

Email Received:
- Amount: ₦5,000.00
- Sender: "JOHN DOE"
- Received: 10:15 AM

Result: ✅ MATCHED (amount matches, name matches, within time window)
```

### Scenario 2: Name Variation
```
Payment Request:
- Amount: ₦5,000.00
- Payer: "Amithy One Media"
- Created: 10:00 AM

Email Received:
- Amount: ₦5,000.00
- Sender: "AMITHY ONE"
- Received: 10:15 AM

Result: ✅ MATCHED (2/3 words match = 67%, but major words match)
```

### Scenario 3: Time Window Exceeded
```
Payment Request:
- Amount: ₦5,000.00
- Created: 10:00 AM

Email Received:
- Amount: ₦5,000.00
- Received: 1:00 PM (3 hours later)

Result: ❌ NOT MATCHED (exceeds 120-minute window)
```

### Scenario 4: Amount Mismatch
```
Payment Request:
- Amount: ₦5,000.00

Email Received:
- Amount: ₦4,500.00

Result: ❌ NOT MATCHED (amount difference > 1 kobo tolerance)
```

### Scenario 5: Email Arrived Before Payment
```
Payment Request:
- Created: 10:00 AM

Email Received:
- Received: 9:45 AM

Result: ❌ NOT MATCHED (email arrived BEFORE payment request)
```

---

## 📊 Matching Priority

1. **Bank Templates** (Priority 100)
   - GTBank template checked first
   - Uses specialized parsing

2. **Stored Emails Check**
   - Checks emails already in database
   - Re-extracts from `html_body` for accuracy

3. **Pending Payments Check**
   - Iterates through all pending payments
   - Stops at first match

4. **Duplicate Check**
   - Prevents re-processing same email
   - Checks last 1 hour of approved payments

---

## 🛠️ Manual Matching

**Admin Panel:**
- View all stored emails in "Inbox"
- Click "Check Match" button on unmatched emails
- System re-extracts and attempts matching
- Shows detailed match results

**API Endpoint:**
- `POST /api/v1/transaction/check?transaction_id=TXN123`
- Triggers email check for specific transaction
- Returns match status

---

## ⚙️ Configuration

**Settings (Admin Panel):**
- **Payment Time Window:** Default 120 minutes
  - Configurable in `/admin/settings`
  - Maximum time after payment creation for email to be valid

**Bank Templates:**
- Create templates for different banks
- Set priority (higher = checked first)
- Define extraction patterns
- Map HTML table fields

---

## 🔐 Security & Validation

1. **Duplicate Prevention:**
   - SHA256 hash for GTBank transactions
   - Time-based duplicate check for payments

2. **Time Validation:**
   - Prevents matching old emails
   - Ensures email arrived after payment request

3. **Amount Validation:**
   - Exact match required (1 kobo tolerance)
   - Prevents rounding errors

4. **Name Validation:**
   - 70% similarity threshold
   - Handles variations while preventing false matches

---

## 📝 Logging

All matching activities are logged:
- Email extraction attempts
- Match successes/failures
- Duplicate detections
- Time window violations
- Name mismatches

Check logs in: `storage/logs/laravel.log`

---

## 🎯 Summary

The matching system is **intelligent, flexible, and secure**:

✅ **Flexible Name Matching** - Handles variations and order changes  
✅ **Time Window Validation** - Ensures emails arrive after payment requests  
✅ **Duplicate Prevention** - Prevents double processing  
✅ **Bank-Specific Parsing** - GTBank and other banks supported  
✅ **Template-Based Extraction** - Easy to add new banks  
✅ **Real-Time Matching** - Checks stored emails immediately  
✅ **Manual Override** - Admin can manually trigger matching  

The system ensures **accurate, secure, and efficient** payment processing! 🚀
