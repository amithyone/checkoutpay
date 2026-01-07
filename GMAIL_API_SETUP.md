# Gmail API Setup Guide - Bypass Firewall Issues

## 🎯 Why Gmail API?

- ✅ Uses HTTPS (port 443) - **Never blocked by firewalls**
- ✅ More reliable than IMAP
- ✅ Official Google solution
- ✅ Supports push notifications (future)

## 📋 Prerequisites

1. Google Account (Gmail)
2. Google Cloud Console access
3. Laravel application with Gmail API package installed

## 🚀 Step-by-Step Setup

### Step 1: Create Google Cloud Project

1. Go to: https://console.cloud.google.com
2. Click **"Select a project"** → **"New Project"**
3. Enter project name: `Email Payment Gateway`
4. Click **"Create"**
5. Wait for project creation (30 seconds)

### Step 2: Enable Gmail API

1. In your project, go to: **APIs & Services** → **Library**
2. Search for: **"Gmail API"**
3. Click on **"Gmail API"**
4. Click **"Enable"**
5. Wait for API to enable (10 seconds)

### Step 3: Create OAuth 2.0 Credentials

1. Go to: **APIs & Services** → **Credentials**
2. Click **"+ CREATE CREDENTIALS"** → **"OAuth client ID"**
3. If prompted, configure OAuth consent screen:
   - **User Type:** External (unless you have Google Workspace)
   - **App name:** Email Payment Gateway
   - **User support email:** Your email
   - **Developer contact:** Your email
   - Click **"Save and Continue"**
   - **Scopes:** Click **"Add or Remove Scopes"**
     - Search for: `gmail.readonly`
     - Check: `https://www.googleapis.com/auth/gmail.readonly`
     - Click **"Update"** → **"Save and Continue"**
   - **Test users:** Add your Gmail address
   - Click **"Save and Continue"** → **"Back to Dashboard"**

4. Create OAuth Client ID:
   - **Application type:** Web application
   - **Name:** Payment Gateway Web Client
   - **Authorized redirect URIs:**
     ```
     https://check-outpay.com/admin/email-accounts/{id}/gmail/callback
     ```
     Replace `{id}` with a placeholder - we'll update this after creating the email account.
   - Click **"Create"**
   - **IMPORTANT:** Copy the **Client ID** and **Client Secret**
   - Click **"OK"**

### Step 4: Download Credentials JSON

1. In **Credentials** page, find your OAuth client
2. Click the **download icon** (⬇️) next to your client
3. Save the file as: `gmail-credentials.json`
4. **Upload this file** to your server at:
   ```
   storage/app/gmail-credentials.json
   ```
   Or upload via cPanel File Manager

### Step 5: Create Email Account in Admin Panel

1. Login to admin: `https://check-outpay.com/admin`
2. Go to: **Email Accounts** → **Create New**
3. Fill in:
   - **Name:** Fastify Sales Email
   - **Email:** fastifysales@gmail.com
   - **Method:** Select **"Gmail API"** (important!)
   - **Gmail Credentials Path:** `gmail-credentials.json` (or custom path)
   - **Is Active:** Checked
4. Click **"Create"**
5. **Note the Email Account ID** from the URL (e.g., `/admin/email-accounts/1`)

### Step 6: Update Redirect URI

1. Go back to Google Cloud Console
2. **APIs & Services** → **Credentials**
3. Click on your OAuth client
4. Under **"Authorized redirect URIs"**, add:
   ```
   https://check-outpay.com/admin/email-accounts/1/gmail/callback
   ```
   (Replace `1` with your actual Email Account ID)
5. Click **"Save"**

### Step 7: Authorize Gmail Access

1. In admin panel, go to: **Email Accounts**
2. Find your email account
3. Click **"Authorize Gmail"** button
4. You'll be redirected to Google
5. Sign in with your Gmail account
6. Click **"Allow"** to grant permissions
7. You'll be redirected back to admin panel
8. You should see: **"Gmail API authorized successfully!"**

### Step 8: Test Connection

1. In **Email Accounts**, click **"Test Connection"**
2. You should see: **"✅ Connection successful!"**

## ✅ Verification

Run the email monitoring command:

```bash
php artisan payment:monitor-emails
```

You should see:
```
Monitoring email account: Fastify Sales Email (fastifysales@gmail.com)
Found X new email(s) in fastifysales@gmail.com (Gmail API)
```

## 🔧 Troubleshooting

### Error: "Credentials file not found"

**Solution:** Upload `gmail-credentials.json` to `storage/app/` directory

### Error: "Redirect URI mismatch"

**Solution:** 
1. Check Email Account ID in admin panel
2. Update redirect URI in Google Cloud Console to match exactly:
   ```
   https://check-outpay.com/admin/email-accounts/{ID}/gmail/callback
   ```

### Error: "Access denied"

**Solution:**
1. Make sure you added your email as a test user in OAuth consent screen
2. If app is in "Testing" mode, only test users can authorize
3. To allow all users, publish the app (requires verification for production)

### Error: "Token expired"

**Solution:**
1. Click **"Authorize Gmail"** again
2. Re-authorize the application
3. Token will be refreshed automatically

## 📝 File Structure

After setup, you should have:

```
storage/app/
  ├── gmail-credentials.json          # OAuth credentials (from Google Cloud)
  └── gmail-token-1.json             # Access token (auto-generated)
```

## 🔒 Security Notes

1. **Never commit** `gmail-credentials.json` or `gmail-token-*.json` to Git
2. Keep credentials file secure (chmod 600)
3. Rotate credentials periodically
4. Monitor API usage in Google Cloud Console

## 🎉 Success!

Your Gmail API integration is now set up! Emails will be monitored using HTTPS, bypassing any firewall restrictions.

---

**Need help?** Check logs: `storage/logs/laravel.log`
