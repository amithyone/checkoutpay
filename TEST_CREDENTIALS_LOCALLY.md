# Test Gmail Credentials Locally

## 🎯 Quick Test Script

I've created a standalone test script: `test_gmail_connection.php`

### Option 1: Run on Your Local Computer

1. **Download the script:**
   - The file `test_gmail_connection.php` is in your project
   - Copy it to your local computer

2. **Make sure PHP is installed:**
   ```bash
   php -v
   ```

3. **Run the test:**
   ```bash
   php test_gmail_connection.php
   ```

### Option 2: Upload to Server and Run via Browser

1. **Upload `test_gmail_connection.php` to your server:**
   ```bash
   # On server
   cd /home/checzspw/public_html
   # Upload the file via FTP/cPanel File Manager
   ```

2. **Access via browser:**
   ```
   http://check-outpay.com/test_gmail_connection.php
   ```

3. **Delete after testing** (security):
   ```bash
   rm test_gmail_connection.php
   ```

### Option 3: Test with Email Client

Use an email client like **Thunderbird** or **Outlook** to test:

**Thunderbird Setup:**
1. Open Thunderbird
2. Add new email account
3. Enter: `fastifysales@gmail.com`
4. Choose "IMAP"
5. Enter settings:
   - Incoming: `imap.gmail.com`, Port `993`, SSL
   - Password: Your App Password (`juqdqfdy mqks txgu`)
6. If it connects, your credentials are correct!

**Outlook Setup:**
1. Add Account → Manual Setup
2. Choose IMAP
3. Server: `imap.gmail.com`
4. Port: `993`
5. Encryption: SSL
6. Username: `fastifysales@gmail.com`
7. Password: App Password

### Option 4: Test via Command Line (if available)

```bash
# Test IMAP connection
php -r "
\$conn = @imap_open('{imap.gmail.com:993/ssl/novalidate-cert}INBOX', 'fastifysales@gmail.com', 'juqdqfdy mqks txgu');
if (\$conn) {
    echo '✅ Connection successful! Credentials are correct.\n';
    imap_close(\$conn);
} else {
    echo '❌ Failed: ' . imap_last_error() . '\n';
}
"
```

## ✅ What to Look For

**If credentials are correct:**
- ✅ "CONNECTION SUCCESSFUL!"
- ✅ Shows mailbox information
- ✅ Can read messages

**If credentials are wrong:**
- ❌ "Authentication failed"
- ❌ "Login failed"
- ❌ "Invalid credentials"

## 🔍 Interpreting Results

### Success = Credentials Correct
If the test script shows "CONNECTION SUCCESSFUL", your credentials are 100% correct. The server firewall issue is separate.

### Failure = Check Credentials
If authentication fails:
1. Verify App Password is correct
2. Make sure IMAP is enabled in Gmail
3. Regenerate App Password if needed

## 🎯 Quick Test Command

Run this on your local machine (if PHP is installed):

```bash
php test_gmail_connection.php
```

Or upload to server and access via browser to test from the server itself.

---

**This will tell you if your credentials are correct BEFORE dealing with firewall issues!** ✅
