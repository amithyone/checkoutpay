# Debug Email Monitoring

## 🔍 Check if Email Monitoring is Working

### 1. Check if Scheduler is Running

```bash
# Check scheduled tasks
php artisan schedule:list

# Run scheduler manually
php artisan schedule:run

# Or run email monitoring directly
php artisan payment:monitor-emails
```

### 2. Check Queue Worker

Email processing uses queues. Make sure queue worker is running:

```bash
# Check if queue worker is running
ps aux | grep "queue:work"

# Start queue worker
php artisan queue:work --verbose

# Or use supervisor/systemd for production
```

### 3. Check Logs

```bash
# View recent logs
tail -f storage/logs/laravel.log | grep -i "email\|payment\|monitor"

# Check for errors
tail -f storage/logs/laravel.log | grep -i "error\|exception"
```

### 4. Test Email Connection

```bash
# Test email account connection
php artisan payment:monitor-emails --verbose

# Or test specific account
php artisan email:test-connection
```

### 5. Check Pending Payments

```bash
php artisan tinker
>>> App\Models\Payment::pending()->count()
>>> App\Models\Payment::pending()->get(['transaction_id', 'amount', 'business_id'])
```

### 6. Check Email Accounts

```bash
php artisan tinker
>>> App\Models\EmailAccount::where('is_active', true)->get(['id', 'email', 'method'])
```

### 7. Manual Test

1. Create a payment request
2. Check logs: `tail -f storage/logs/laravel.log`
3. Run: `php artisan payment:monitor-emails`
4. Check if email is processed

## 🐛 Common Issues

### Issue: No emails being processed

**Check:**
- Are there pending payments?
- Is email account active?
- Is queue worker running?
- Are emails actually in inbox?
- Are emails unread?

### Issue: Emails not matching payments

**Check:**
- Amount matches exactly?
- Payer name matches (if provided)?
- Email format is correct?
- Check logs for matching attempts

### Issue: Scheduler not running

**Solution:**
Add to crontab:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## ✅ Expected Behavior

When monitoring runs:
1. ✅ Connects to email account
2. ✅ Finds pending payments
3. ✅ Checks emails after oldest payment created
4. ✅ Filters by allowed senders (if configured)
5. ✅ Processes matching emails
6. ✅ Approves payments
7. ✅ Sends webhooks

Check logs to see each step!
