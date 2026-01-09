<?php
/**
 * Check Email Filters
 * Shows what filters are active that might prevent emails from being fetched
 * Usage: php check_email_filters.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\EmailAccount;
use App\Models\WhitelistedEmailAddress;

echo "========================================\n";
echo "Email Filter Check\n";
echo "========================================\n\n";

// Find email account
$emailAccount = EmailAccount::where('email', 'notify@check-outpay.com')->first();

if (!$emailAccount) {
    echo "❌ Email account not found!\n";
    exit(1);
}

echo "Email Account: {$emailAccount->email}\n";
echo "Account ID: {$emailAccount->id}\n";
echo "Active: " . ($emailAccount->is_active ? 'Yes ✅' : 'No ❌') . "\n\n";

// Check Allowed Senders Filter
echo "1. Allowed Senders Filter\n";
echo "----------------------------------------\n";

if (empty($emailAccount->allowed_senders) || !is_array($emailAccount->allowed_senders) || count($emailAccount->allowed_senders) === 0) {
    echo "✅ NO FILTER - All emails will be processed\n";
    echo "   The 'Allowed Senders' field is empty, so emails from ANY sender will be fetched.\n\n";
} else {
    echo "⚠️  FILTER ACTIVE - Only emails from these senders will be processed:\n";
    foreach ($emailAccount->allowed_senders as $sender) {
        echo "   - {$sender}\n";
    }
    echo "\n";
    echo "❌ Emails from OTHER senders will be SKIPPED!\n";
    echo "   If you sent an email from a sender NOT in this list, it will NOT be fetched.\n\n";
    echo "To allow ALL emails:\n";
    echo "   1. Go to Admin Panel → Email Accounts\n";
    echo "   2. Edit {$emailAccount->email}\n";
    echo "   3. Clear the 'Allowed Senders' field (make it empty)\n";
    echo "   4. Save\n\n";
}

// Test what senders would be allowed
echo "2. Filter Test Examples\n";
echo "----------------------------------------\n";

$testSenders = [
    'alerts@gtbank.com',
    'notifications@check-outpay.com',
    'test@example.com',
    'noreply@gtbank.com',
];

foreach ($testSenders as $testSender) {
    $allowed = $emailAccount->isSenderAllowed($testSender);
    echo ($allowed ? '✅' : '❌') . " {$testSender}: " . ($allowed ? 'ALLOWED' : 'BLOCKED') . "\n";
}

echo "\n";

// Check Whitelisted Emails (only for Zapier webhook, not IMAP)
echo "3. Whitelisted Emails (Zapier Webhook Only)\n";
echo "----------------------------------------\n";

$whitelisted = WhitelistedEmailAddress::where('is_active', true)->get();

if ($whitelisted->isEmpty()) {
    echo "✅ NO FILTER - All emails accepted via Zapier webhook\n";
    echo "   (This only affects Zapier webhook, NOT IMAP email fetching)\n\n";
} else {
    echo "⚠️  WHITELIST ACTIVE - Only these emails accepted via Zapier webhook:\n";
    foreach ($whitelisted as $entry) {
        echo "   - {$entry->email}";
        if ($entry->description) {
            echo " ({$entry->description})";
        }
        echo "\n";
    }
    echo "   Note: This only affects Zapier webhook, NOT IMAP email fetching.\n\n";
}

// Check hardcoded filters
echo "4. Hardcoded Filters\n";
echo "----------------------------------------\n";
echo "The following emails are ALWAYS skipped:\n";
echo "   ❌ noreply@xtrapay.ng (hardcoded filter)\n";
echo "\n";

// Summary
echo "========================================\n";
echo "Summary\n";
echo "========================================\n\n";

if (empty($emailAccount->allowed_senders) || !is_array($emailAccount->allowed_senders) || count($emailAccount->allowed_senders) === 0) {
    echo "✅ Email fetching is NOT restricted by filters.\n";
    echo "   All emails should be fetched (except noreply@xtrapay.ng).\n\n";
    
    if (!$emailAccount->is_active) {
        echo "⚠️  WARNING: Email account is marked as INACTIVE!\n";
        echo "   Go to Admin Panel → Email Accounts and activate it.\n\n";
    }
} else {
    echo "❌ Email fetching IS restricted!\n";
    echo "   Only emails from allowed senders will be fetched.\n";
    echo "   If your test email sender is not in the list above, it won't be fetched.\n\n";
    
    echo "To fix:\n";
    echo "   1. Go to Admin Panel → Email Accounts\n";
    echo "   2. Edit {$emailAccount->email}\n";
    echo "   3. Clear the 'Allowed Senders' field\n";
    echo "   4. Save\n\n";
}

echo "Done!\n";
