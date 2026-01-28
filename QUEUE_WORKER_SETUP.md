# Queue Worker Setup for cPanel

This guide explains how to set up the Laravel queue worker on cPanel to ensure webhooks are processed automatically.

## Method 1: cPanel Cron Job (Recommended)

### Step 1: Access cPanel Cron Jobs

1. Log into your cPanel
2. Navigate to **Advanced** → **Cron Jobs**
3. Click **Add New Cron Job** or **Edit** an existing one

### Step 2: Configure the Cron Job

**Option A: Run Queue Worker Continuously (Best for Production)**

- **Minute**: `*`
- **Hour**: `*`
- **Day**: `*`
- **Month**: `*`
- **Weekday**: `*`
- **Command**: 
```bash
cd /var/www/checkout && /usr/bin/php artisan queue:work --queue=default --timeout=60 --memory=512 --sleep=3 --max-time=3600 --tries=3 --stop-when-empty=false >> /var/www/checkout/storage/logs/queue-worker.log 2>&1
```

**Option B: Run Queue Worker Script (Auto-restart on crash)**

- **Minute**: `*`
- **Hour**: `*`
- **Day**: `*`
- **Month**: `*`
- **Weekday**: `*`
- **Command**: 
```bash
/bin/bash /var/www/checkout/queue-worker.sh
```

**Option C: Process Jobs Every Minute (Simpler, but less efficient)**

- **Minute**: `*`
- **Hour**: `*`
- **Day**: `*`
- **Month**: `*`
- **Weekday**: `*`
- **Command**: 
```bash
cd /var/www/checkout && /usr/bin/php artisan queue:work --once --timeout=60
```

### Step 3: Verify Queue Worker is Running

After setting up the cron job, verify it's working:

```bash
# Check if queue worker process is running
ps aux | grep "queue:work"

# Check queue worker logs
tail -f /var/www/checkout/storage/logs/queue-worker.log

# Check Laravel logs for webhook activity
tail -f /var/www/checkout/storage/logs/laravel.log | grep -i webhook
```

## Method 2: SSH Terminal (If you have SSH access)

If you have SSH access, you can run the queue worker in a screen or tmux session:

```bash
# Install screen if not available
# yum install screen  # or apt-get install screen

# Start a screen session
screen -S queue-worker

# Run the queue worker
cd /var/www/checkout
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
command=/usr/bin/php /var/www/checkout/artisan queue:work --queue=default --timeout=60 --memory=512 --sleep=3 --tries=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/checkout/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Then run:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start checkout-queue-worker:*
```

## Testing the Queue Worker

After setting up, test that it's working:

```bash
# Check queue status
cd /var/www/checkout
php artisan queue:work --once

# Verify webhook is sent
php artisan webhooks:verify-flow

# Resend pending webhooks
php artisan webhooks:resend-fadded-net
```

## Troubleshooting

### Queue Worker Not Processing Jobs

1. **Check if jobs table exists:**
```bash
php artisan migrate
```

2. **Check for failed jobs:**
```bash
php artisan queue:failed
```

3. **Retry failed jobs:**
```bash
php artisan queue:retry all
```

4. **Clear failed jobs:**
```bash
php artisan queue:flush
```

### Queue Worker Keeps Crashing

1. Check logs: `/var/www/checkout/storage/logs/queue-worker.log`
2. Check Laravel logs: `/var/www/checkout/storage/logs/laravel.log`
3. Increase memory limit in cron job: `--memory=1024`
4. Increase timeout: `--timeout=120`

### Webhooks Still Showing "Pending"

1. Ensure queue worker is running: `ps aux | grep queue:work`
2. Check if jobs are in queue: Check `jobs` table in database
3. Manually process: `php artisan queue:work --once`
4. Resend webhooks: `php artisan webhooks:resend-fadded-net`

## Recommended Settings

For production, use these settings:

- **Memory**: 512MB (increase if needed)
- **Timeout**: 60 seconds per job
- **Sleep**: 3 seconds (when no jobs available)
- **Max Time**: 3600 seconds (1 hour, then restart)
- **Tries**: 3 attempts before marking as failed

## Monitoring

Monitor the queue worker:

```bash
# Watch queue worker log
tail -f /var/www/checkout/storage/logs/queue-worker.log

# Check Laravel logs for webhook activity
tail -f /var/www/checkout/storage/logs/laravel.log | grep -i "webhook\|SendWebhookNotification"

# Check queue status
cd /var/www/checkout && php artisan queue:work --once --verbose
```
