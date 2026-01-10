# Cron Job Setup Guide for Email Fetching

## Overview

This guide shows you how to set up automated email fetching using cron jobs. You can use either:
1. **Direct Filesystem Reading** (Recommended for shared hosting) - Reads emails directly from server mail directories
2. **IMAP Email Fetching** - Uses IMAP protocol to fetch emails (requires IMAP to be enabled)

## Cron URLs

### Recommended: Direct Filesystem Reading

**URL:**
```
https://yourdomain.com/cron/read-emails-direct
```

**Method:** GET  
**Authentication:** None required (public endpoint)  
**Recommended Frequency:** Every 5-15 minutes

This method:
- ✅ Works on shared hosting
- ✅ Doesn't require IMAP to be enabled
- ✅ More reliable for direct mail directory access
- ✅ Faster execution

### Alternative: IMAP Email Fetching

**URL:**
```
https://yourdomain.com/cron/monitor-emails
```

**Method:** GET  
**Authentication:** None required (public endpoint)  
**Recommended Frequency:** Every 5-10 minutes

**Note:** This will fail if IMAP fetching is disabled in settings.

## Setup Instructions

### Option 1: Using cPanel Cron Jobs (Recommended for Shared Hosting)

1. **Log in to cPanel**
   - Go to your hosting control panel
   - URL: `https://yourdomain.com:2083` or `https://cpanel.yourdomain.com`

2. **Navigate to Cron Jobs**
   - Find "Cron Jobs" in the main menu
   - Or search for "Cron" in cPanel search

3. **Add New Cron Job**
   - Click "Add New Cron Job" or "Standard"
   - Select frequency: **Every 10 minutes** or **Every 15 minutes**
   - Command type: **URL**
   - Enter URL: `https://yourdomain.com/cron/read-emails-direct`
   - Click "Add New Cron Job"

**Example cPanel Cron Command:**
```
*/10 * * * * curl -s https://yourdomain.com/cron/read-emails-direct > /dev/null 2>&1
```

### Option 2: Using External Cron Services

#### cron-job.org (Free)

1. **Sign up** at https://cron-job.org (free account)
2. **Create New Cron Job:**
   - Title: "Fetch Emails Direct"
   - URL: `https://yourdomain.com/cron/read-emails-direct`
   - Schedule: Every 10 minutes
   - HTTP Method: GET
   - Click "Create Cronjob"

#### EasyCron (Free Tier Available)

1. **Sign up** at https://www.easycron.com
2. **Create New Cron Job:**
   - URL: `https://yourdomain.com/cron/read-emails-direct`
   - Schedule: `*/10 * * * *` (every 10 minutes)
   - HTTP Method: GET
   - Save

#### UptimeRobot (Free Monitoring + Cron)

1. **Sign up** at https://uptimerobot.com
2. **Add New Monitor:**
   - Monitor Type: HTTP(s)
   - URL: `https://yourdomain.com/cron/read-emails-direct`
   - Monitoring Interval: 5 minutes
   - Save

### Option 3: Using Server SSH (If Available)

If you have SSH access to your server, you can set up a cron job directly:

```bash
# SSH into your server
ssh checzspw@premium340.web-hosting.com

# Edit crontab
crontab -e

# Add this line (runs every 10 minutes)
*/10 * * * * curl -s https://yourdomain.com/cron/read-emails-direct > /dev/null 2>&1

# Or use wget instead of curl
*/10 * * * * wget -q -O /dev/null https://yourdomain.com/cron/read-emails-direct

# Save and exit (in vi: press Esc, type :wq, press Enter)
```

**Note:** On shared hosting, you may need to use the full path to curl:
```bash
*/10 * * * * /usr/bin/curl -s https://yourdomain.com/cron/read-emails-direct > /dev/null 2>&1
```

## Testing Cron URLs

### Test Direct Filesystem Reading

```bash
# Using curl
curl https://yourdomain.com/cron/read-emails-direct

# Using wget
wget -O- https://yourdomain.com/cron/read-emails-direct

# Using browser
# Just visit: https://yourdomain.com/cron/read-emails-direct
```

**Expected Response:**
```json
{
    "success": true,
    "message": "Direct filesystem email reading completed",
    "method": "direct_filesystem",
    "timestamp": "2026-01-10 12:00:00",
    "execution_time_seconds": 2.5,
    "output": "..."
}
```

