# SSH Key Setup Guide

## Your SSH Public Key

Your SSH public key has been provided. Here's how to set it up for passwordless access:

```
ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQCu88qL0BRyKBJBe281VuxPeUy33uxgrrWAKazYwN0qHSjJUiudRFX80Ad+VgjgucBUsr5ZhvCZ2f9fxgpJ4hrjakfdiGeUroqSBEUraoJBsOu9XvoctRLiIM/xcpqGbmTqFEoI0ao0d2MwhtMWEdP4KWp6UoPoxLiVireV8GiosvYqSJs8tU93CyoqJXfJKIgnrHVoSacCecdUFUiuysg+xD0BzYhkFxVxKCqlz7cTwaPELS6lezz4EwxXFw1ZgCxCJ4j5XbR7osokmofR0XXBYZINEPSg0al/Jmgm9kDa71UfeIuBjpXeCUKrvBMHmzpp9P0VYcr/9Ru8JQGHvn4b
```

## Step 1: Add Your SSH Key to Server

### Option A: Using ssh-copy-id (Easiest)

From your local Mac:

```bash
# If you don't have the private key yet, create it first
ssh-keygen -t rsa -b 4096

# Copy your public key to server
ssh-copy-id checzspw@premium340.web-hosting.com

# Or if you have a specific key file
ssh-copy-id -i ~/.ssh/id_rsa.pub checzspw@premium340.web-hosting.com
```

### Option B: Manual Setup (via cPanel or SSH)

**Via cPanel:**
1. Log in to cPanel
2. Go to **Security → SSH Access** or **Security → Manage SSH Keys**
3. Click **"Import Key"** or **"Authorize Key"**
4. Paste your public key above
5. Click **"Authorize"** or **"Import"**

**Via SSH (if you already have password access):**

```bash
# Connect with password first
ssh checzspw@premium340.web-hosting.com

# Once connected, run these commands:
mkdir -p ~/.ssh
chmod 700 ~/.ssh

# Add your public key (paste the key below)
echo "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQCu88qL0BRyKBJBe281VuxPeUy33uxgrrWAKazYwN0qHSjJUiudRFX80Ad+VgjgucBUsr5ZhvCZ2f9fxgpJ4hrjakfdiGeUroqSBEUraoJBsOu9XvoctRLiIM/xcpqGbmTqFEoI0ao0d2MwhtMWEdP4KWp6UoPoxLiVireV8GiosvYqSJs8tU93CyoqJXfJKIgnrHVoSacCecdUFUiuysg+xD0BzYhkFxVxKCqlz7cTwaPELS6lezz4EwxXFw1ZgCxCJ4j5XbR7osokmofR0XXBYZINEPSg0al/Jmgm9kDa71UfeIuBjpXeCUKrvBMHmzpp9P0VYcr/9Ru8JQGHvn4b" >> ~/.ssh/authorized_keys

# Set correct permissions
chmod 600 ~/.ssh/authorized_keys

# Exit
exit
```

## Step 2: Test Passwordless Access

From your local Mac:

```bash
# Try connecting (should not ask for password)
ssh checzspw@premium340.web-hosting.com

# If it still asks for password, check:
# 1. Key was added correctly: cat ~/.ssh/authorized_keys on server
# 2. Permissions are correct: ls -la ~/.ssh on server
# 3. SSH agent is running: ssh-add -l on local
```

## Step 3: Configure SSH Config (Optional but Recommended)

Create/edit `~/.ssh/config` on your local Mac:

```bash
Host premium340
    HostName premium340.web-hosting.com
    User checzspw
    IdentityFile ~/.ssh/id_rsa
    PreferredAuthentications publickey
```

Now you can connect with just:
```bash
ssh premium340
```

## Troubleshooting Direct Email Reading Issue

Based on your report that "direct fetch isn't pulling email", let's diagnose this:

### Step 1: Find Mail Directory

Once you have SSH access, run this diagnostic command:

```bash
# On server
cd ~/public_html
php artisan payment:find-mail-directory notify@check-outpay.com
```

This will search for the actual mail directory paths.

### Step 2: Check Mail Directory Permissions

```bash
# Check if mail directory exists and is readable
ls -la ~/mail/
ls -la ~/mail/*/notify/

# Check permissions (should be readable by your user)
find ~/mail -name "*notify*" -type d
find ~/mail -name "*Maildir*" -type d
```

### Step 3: Test Email Reading Manually

```bash
# Try to read emails directly
cd ~/public_html
php artisan payment:read-emails-direct --email=notify@check-outpay.com --all

# Check for errors
tail -f storage/logs/laravel.log
```

### Step 4: Verify Email Account is Active

```bash
# Check email account in database
php artisan tinker
>>> \App\Models\EmailAccount::where('email', 'notify@check-outpay.com')->first();
>>> exit
```

## Common Issues and Fixes

### Issue: "Could not find mail directory"

**Solution:**
1. Mail directory might be in a different location
2. Domain might be stored differently in cPanel
3. Permissions might prevent access

**Fix:**
```bash
# On server, find actual mail directory
find ~/mail -type d -name "*notify*" 2>/dev/null
find ~/mail -type d -name "*check-outpay*" 2>/dev/null
find ~ -type d -name "Maildir" 2>/dev/null | head -20

# Check if domain is stored as check-outpay.com or checkoutpay.com
ls -la ~/mail/
```

### Issue: "Permission denied" when reading emails

**Solution:**
```bash
# Check permissions
ls -la ~/mail/check-outpay.com/notify/

# Fix permissions if needed (contact hosting support if you can't)
# Or check if files are owned by mail:mail (common on cPanel)
```

### Issue: Emails are in different location

**Solution:**
Some cPanel hosts store emails in:
- `/home/username/mail/domain/user/` (most common)
- `/var/spool/mail/username` (mbox format)
- `/home/username/Maildir/` (some setups)

Use the diagnostic command to find the correct path:
```bash
php artisan payment:find-mail-directory notify@check-outpay.com
```

## Next Steps After SSH Setup

Once SSH is working and we've found the mail directory:

1. **Update mail path if needed** - We can hardcode the correct path if it's different
2. **Test email reading** - Run the diagnostic and read commands
3. **Check scheduler** - Ensure `payment:read-emails-direct` is running
4. **Monitor logs** - Watch for any errors

Let me know what the diagnostic command finds, and we'll fix the path issue!
