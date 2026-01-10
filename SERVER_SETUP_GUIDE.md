# Automated Server Setup Guide

## Option 1: Manual Setup (You Run Commands)

### Step 1: SSH to Your Server

```bash
ssh checzspw@premium340.web-hosting.com
# or
ssh checzspw@premium340
```

### Step 2: Navigate to Laravel Root

```bash
cd ~/public_html
# or wherever your Laravel app is
```

### Step 3: Upload Files

You can upload files via:
- FTP/SFTP client (FileZilla, Cyberduck, etc.)
- cPanel File Manager
- SCP (from your local machine)

Upload these files:
- `python-extractor/extract_simple.py` → `~/public_html/python-extractor/extract_simple.py`
- `test_python_extraction.php` → `~/public_html/test_python_extraction.php`
- `setup_python_on_server.sh` → `~/public_html/setup_python_on_server.sh`

### Step 4: Run Setup Script

```bash
cd ~/public_html
bash setup_python_on_server.sh
```

This will:
- Check Python installation
- Create directories
- Test the Python script
- Configure .env file
- Clear Laravel cache
- Run tests

## Option 2: Automated Upload and Setup

### From Your Local Machine

1. Make scripts executable:
```bash
chmod +x upload_and_setup.sh
chmod +x setup_python_on_server.sh
```

2. Run upload script:
```bash
./upload_and_setup.sh
```

This will:
- Generate SSH key if needed
- Show you the public key to add to server
- Upload all necessary files
- Run setup on server automatically

## Step-by-Step Manual Commands

If you prefer to run commands manually:

```bash
# 1. SSH to server
ssh checzspw@premium340.web-hosting.com

# 2. Navigate to Laravel root
cd ~/public_html

# 3. Create Python directory
mkdir -p python-extractor

# 4. Upload extract_simple.py (via FTP/SFTP or create manually)
# Then make executable:
chmod +x python-extractor/extract_simple.py

# 5. Test Python script
echo '{"text_body":"NGN 1000.00","html_body":"<table><tr><td>Amount</td><td>NGN 1,000.00</td></tr></table>","from_email":"test@bank.com"}' | python3 python-extractor/extract_simple.py

# 6. Edit .env file
nano .env
# Add configuration (see CONFIGURE_PYTHON_IN_LARAVEL.md)

# 7. Clear cache
php artisan config:clear
php artisan cache:clear

# 8. Test
php test_python_extraction.php
```

## SSH Key Setup (One-Time)

If you want passwordless SSH:

### On Your Local Machine:

```bash
# Generate SSH key (if not exists)
ssh-keygen -t rsa -b 2048

# Display public key
cat ~/.ssh/id_rsa.pub
```

### On Your Server:

```bash
# Create .ssh directory
mkdir -p ~/.ssh
chmod 700 ~/.ssh

# Add public key (paste the output from local machine)
nano ~/.ssh/authorized_keys
# Paste your public key, save (Ctrl+X, Y, Enter)

# Set permissions
chmod 600 ~/.ssh/authorized_keys
```

### Test Passwordless SSH:

From local machine:
```bash
ssh checzspw@premium340.web-hosting.com
```

Should connect without password.

## Quick Setup Script (All-in-One)

Create this file on your server as `quick_setup.sh`:

```bash
#!/bin/bash
cd ~/public_html

# Create directory
mkdir -p python-extractor

# Download/extract extract_simple.py (you need to upload this first)
# Or create it manually

# Make executable
chmod +x python-extractor/extract_simple.py

# Get absolute path
SCRIPT_PATH=$(readlink -f python-extractor/extract_simple.py || pwd)/python-extractor/extract_simple.py

# Add to .env
cat >> .env << EOF

# Python Extraction Service
PYTHON_EXTRACTOR_ENABLED=true
PYTHON_EXTRACTOR_MODE=script
PYTHON_EXTRACTOR_SCRIPT_PATH=$SCRIPT_PATH
PYTHON_EXTRACTOR_COMMAND=python3
PYTHON_EXTRACTOR_MIN_CONFIDENCE=0.7
PYTHON_EXTRACTOR_TIMEOUT=10
EOF

# Clear cache
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

echo "Setup complete!"
echo "Test with: php test_python_extraction.php"
```

## Troubleshooting

### Can't connect via SSH

- Check if SSH is enabled on your hosting
- Verify username and hostname
- Try using password authentication first

### Files won't upload

- Use FTP/SFTP client instead
- Check file permissions
- Use cPanel File Manager

### Script doesn't execute

- Check permissions: `chmod +x filename.sh`
- Check shebang: `#!/bin/bash` at top of file
- Run with: `bash filename.sh` instead of `./filename.sh`

### Python script not found

- Verify path in .env matches actual location
- Use absolute path: `pwd` then add to script path
- Check file exists: `ls -la ~/public_html/python-extractor/extract_simple.py`

## Recommended Approach for Shared Hosting

**Easiest Method:**

1. Upload files via cPanel File Manager
2. SSH to server and run: `bash setup_python_on_server.sh`
3. Test: `php test_python_extraction.php`

This avoids SSH key setup if you're not comfortable with it.
