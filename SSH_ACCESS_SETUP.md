# SSH Access Setup for Shared Hosting

## Overview

SSH (Secure Shell) access allows you to connect directly to your server terminal to run commands. Most shared hosting providers support SSH, but it may need to be enabled through cPanel.

## Step 1: Enable SSH Access (if not already enabled)

### Via cPanel:

1. **Log in to cPanel**
   - Go to your hosting control panel (cPanel)
   - URL is usually: `https://yourdomain.com:2083` or `https://cpanel.yourdomain.com`

2. **Enable SSH Access**
   - Look for **"Terminal"** or **"SSH Access"** in cPanel
   - OR go to **Security → SSH Access** or **Security → Terminal**
   - Enable SSH access (you may need to request it from your host if it's not available)

3. **Verify SSH is enabled**
   - Check if you see a "Terminal" icon in cPanel
   - Some hosts require you to request SSH access first

## Step 2: Check if SSH is Already Available

Based on your previous terminal commands, you might already have SSH access. Try:

```bash
# On your local Mac, try connecting:
ssh checzspw@premium340.web-hosting.com
# or
ssh checzspw@premium340.web-hosting.com -p 22
```

## Step 3: Generate SSH Key Pair (for Passwordless Access)

If SSH is working, you can set up SSH keys for easier access:

### On Your Local Mac:

```bash
# Generate SSH key pair (if you haven't already)
ssh-keygen -t rsa -b 4096 -C "your_email@example.com"

# Press Enter to accept default location (~/.ssh/id_rsa)
# Enter a passphrase (optional, but recommended for security)

# Copy public key to server
ssh-copy-id checzspw@premium340.web-hosting.com

# Or manually copy the key:
cat ~/.ssh/id_rsa.pub
# Copy the output, then on server run:
# mkdir -p ~/.ssh
# echo "YOUR_PUBLIC_KEY_HERE" >> ~/.ssh/authorized_keys
# chmod 700 ~/.ssh
# chmod 600 ~/.ssh/authorized_keys
```

## Step 4: Connect via SSH

```bash
# Basic connection
ssh checzspw@premium340.web-hosting.com

# Or if you need to specify port
ssh checzspw@premium340.web-hosting.com -p 22

# With SSH key
ssh -i ~/.ssh/id_rsa checzspw@premium340.web-hosting.com
```

## Step 5: Common Shared Hosting SSH Limitations

Most shared hosting providers have these limitations:

- ✅ **Available**: Basic commands (ls, cd, cat, grep, etc.)
- ✅ **Available**: PHP commands (php, php artisan, etc.)
- ✅ **Available**: Git commands (git pull, git status, etc.)
- ❌ **NOT Available**: Installing system packages (apt, yum, etc.)
- ❌ **NOT Available**: Running services on arbitrary ports
- ❌ **NOT Available**: Root/sudo access
- ⚠️ **Limited**: Python/pip package installation (may require user install with --user flag)

## Step 6: Alternative - cPanel Terminal (If SSH is not available)

If SSH is not available, you can use cPanel's web-based terminal:

1. Log in to cPanel
2. Find **"Terminal"** or **"SSH Access"** icon
3. Click to open web-based terminal
4. Run commands directly in the browser

## Step 7: Test Connection

Once connected, test basic commands:

```bash
# Check current directory
pwd

# Check PHP version
php --version

# Check if artisan is accessible
cd ~/public_html
php artisan --version

# Check Git status
git status

# Check file permissions
ls -la
```

## Troubleshooting

### SSH Connection Refused:

1. **Check if SSH is enabled in cPanel**
   - Go to cPanel → Security → SSH Access
   - Enable SSH if disabled

2. **Contact Hosting Support**
   - Some hosts require SSH to be enabled by support
   - Request SSH access for your account

3. **Check Firewall**
   - Some hosts block SSH by default
   - May need to whitelist your IP in cPanel

### Permission Denied:

1. **Check username**
   - Username is usually your cPanel username (checzspw)

2. **Reset SSH password in cPanel**
   - Go to cPanel → Security → SSH Access
   - Set or reset SSH password

3. **Check SSH key permissions**
   - On server: `chmod 700 ~/.ssh`
   - On server: `chmod 600 ~/.ssh/authorized_keys`

## Next Steps After SSH Access

Once you have SSH access, you can:

1. **Pull latest code changes**:
   ```bash
   cd ~/public_html
   git pull
   ```

2. **Run migrations**:
   ```bash
   php artisan migrate --force
   ```

3. **Clear caches**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

4. **Run seeders**:
   ```bash
   php artisan db:seed --force
   ```

5. **Test email reading**:
   ```bash
   php artisan payment:read-emails-direct --all
   ```

6. **Check logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Security Notes

- **Always use SSH keys instead of passwords** (more secure)
- **Use strong passphrases** for SSH keys
- **Don't share your SSH credentials**
- **Keep your SSH keys secure** on your local machine
- **Use SFTP instead of FTP** (if available)

## Provider-Specific Instructions

### For cPanel Shared Hosting (Most Common):

1. Log in to cPanel
2. Look for **"Terminal"** in the main menu
3. If not visible, search for **"SSH"** in cPanel search
4. Enable SSH access
5. Note your SSH username (usually your cPanel username)
6. Use port 22 (default) or check with your host

### Contact Your Hosting Provider:

If SSH is not available, contact support and request:
- "Please enable SSH access for my account"
- "I need SSH access for deploying Laravel applications"
- Provide your cPanel username

Most shared hosting providers will enable SSH upon request.
