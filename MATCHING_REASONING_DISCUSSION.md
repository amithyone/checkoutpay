# Matching Reasoning & Improvement System - Discussion Document

## 🎯 Goal

Add detailed reasoning for why transactions don't match, so we can:
1. **Understand failures** - See exactly why a transaction wasn't matched
2. **Improve matching** - Use failure data to improve the matching algorithm
3. **Train the system** - Build a dataset of matches/mismatches for learning

## 📋 Current State

The `matchPayment()` method already returns reasons for mismatches:
- ✅ Amount mismatch reasons
- ✅ Name mismatch reasons  
- ✅ Time window reasons
- ✅ Logs to `storage/logs/laravel.log` at DEBUG level

**Problem:** Debug logs aren't easily accessible and reasons aren't stored for analysis.

## 🎨 Proposed Solutions

### Option 1: Separate Match Log File (Recommended for Now)

**Pros:**
- ✅ Easy to access and copy from server
- ✅ Doesn't clutter main Laravel logs
- ✅ Can be easily parsed for analysis
- ✅ Quick to implement

**Cons:**
- ❌ Not in database (harder to query)
- ❌ Need file access to read

**Implementation:**
- Create `storage/logs/match_attempts.log` file
- Log every matching attempt with:
  - Transaction ID
  - Email subject/from
  - Extracted info (amount, name, account)
  - Expected info (amount, name)
  - Match result (matched/unmatched)
  - Detailed reason
  - Timestamp

**Format:**
```json
{
  "timestamp": "2025-01-20 10:30:15",
  "transaction_id": "TXN-1234567890-abc",
  "payment_amount": 5000.00,
  "payment_name": "john doe",
  "email_subject": "Credit Alert",
  "email_from": "alerts@gtbank.com",
  "extracted_amount": 5000.00,
  "extracted_name": "john doe smith",
  "match_result": "unmatched",
  "reason": "Name mismatch: expected \"john doe\", got \"john doe smith\"",
  "details": {
    "name_similarity": "66%",
    "amount_diff": 0,
    "time_diff_minutes": 2,
    "extraction_method": "html_table"
  }
}
```

### Option 2: Database Table for Match Attempts (Better for Training)

**Pros:**
- ✅ Queryable - Easy to analyze patterns
- ✅ Can build admin UI to view attempts
- ✅ Can mark attempts as "correct match" or "incorrect match" for training
- ✅ Can calculate statistics and improve matching algorithm

**Cons:**
- ❌ More complex to implement
- ❌ Database will grow over time (need cleanup strategy)

**Table Structure:**
```sql
match_attempts:
- id
- payment_id (nullable - can attempt without payment)
- processed_email_id (nullable - can attempt without email)
- transaction_id
- payment_amount
- payment_name
- email_subject
- email_from
- extracted_amount
- extracted_name
- match_result (matched/unmatched/rejected)
- reason (detailed text)
- details (JSON - name_similarity, amount_diff, time_diff, etc.)
- extraction_method (html_table/html_text/html_rendered/text_only)
- html_body (nullable - store HTML for analysis)
- text_body (nullable - store text for analysis)
- manual_review_status (null/pending/reviewed/correct/incorrect)
- manual_review_notes (nullable)
- created_at
- updated_at
```

### Option 3: Both (Log File + Database)

**Pros:**
- ✅ Best of both worlds
- ✅ Log file for quick access/copying
- ✅ Database for analysis and training

**Cons:**
- ❌ More storage required
- ❌ Need to keep both in sync

## 🔍 HTML vs Rendered View Discussion

### Current Approach: Using HTML Directly

**What we're doing now:**
- Extracting from raw HTML using regex patterns
- Looking for `<td>Amount</td><td>NGN 5000</td>` structures
- Using HTML patterns for banks like GTBank

**Pros:**
- ✅ More accurate - preserves exact structure
- ✅ Can target specific table cells/fields
- ✅ Handles complex bank email formats
- ✅ Works for most Nigerian banks (GTBank, Access, etc.)

**Cons:**
- ❌ HTML can vary between email clients
- ❌ Complex patterns needed for each bank
- ❌ HTML can have different formatting/spacing
- ❌ Hard to debug (need to look at raw HTML)

### Alternative: Using Rendered View (Stripped/Cleaned HTML)

**What this means:**
- Strip HTML to clean text but preserve structure
- Convert HTML tables to structured text
- Use cleaner patterns on the rendered text

