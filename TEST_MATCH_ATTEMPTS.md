# Test Match Attempts Logging - Quick Guide

## ✅ What's Complete

1. ✅ **Database Table:** `match_attempts` created with indexes
2. ✅ **Logging Integration:** All match attempts logged to database
3. ✅ **GTBank Extraction:** Improved patterns for "AMITHY ONE M TRF FOR" format
4. ✅ **Processed Email ID:** Passed to all matching calls for logging
5. ✅ **Extraction Method:** Tracked (html_table, rendered_text, template, fallback)
6. ✅ **Similarity Metrics:** Name similarity %, amount diff, time diff all calculated

## 🧪 How to Test

### Step 1: Pull and Migrate
```bash
cd /home/checzspw/public_html
git pull origin main
php artisan migrate --force
php artisan cache:clear
php artisan config:clear
```

### Step 2: Create Test Transaction
1. Go to admin panel
2. Create payment request:
   - Amount: **1000**
   - Payer Name: **amithy one m** (or match email)
   - Account Number: **3002156642** (if needed)

### Step 3: Process Email
Send GTBank email notification or run:
```bash
# Fetch emails from filesystem (if email is already on server)
php artisan payment:read-emails-direct --all

# OR fetch via IMAP
php artisan payment:monitor-emails

# OR trigger matching check
php artisan payment:check-match TXN_ID
```

### Step 4: Check Match Attempts

#### In Tinker (Quick Check):
```bash
php artisan tinker
```

```php
// Check recent match attempts
\App\Models\MatchAttempt::latest()
    ->with('payment', 'processedEmail')
    ->take(10)
    ->get([
        'transaction_id', 
        'match_result', 
        'reason', 
        'extraction_method',
        'payment_amount',
        'extracted_amount',
        'amount_diff',
        'payment_name',
        'extracted_name',
        'name_similarity_percent',
        'time_diff_minutes',
        'created_at'
    ]);

// Check specific transaction
\App\Models\MatchAttempt::where('transaction_id', 'YOUR_TXN_ID')
    ->get(['match_result', 'reason', 'extraction_method', 'amount_diff', 'name_similarity_percent']);

// Check unmatched emails with reasons
\App\Models\ProcessedEmail::where('is_matched', false)
    ->whereNotNull('last_match_reason')
    ->latest()
    ->get(['id', 'subject', 'amount', 'sender_name', 'last_match_reason', 'match_attempts_count', 'extraction_method']);
```

#### Direct SQL Query:
```sql
-- View all match attempts
SELECT 
    id,
    transaction_id,
    match_result,
    LEFT(reason, 100) as reason_short,
    extraction_method,
    payment_amount,
    extracted_amount,
    amount_diff,
    payment_name,
    extracted_name,
    name_similarity_percent,
    time_diff_minutes,
    processing_time_ms,
    created_at
FROM match_attempts 
ORDER BY created_at DESC 
LIMIT 50;

-- View JSON details for a specific attempt
SELECT 
    transaction_id,
    match_result,
    reason,
    details,
    html_snippet,
    text_snippet
FROM match_attempts 
WHERE transaction_id = 'YOUR_TXN_ID'
ORDER BY created_at DESC
LIMIT 5;

-- Common failure reasons
SELECT 
    LEFT(reason, 150) as reason,
    extraction_method,
    COUNT(*) as count
FROM match_attempts 
WHERE match_result = 'unmatched'
GROUP BY reason, extraction_method
ORDER BY count DESC
LIMIT 20;

-- Extraction method performance
SELECT 
    extraction_method,
    match_result,
    COUNT(*) as count,
    AVG(processing_time_ms) as avg_time_ms
FROM match_attempts
GROUP BY extraction_method, match_result
ORDER BY extraction_method, match_result;
```

### Step 5: View Transaction JSON

To see what the system is trying to match, check the `details` JSON field:

