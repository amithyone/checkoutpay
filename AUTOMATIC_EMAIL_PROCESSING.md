# Automatic Email Processing - Real-Time Processing

## ✅ What I've Set Up

I've configured your system to automatically process emails in real-time when they arrive!

## 🚀 How It Works Now

### 1. **Automatic Scheduled Checks**

Your system now checks for emails **very frequently**:

- **Every 10 seconds:** Checks IMAP emails
- **Every 15 seconds:** Reads emails directly from filesystem
- **Immediate:** When emails are read, they automatically trigger payment matching

### 2. **Automatic Processing Flow**

```
New Email Arrives
    ↓
Filesystem Read (every 15 sec) OR IMAP Check (every 10 sec)
    ↓
Email Stored in Database
    ↓
Extract Payment Info (amount, sender, etc.)
    ↓
✅ IF amount > 0 → Dispatch ProcessEmailPayment Job
    ↓
Automatic Payment Matching
    ↓
If Match Found → Payment Approved ✅
```

### 3. **When Payment is Created**

```
Payment Created
    ↓
Check Existing Emails (immediate)
    ↓
Schedule CheckPaymentEmails Job (1 minute delay)
    ↓
After 1 Minute → Check for Matching Emails
    ↓
If No Match → Trigger Filesystem Email Read
    ↓
Try to Match → Approve if Found ✅
```

## 📋 Current Configuration

**Scheduler (app/Console/Kernel.php):**
- ✅ `payment:monitor-emails` - Every 10 seconds (IMAP)
- ✅ `payment:read-emails-direct --all` - Every 15 seconds (Filesystem)
- ✅ Both run automatically via cron

## 🔧 How to Run Watcher (Continuous Monitoring)

For **real-time** processing, you can run a continuous watcher that monitors the mail directory:

```bash
# Run watcher that checks every 10 seconds
php artisan payment:watch-emails --interval=10

# Run once (for testing)
php artisan payment:watch-emails --once
```

**In Production (Background Daemon):**

You can run the watcher as a background process:

```bash
# Run in background
nohup php artisan payment:watch-emails --interval=10 > /dev/null 2>&1 &

# Or use supervisor/systemd for better process management
```

## ⚡ Make It Even Faster

### Option 1: Run Watcher as Daemon (Best for Real-Time)

```bash
# On your server, create a daemon that continuously watches
php artisan payment:watch-emails --interval=5

# This will:
# - Monitor mail directories every 5 seconds
# - Detect new email files immediately
# - Process emails as soon as they arrive
```

### Option 2: Increase Scheduler Frequency

Edit `app/Console/Kernel.php` to check more frequently:

```php
// Check every 5 seconds (maximum frequency)
$schedule->command('payment:monitor-emails')
    ->cron('*/5 * * * * *') // Every 5 seconds
    ->withoutOverlapping()
    ->runInBackground();
```

**Note:** Laravel scheduler minimum is 1 minute via cron, but you can use `->everyFiveSeconds()` for internal checks.

### Option 3: Use Queue Workers for Instant Processing

Make sure queue workers are running to process jobs immediately:

```bash
# Start queue worker
php artisan queue:work --verbose

# Or run in background
nohup php artisan queue:work > /dev/null 2>&1 &
```

## 🎯 Recommended Setup

### For Maximum Speed:

1. **Run Watcher Daemon:**
   ```bash
   php artisan payment:watch-emails --interval=5
   ```

2. **Keep Queue Worker Running:**
   ```bash
   php artisan queue:work --verbose
   ```

3. **Keep Scheduler Running:**
   ```bash
   # Add to crontab (runs every minute, Laravel handles sub-minute scheduling)
   * * * * * cd /home/checzspw/public_html && php artisan schedule:run >> /dev/null 2>&1
   ```

## 📊 What Happens Now

**When a new email arrives:**

1. ✅ **Within 5-15 seconds:** Watcher or scheduler detects new email
2. ✅ **Immediately:** Email is read from filesystem
3. ✅ **Automatically:** Payment info is extracted
4. ✅ **Automatically:** ProcessEmailPayment job is dispatched
5. ✅ **Automatically:** Payment matching happens
6. ✅ **If matched:** Payment is approved instantly

**When a payment is created:**

1. ✅ **Immediately:** Checks existing emails for match
2. ✅ **After 1 minute:** CheckPaymentEmails job runs
3. ✅ **If no match:** Triggers filesystem email read
4. ✅ **Automatically:** Tries to match new emails

## 🔍 Monitor It

Watch the logs in real-time:

```bash
# Watch logs for email processing
tail -f storage/logs/laravel.log | grep -E "email|payment|match"

# Or watch all logs
tail -f storage/logs/laravel.log
```

## 🚨 Troubleshooting

**If emails aren't processing automatically:**

1. **Check if scheduler is running:**
   ```bash
   php artisan schedule:list
   php artisan schedule:run
   ```

2. **Check if queue worker is running:**
   ```bash
   ps aux | grep "queue:work"
   # If not running, start it:
   php artisan queue:work
   ```

3. **Check mail directory is accessible:**
   ```bash
   php artisan payment:read-emails-direct --all
   ```

4. **Check logs:**
   ```bash
   tail -100 storage/logs/laravel.log
   ```

## ✅ Summary

Your system now:
- ✅ Checks emails every 10-15 seconds automatically
- ✅ Processes emails immediately when detected
- ✅ Automatically matches payments when emails arrive
- ✅ Automatically checks for emails 1 minute after payment creation
- ✅ Can run continuous watcher for instant detection

**Everything is automatic now!** 🎉
