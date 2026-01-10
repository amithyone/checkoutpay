# Match Attempts Logging - Complete! ✅

## What Was Fixed

### 1. **Complete Integration**
- ✅ `extractPaymentInfo` now returns `['data' => [...], 'method' => '...']` format
- ✅ `matchEmail` logs all attempts to `match_attempts` table
- ✅ `matchPayment` returns similarity percent, amount diff, time diff
- ✅ `namesMatch` returns `['matched' => bool, 'similarity' => int]`
- ✅ All callers updated to handle new format and pass `processed_email_id`

### 2. **GTBank Email Extraction Improvements**
- ✅ Added pattern to extract name from "AMITHY ONE M TRF FOR CUSTOMER..." format
- ✅ Fixed amount extraction to handle `NGN&nbsp;1000` format (HTML entities)
- ✅ Improved description field parsing for GTBank emails

### 3. **Database Logging**
- ✅ Every match attempt is now logged to `match_attempts` table
- ✅ Stores: amounts, names, reasons, similarity %, time diff, extraction method
- ✅ Updates `processed_emails.last_match_reason` and `match_attempts_count`
- ✅ Stores HTML/text snippets for debugging (first 500 chars)

## How to Test

### Step 1: Pull Latest Code
```bash
cd /home/checzspw/public_html && git pull origin main
```

### Step 2: Run Migrations
```bash
php artisan migrate --force
```

### Step 3: Create a Test Transaction
1. Go to admin panel
2. Create a new payment request with:
   - Amount: 1000
   - Payer Name: "amithy one m" (or match what's in email)

### Step 4: Send Test Email
Send a GTBank email notification with the transaction details.

### Step 5: Check Match Attempts
```bash
php artisan tinker
```

Then run:
```php
// Check match attempts
\App\Models\MatchAttempt::latest()->take(10)->get(['transaction_id', 'match_result', 'reason', 'extraction_method', 'amount_diff', 'name_similarity_percent', 'created_at']);

// Check specific transaction attempts
\App\Models\MatchAttempt::where('transaction_id', 'YOUR_TXN_ID')->get(['match_result', 'reason', 'extraction_method', 'amount_diff', 'name_similarity_percent']);

// Check unmatched emails with reasons
\App\Models\ProcessedEmail::where('is_matched', false)
    ->whereNotNull('last_match_reason')
    ->latest()
    ->get(['id', 'subject', 'amount', 'sender_name', 'last_match_reason', 'match_attempts_count', 'extraction_method']);

// Get extraction details
$email = \App\Models\ProcessedEmail::find(EMAIL_ID);
$email->extracted_data; // See what was extracted
$email->extraction_method; // See method used
$email->last_match_reason; // See why it didn't match
```

### Step 6: View in Database
```sql
-- View all match attempts
SELECT 
    transaction_id,
    match_result,
    reason,
    extraction_method,
    payment_amount,
    extracted_amount,
    amount_diff,
    payment_name,
    extracted_name,
    name_similarity_percent,
    time_diff_minutes,
    created_at
FROM match_attempts 
ORDER BY created_at DESC 
LIMIT 50;

-- View common failure reasons
SELECT 
    reason,
    COUNT(*) as count,
    extraction_method
FROM match_attempts 
WHERE match_result = 'unmatched'
GROUP BY reason, extraction_method
ORDER BY count DESC;

-- View extraction method performance
SELECT 
    extraction_method,
    match_result,
    COUNT(*) as count
FROM match_attempts
GROUP BY extraction_method, match_result
ORDER BY extraction_method, match_result;
```

## What Gets Logged

For **every** match attempt, the system logs:

1. **Payment Details:**
   - Transaction ID
   - Amount
   - Payer Name
   - Account Number
   - Created At

2. **Extracted Email Details:**
   - Amount extracted
   - Sender name extracted
   - Account number extracted
   - Email subject
   - Email from
   - Email date

3. **Comparison Metrics:**
   - Amount difference
   - Name similarity percent (0-100)
   - Time difference in minutes

4. **Extraction Method:**
   - `html_table` - HTML table extraction (most accurate)
   - `html_text` - HTML text extraction
   - `rendered_text` - Rendered text extraction
   - `template` - Bank template extraction
   - `fallback` - Fallback method

5. **Match Result:**
   - `matched` - Payment matched successfully
   - `unmatched` - Payment didn't match
   - `rejected` - Payment rejected (e.g., amount too low)
   - `partial` - Partial match (for future use)

6. **Detailed Reason:**
   - Full explanation why it matched or didn't match
   - Includes amounts, names, time windows, etc.

7. **Debugging Info:**
   - HTML snippet (first 500 chars)
   - Text snippet (first 300 chars)
   - Full details JSON

## Example Match Attempt Data

```json
{
  "transaction_id": "TXN-1234567890-abc",
  "match_result": "unmatched",
  "reason": "Name mismatch: expected \"amithy one m\", got \"amithy one\" (similarity: 66%)",
  "payment_amount": 1000.00,
  "extracted_amount": 1000.00,
  "amount_diff": 0.00,
  "payment_name": "amithy one m",
  "extracted_name": "amithy one",
  "name_similarity_percent": 66,
  "time_diff_minutes": 2,
  "extraction_method": "html_table",
  "details": {
    "match_details": {...},
    "extracted_info": {...},
    "payment_data": {...}
  }
}
```

## For Your GTBank Email

Based on the email you provided:
- **Amount:** `NGN 1000` → Extracted as `1000.00`
- **Description:** `090405260110014006799532206126-AMITHY ONE M TRF FOR CUSTOMERAT126TRF2MPT4E0RT200`
- **Name to extract:** `AMITHY ONE M` (from description field)

The new pattern should extract:
- Amount: `1000` ✅
- Name: `amithy one m` ✅ (from "AMITHY ONE M TRF FOR" pattern)

## Next Steps

1. **Pull latest code** on server
2. **Run migrations** (if not already done)
3. **Test with a transaction** - create payment and check match_attempts table
4. **Review the JSON** - see exactly what the system is trying to match
5. **Analyze patterns** - use the logged data to improve matching accuracy

## Troubleshooting

**If no match attempts are being logged:**

1. Check logs:
   ```bash
   tail -f storage/logs/laravel.log | grep -i "match"
   ```

2. Check if processed_email_id is being passed:
   ```php
   // In tinker
   $email = \App\Models\ProcessedEmail::latest()->first();
   $email->id; // Should exist
   ```

3. Check match_attempts table:
   ```sql
   SELECT COUNT(*) FROM match_attempts;
   ```

**If extraction is failing:**

Check the extraction_method:
```php
$email = \App\Models\ProcessedEmail::latest()->first();
$email->extraction_method; // Should show method used
$email->extracted_data; // Should show extracted info
```

---

**Status:** ✅ Complete and ready to test!
**Commit:** `b92e41b`
**Files Changed:** PaymentMatchingService, ReadEmailsDirect, CheckPaymentEmails, match attempts logging
