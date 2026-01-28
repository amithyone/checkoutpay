# Queue Worker Setup for cPanel

This guide explains how to set up the Laravel queue worker on cPanel to ensure webhooks are processed automatically.

## Finding Your Application Path

First, find your Laravel application directory:

```bash
# SSH into your server and run:
cd ~/public_html  # or wherever your Laravel app is
pwd  # This shows your current path

# Or find artisan file:
find ~ -name "artisan" -type f 2>/dev/null | head -1
```

Common paths:
- `/home/username/public_html` (cPanel default)
- `/home/username/checkout`
- `/var/www/checkout`
- `/var/www/html`

Replace `username` with your actual cPanel username.

## Method 1: cPanel Cron Job (Recommended)

### Step 1: Access cPanel Cron Jobs

1. Log into your cPanel
2. Navigate to **Advanced** → **Cron Jobs**
3. Click **Add New Cron Job** or **Edit** an existing one

### Step 2: Configure the Cron Job

**IMPORTANT**: Replace `/home/username/public_html` with YOUR actual application path!

**Option A: Run Queue Worker Continuously (Best for Production)**

- **Minute**: `*`
- **Hour**: `*`
- **Day**: `*`
- **Month**: `*`
- **Weekday**: `*`
- **Command**: 
```bash
cd /home/username/public_html && /usr/bin/php artisan queue:work --queue=default --timeout=60 --memory=512 --sleep=3 --max-time=3600 --tries=3 --stop-when-empty=false >> /home/username/public_html/storage/logs/queue-worker.log 2>&1
```

**Option B: Run Queue Worker Script (Auto-restart on crash)**

- **Minute**: `*`
- **Hour**: `*`
- **Day**: `*`
- **Month**: `*`
- **Weekday**: `*`
- **Command**: 
```bash
/bin/bash /home/username/public_html/queue-worker.sh
```

**Option C: Simple - Process Jobs Every Minute**

- **Minute**: `*`
- **Hour**: `*`
- **Day**: `*`
- **Month**: `*`
- **Weekday**: `*`
- **Command**: 
```bash
cd /home/username/public_html && /usr/bin/php artisan queue:work --once --timeout=60
```

### Step 3: Verify Queue Worker is Running

After setting up the cron job, verify it's working:

```bash
# SSH into your server first, then:

# Check if queue worker process is running
ps aux | grep "queue:work"

# Check queue worker logs (replace path with yours)
tail -f /home/username/public_html/storage/logs/queue-worker.log

# Check Laravel logs for webhook activity (replace path with yours)
tail -f /home/username/public_html/storage/logs/laravel.log | grep -i webhook

# Or test manually
cd /home/username/public_html
php artisan queue:work --once
```

## Method 2: SSH Terminal (If you have SSH access)

If you have SSH access, you can run the queue worker in a screen or tmux session:

```bash
# First, navigate to your Laravel application directory
cd ~/public_html  # or your actual path

# Install screen if not available (may need root)
# yum install screen  # or apt-get install screen

# Start a screen session
screen -S queue-worker

# Run the queue worker
php artisan queue:work --queue=default --timeout=60 --memory=512 --sleep=3 --tries=3

# Detach from screen: Press Ctrl+A then D
# Reattach: screen -r queue-worker
```

## Method 3: Using Supervisor (If Available)

If Supervisor is installed on your server, create a supervisor config:

**File: `/etc/supervisor/conf.d/checkout-queue-worker.conf`**

```ini
[program:checkout-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /home/username/public_html/artisan queue:work --queue=default --timeout=60 --memory=512 --sleep=3 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/home/username/public_html/storage/logs/queue-worker.log
stopwaitsecs=3600
```

**Note**: Replace `/home/username/public_html` with your actual application path.

Then run:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start checkout-queue-worker:*
```

## Testing the Queue Worker

After setting up, test that it's working:

```bash
# SSH into your server first, then navigate to your app directory
cd ~/public_html  # or your actual path

# Check queue status
php artisan queue:work --once

# Verify webhook is sent
php artisan webhooks:verify-flow

# Resend pending webhooks
php artisan webhooks:resend-fadded-net
```

## Troubleshooting

### Queue Worker Not Processing Jobs

1. **SSH into your server and navigate to app directory:**
```bash
cd ~/public_html  # or your actual path
```

2. **Check if jobs table exists:**
```bash
php artisan migrate
```

3. **Check for failed jobs:**
```bash
php artisan queue:failed
```

4. **Retry failed jobs:**
```bash
php artisan queue:retry all
```

5. **Clear failed jobs:**
```bash
php artisan queue:flush
```

### Queue Worker Keeps Crashing

1. Check logs (replace path with yours): `~/public_html/storage/logs/queue-worker.log`
2. Check Laravel logs: `~/public_html/storage/logs/laravel.log`
3. Increase memory limit in cron job: `--memory=1024`
4. Increase timeout: `--timeout=120`

### Webhooks Still Showing "Pending"

1. Ensure queue worker is running: `ps aux | grep queue:work`
2. Check if jobs are in queue: Check `jobs` table in database
3. Manually process: `cd ~/public_html && php artisan queue:work --once`
4. Resend webhooks: `cd ~/public_html && php artisan webhooks:resend-fadded-net`

## Recommended Settings

For production, use these settings:

- **Memory**: 512MB (increase if needed)
- **Timeout**: 60 seconds per job
- **Sleep**: 3 seconds (when no jobs available)
- **Max Time**: 3600 seconds (1 hour, then restart)
- **Tries**: 3 attempts before marking as failed

## Monitoring

Monitor the queue worker (replace paths with your actual paths):

```bash
# SSH into your server first
cd ~/public_html  # or your actual path

# Watch queue worker log
tail -f storage/logs/queue-worker.log

# Check Laravel logs for webhook activity
tail -f storage/logs/laravel.log | grep -i "webhook\|SendWebhookNotification"

# Check queue status
php artisan queue:work --once --verbose
```

## Quick Setup for Your Server

Since you're in `public_html` directory, here's the exact command for your cron job:

**cPanel Cron Job Command:**
```bash
cd ~/public_html && /usr/bin/php artisan queue:work --queue=default --timeout=60 --memory=512 --sleep=3 --max-time=3600 --tries=3 --stop-when-empty=false >> ~/public_html/storage/logs/queue-worker.log 2>&1
```

Or if you want to use the script:
```bash
/bin/bash ~/public_html/queue-worker.sh
```

**Note**: The `~/public_html` path will automatically expand to `/home/your_username/public_html` in cron jobs.