### Test IMAP Email Fetching

```bash
curl https://yourdomain.com/cron/monitor-emails
```

**Expected Response:**
```json
{
    "success": true,
    "message": "Email monitoring (IMAP) completed",
    "method": "imap",
    "timestamp": "2026-01-10 12:00:00",
    ...
}
```

## Frequency Recommendations

### For Real-Time Processing (Fastest)
- **Every 1-2 minutes** - Fastest email detection, but more server load
- Use if you need immediate payment matching

### For Balanced Performance (Recommended)
- **Every 5-10 minutes** - Good balance between speed and server load
- Most use cases

### For Low Traffic (Slower but Efficient)
- **Every 15-30 minutes** - Lower server load, slower detection
- Use if you don't need immediate matching

## Troubleshooting

### Cron URL Returns Error 500

**Possible Causes:**
1. PHP errors in the command
2. Database connection issues
3. Mail directory permissions

**Solution:**
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Test command manually
php artisan payment:read-emails-direct --all
```

### Cron URL Returns 404

**Possible Causes:**
1. Wrong URL
2. `.htaccess` rewrite rules not working
3. Route not registered

**Solution:**
1. Check if URL is correct: `https://yourdomain.com/cron/read-emails-direct`
2. Ensure `.htaccess` in `public/` directory is correct
3. Clear Laravel route cache: `php artisan route:clear`

### Cron Job Runs But No Emails Found

**Possible Causes:**
1. Mail directory path is incorrect
2. No new emails in mail directory
3. Emails already processed

**Solution:**
```bash
# Run diagnostic command
php artisan payment:find-mail-directory notify@check-outpay.com

# Check if emails exist
ls -la /home/username/mail/domain/user/Maildir/cur/
ls -la /home/username/mail/domain/user/Maildir/new/
```

### Cron Job Not Executing

**Possible Causes:**
1. Cron job not properly configured
2. Server timezone mismatch
3. Cron service not running

**Solution:**
1. Verify cron job is active in cPanel/external service
2. Check server timezone matches your cron schedule
3. Add email notification in cron to verify execution:
   ```bash
   */10 * * * * curl -s https://yourdomain.com/cron/read-emails-direct || mail -s "Cron Failed" your@email.com
   ```

## Manual Testing from Admin Panel

You can also manually trigger email fetching from the admin dashboard:

1. **Log in to Admin Panel**
   - URL: `https://yourdomain.com/admin`

2. **Go to Dashboard**
   - Click "Dashboard" in sidebar

3. **Click "Fetch Emails (Direct)" button**
   - This triggers the same command as the cron URL
   - Results will be displayed on the page

## Best Practices

1. **Use Direct Filesystem Reading** for shared hosting (more reliable)
2. **Set appropriate frequency** (5-15 minutes is usually sufficient)
3. **Monitor cron logs** regularly to ensure it's running
4. **Set up email alerts** for cron failures (optional)
5. **Keep Laravel scheduler running** alongside cron (if using both)

## Laravel Scheduler vs Cron URL

### Laravel Scheduler (Already Configured)

The Laravel scheduler runs automatically via cron (configured in `app/Console/Kernel.php`):
- Runs `payment:read-emails-direct` every 15 seconds
- Runs `payment:monitor-emails` every 10 seconds (if IMAP enabled)

**This is already active!** But you can also use external cron URLs for redundancy.

### External Cron URL

Use external cron services for:
- Redundancy (backup if scheduler fails)
- External monitoring
- Manual control
- Better logging/alerting from external service

**You can use both!** They won't conflict with each other.

## Quick Start Checklist

- [ ] Choose method: Direct Filesystem (recommended) or IMAP
- [ ] Copy the appropriate cron URL
- [ ] Set up cron job in cPanel or external service
- [ ] Set frequency: Every 5-15 minutes
- [ ] Test the URL manually in browser
- [ ] Verify cron job is executing
- [ ] Check Laravel logs for any errors
- [ ] Monitor email fetching results in admin panel

## Need Help?

If you encounter issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Test command manually: `php artisan payment:read-emails-direct --all`
3. Run diagnostic: `php artisan payment:find-mail-directory notify@check-outpay.com`
4. Verify mail directory exists and is readable
