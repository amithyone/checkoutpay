# Email Filtering System - Check What's Blocking Emails

## 🔍 There ARE Filters That Can Block Emails!

Your system has **filtering mechanisms** that can prevent emails from being fetched:

### 1. **Allowed Senders Filter** (Main Filter)

Each email account has an **"Allowed Senders"** field. If this is set, **ONLY emails from those senders will be fetched**.

**Location:** Admin Panel → Email Accounts → Edit Account → "Allowed Senders" field

**How it works:**
- If **empty**: All emails are fetched ✅
- If **set**: Only emails from listed senders are fetched ❌ (others are skipped)

**Examples of allowed senders:**
```
alerts@gtbank.com
notifications@accessbank.com
@zenithbank.com
transactions@uba.com
```

### 2. **Hardcoded Filters**

The system automatically skips:
- `noreply@xtrapay.ng` (always skipped)

### 3. **Whitelisted Emails** (Only for Zapier Webhook)

This only affects Zapier webhook, **NOT IMAP email fetching**. So it won't block your emails.

## 🧪 Check Your Filters

I've created a script to check what filters are active. Run this on your server:

```bash
php check_email_filters.php
```

This will show:
- ✅ If "Allowed Senders" filter is active
- ✅ What senders are whitelisted
- ✅ What emails would be allowed/blocked

## 🔧 How to Fix if Emails Are Being Filtered

### Option 1: Allow ALL Emails (Recommended for Testing)

1. Go to **Admin Panel → Email Accounts**
2. Click **Edit** on `notify@check-outpay.com`
3. Find the **"Allowed Senders"** field
4. **Clear it completely** (make it empty)
5. Click **Update**
6. Now ALL emails will be fetched

### Option 2: Add Your Sender to Allowed List

If you want to keep the filter but allow your test email:

1. Go to **Admin Panel → Email Accounts**
2. Click **Edit** on `notify@check-outpay.com`
3. In **"Allowed Senders"** field, add your sender email, one per line:
   ```
   your-email@example.com
   @yourdomain.com
   ```
4. Click **Update**

## 📋 Filtering Logic

The filtering happens in `MonitorEmails.php` at line 184:

```php
// Filter by allowed senders if configured
if ($emailAccount && !$emailAccount->isSenderAllowed($fromEmail)) {
    $skippedCount++;
    continue; // Email is skipped, not fetched
}
```

**This means:**
- If "Allowed Senders" is set and your sender is NOT in the list → Email is **SKIPPED**
- If "Allowed Senders" is empty → All emails are **FETCHED**

## 🎯 Quick Fix

**Most likely issue:** The "Allowed Senders" field has entries, and your test email sender is not in that list.

**Solution:** Clear the "Allowed Senders" field in your email account settings to allow ALL emails.

---

**Next Step:** Run `php check_email_filters.php` on your server to see what filters are active!