**Example:**
```
HTML: <td>Amount</td><td>NGN 5,000.00</td>
Rendered: Amount : NGN 5,000.00
```

**Pros:**
- ✅ Cleaner patterns - easier to write
- ✅ Less dependent on HTML structure
- ✅ More readable for debugging
- ✅ Can use simpler regex patterns

**Cons:**
- ❌ Might lose some structure information
- ❌ Still need to handle multiple formats
- ❌ Some banks use complex nested HTML

### Recommendation: Hybrid Approach ✅

**Use HTML for structured data (tables), rendered text for fallback:**

1. **Primary:** Extract from HTML tables (current approach)
   - Most accurate for banks with structured HTML
   - GTBank, Access Bank use HTML tables

2. **Fallback:** Use rendered/cleaned text
   - If HTML extraction fails
   - For banks without table structures
   - Simpler patterns for common fields

3. **Store both:**
   - HTML for primary extraction
   - Rendered text for fallback and debugging
   - Log which method was used

**Implementation:**
```php
// Try HTML table first (most accurate)
if (preg_match('/<td[^>]*>Amount[\s:]*<\/td>\s*<td[^>]*>NGN\s*([\d,]+)/i', $html, $matches)) {
    $amount = $matches[1];
    $extractionMethod = 'html_table';
} 
// Fallback to rendered text
elseif (preg_match('/Amount[\s:]+NGN\s*([\d,]+)/i', $renderedText, $matches)) {
    $amount = $matches[1];
    $extractionMethod = 'rendered_text';
}
```

## 📊 Proposed Implementation Plan

### Phase 1: Enhanced Logging (Immediate - Quick Win)

1. **Create separate match log file:**
   - `storage/logs/match_attempts.log`
   - JSON format for easy parsing
   - Log every matching attempt with full details

2. **Enhance existing logging:**
   - Change mismatch logs from DEBUG to INFO level
   - Add detailed reasons to all matching attempts
   - Include extraction method used

3. **Add reason field to ProcessedEmail model:**
   - `last_match_reason` - Why it didn't match (if unmatched)
   - `match_attempts_count` - How many times we tried to match

### Phase 2: Database Table (Next Step)

1. **Create `match_attempts` table:**
   - Store every matching attempt
   - Link to payments and processed_emails
   - Store full details for analysis

2. **Admin UI for reviewing:**
   - View unmatched attempts
   - Mark attempts as "correct" or "incorrect"
   - Filter by reason type
   - Statistics dashboard

3. **Training dataset:**
   - Export matches/mismatches for ML training
   - Build confidence scores based on historical data

### Phase 3: Hybrid HTML/Rendered Extraction

1. **Improve extraction to use both methods:**
   - Try HTML first (current)
   - Fallback to rendered text
   - Log which method succeeded

2. **A/B testing:**
   - Compare extraction accuracy
   - Choose best method per bank
   - Fine-tune patterns based on results

## 🎯 My Recommendations

### For Now (Quick Implementation):

1. **✅ Option 1: Separate Match Log File**
   - Fast to implement
   - Easy to access and copy
   - You can send me log entries for analysis

2. **✅ Add detailed reasons to ProcessedEmail**
   - Store last match reason in database
   - Show in admin panel why email didn't match
   - Easy to see patterns

3. **✅ Keep HTML extraction, add rendered fallback**
   - HTML is working, don't break it
   - Add rendered text as backup
   - Compare which works better

### For Future (Better System):

1. **Database table for match attempts**
   - Build admin UI
   - Training dataset
   - Automated improvement

## 📝 What I Need From You

**Please tell me:**
1. **Which option do you prefer?** (Log file, Database, or Both?)
2. **HTML vs Rendered:** Do you want to try rendered view or keep HTML?
3. **Priority:** Quick log file now, or build database table first?

**My suggestion:** Start with Option 1 (log file) + add reasons to ProcessedEmail, then move to database table once we see the patterns.

---

## 💬 Questions for Discussion

1. **Storage:** Do you have concerns about database size if we store all attempts?
2. **Privacy:** Should we store full HTML/text in logs/database? (might contain sensitive info)
3. **Retention:** How long should we keep match attempts? (1 month? 3 months?)
4. **Manual Review:** Do you want ability to manually review and correct matches in admin panel?
5. **Bank-Specific:** Should different banks use different extraction methods?

**Let me know your thoughts and I'll implement accordingly!** 🚀
