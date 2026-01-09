# Enhanced Email Fetching with Detailed Logging

## ✅ What I Added

I've added comprehensive logging to the email fetching process so you can see exactly what's happening when emails are fetched.

## 📋 New Logging Features

### 1. **Startup Logging**
- Logs connection details (host, port, folder)
- Logs the `sinceDate` being used
- Shows timezone information

### 2. **Per-Email Logging**
For each email found, logs:
- Message ID
- Subject
- From email
- Date
- Why it was skipped (if applicable):
  - No message ID
  - Already stored
  - Already processed
  - Sender not allowed
  - Before last processed message

### 3. **Summary Logging**
- Total emails found
- How many were already stored
- How many were newly stored
- How many were processed
- How many were skipped
- The date range being searched

## 🔧 New Command Options

### Fetch ALL Emails (Regardless of Date)
```bash
php artisan payment:monitor-emails --all
```
Fetches ALL emails from the server (going back 10 years)

### Fetch Emails from Last N Days
```bash
php artisan payment:monitor-emails --days=30
```
Fetches emails from the last 30 days (default is 7 days now, was 24 hours)

### Fetch Emails Since Specific Date
```bash
php artisan payment:monitor-emails --since='2025-01-01 00:00:00'
```
Fetches emails since a specific date

## 🔍 Why Your 5 Emails Aren't Being Fetched

The code was only fetching emails from the **last 24 hours** by default. If your 5 emails are older, they won't be fetched.

**Fixed:** Now defaults to **last 7 days**, and you can use `--all` to fetch everything.

### Common Reasons Emails Are Skipped:

1. **Already Stored** - Email is already in database
   - Check: Look for "Already stored" in logs
   
2. **Older Than SinceDate** - Email is too old
   - Fix: Use `--all` or `--days=30` flag
   
3. **Sender Not Allowed** - Filtered by "Allowed Senders"
   - Fix: Clear "Allowed Senders" field in admin panel
   
4. **No Message ID** - Email doesn't have a UID
   - Rare, but logged if it happens

## 🧪 How to Debug Your 5 Emails

### Step 1: Check What's Actually Stored
```bash
php artisan tinker
```
Then:
```php
\App\Models\ProcessedEmail::count(); // Total stored emails
\App\Models\ProcessedEmail::latest()->take(5)->get(['subject', 'from_email', 'email_date', 'created_at']); // Last 5
```

### Step 2: Fetch ALL Emails (Force)
```bash
php artisan payment:monitor-emails --all
```
This will fetch ALL emails regardless of date and show detailed logging.

### Step 3: Check Logs
```bash
tail -f storage/logs/laravel.log
```
Then run the command and watch the logs in real-time.

### Step 4: Check Filter Settings
```bash
php check_email_filters.php
```
This shows if "Allowed Senders" filter is blocking emails.

## 📊 What the Logs Show

When you run the command, you'll see:

```
📧 Fetching emails from folder: INBOX
📅 Fetching emails since: 2025-01-20 10:00:00 (2 hours ago)
✅ Found 5 email(s) in notify@check-outpay.com

📧 Starting to process and store emails...
  ✅ Processing email #0: Test Email (From: sender@example.com)
  ✅ Stored email #0 in database (ID: 123)
  ⏭️  Email #1 already stored: Old Email
  ❌ Email #2 skipped: Sender not allowed - From: spam@example.com, Subject: Spam
  ✅ Processing email #3: Payment Notification (From: alerts@gtbank.com)
  ✅ Stored email #3 in database (ID: 124)

═══════════════════════════════════════════
📊 Email Fetching Summary for notify@check-outpay.com
═══════════════════════════════════════════
📧 Total found: 5
✅ Already stored: 1
💾 Newly stored: 2
💰 Processed for matching: 1 (with payment info)
⏭️  Skipped: 2
📅 Fetching since: 2025-01-20 10:00:00
═══════════════════════════════════════════
```

## 🎯 Quick Fix for Your 5 Emails

**Try this first:**
```bash
php artisan payment:monitor-emails --all
```

This will:
- Fetch ALL emails from the server
- Show detailed logging for each email
- Tell you exactly why each email was or wasn't stored
- Store any emails that aren't already in the database

## 📝 Check Laravel Logs

All detailed information is also logged to:
```
storage/logs/laravel.log
```

Look for entries with:
- `IMAP Email Fetching Started`
- `IMAP Emails Found`
- `Processing Email #`
- `Email skipped:`
- `Email stored successfully`
- `IMAP Email Fetching Summary`

---

**Run `php artisan payment:monitor-emails --all` to fetch your 5 emails with full logging!**