```php
// In tinker
$attempt = \App\Models\MatchAttempt::latest()->first();
$attempt->details; // See full JSON with all comparison data
```

Or in SQL:
```sql
SELECT 
    transaction_id,
    match_result,
    JSON_PRETTY(details) as details_json
FROM match_attempts 
WHERE transaction_id = 'YOUR_TXN_ID'
ORDER BY created_at DESC
LIMIT 1;
```

## 📊 What You'll See

### For Your GTBank Email:
- **Amount:** `NGN 1000` → Extracted as `1000.00`
- **Description:** `090405260110014006799532206126-AMITHY ONE M TRF FOR CUSTOMERAT126TRF2MPT4E0RT200`
- **Name Extracted:** `amithy one m` (from "AMITHY ONE M TRF FOR" pattern)
- **Extraction Method:** `html_table` (most accurate)

### Match Attempt Data Example:
```json
{
  "transaction_id": "TXN-1234567890-abc",
  "match_result": "unmatched",
  "reason": "Name mismatch: expected \"amithy one m\", got \"amithy one\" (similarity: 66%)",
  "extraction_method": "html_table",
  "payment_amount": 1000.00,
  "extracted_amount": 1000.00,
  "amount_diff": 0.00,
  "payment_name": "amithy one m",
  "extracted_name": "amithy one",
  "name_similarity_percent": 66,
  "time_diff_minutes": 2,
  "details": {
    "match_details": {...},
    "extracted_info": {...},
    "payment_data": {...}
  }
}
```

## 🔍 Debugging

### If No Match Attempts Are Logged:

1. **Check if emails are being processed:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i "match\|extract"
   ```

2. **Check if processed_email_id is being passed:**
   ```php
   // In tinker
   $email = \App\Models\ProcessedEmail::latest()->first();
   echo "Email ID: " . $email->id . "\n";
   echo "Extraction Method: " . ($email->extraction_method ?? 'null') . "\n";
   echo "Match Attempts: " . $email->match_attempts_count . "\n";
   ```

3. **Manually trigger matching:**
   ```bash
   # Check specific transaction
   php artisan tinker
   >>> $payment = \App\Models\Payment::where('transaction_id', 'YOUR_TXN_ID')->first();
   >>> $matchingService = new \App\Services\PaymentMatchingService();
   >>> $matchingService->matchEmail([...]);
   ```

### If Extraction is Failing:

Check the extraction:
```php
// In tinker
$email = \App\Models\ProcessedEmail::latest()->first();
$matchingService = new \App\Services\PaymentMatchingService();
$result = $matchingService->extractPaymentInfo([
    'subject' => $email->subject,
    'from' => $email->from_email,
    'text' => $email->text_body,
    'html' => $email->html_body,
    'date' => $email->email_date->toDateTimeString(),
]);
$result; // Should show ['data' => [...], 'method' => '...']
```

### View Email HTML/Text:

```php
// In tinker
$email = \App\Models\ProcessedEmail::latest()->first();
echo "HTML (first 1000 chars):\n";
echo substr($email->html_body, 0, 1000);
echo "\n\nText Body:\n";
echo substr($email->text_body, 0, 500);
```

## 🎯 Expected Results

After creating a transaction and processing the email:

1. ✅ **Email stored** in `processed_emails` table
2. ✅ **Extraction method** stored (html_table, rendered_text, etc.)
3. ✅ **Match attempt logged** in `match_attempts` table
4. ✅ **Reason stored** in `last_match_reason` if unmatched
5. ✅ **Match attempts count** incremented
6. ✅ **Full JSON details** available for analysis

## 📝 Next Steps After Testing

1. **Review match attempts** to see why transactions aren't matching
2. **Analyze patterns** - use SQL queries to find common issues
3. **Adjust matching logic** based on logged data
4. **Improve extraction** based on extraction_method performance

---

**Ready to test!** Pull the latest code and run the commands above. 🚀
