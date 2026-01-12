<?php
/**
 * Test Sender Name Extraction
 * Clears all sender_name fields and re-extracts them to test accuracy
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;

echo "=== Sender Name Extraction Test ===\n\n";

// Step 1: Count emails
$totalEmails = ProcessedEmail::count();
$emailsWithSenderName = ProcessedEmail::whereNotNull('sender_name')->count();

echo "📊 Current Status:\n";
echo "  Total processed emails: {$totalEmails}\n";
echo "  Emails with sender_name: {$emailsWithSenderName}\n";
echo "  Emails without sender_name: " . ($totalEmails - $emailsWithSenderName) . "\n\n";

// Step 2: Clear all sender names
echo "🗑️  Clearing all sender_name fields...\n";
$cleared = ProcessedEmail::query()->update(['sender_name' => null]);
echo "  Cleared {$cleared} sender_name fields\n\n";

// Step 3: Re-extract sender names
echo "🔄 Re-extracting sender names...\n";
$matchingService = new PaymentMatchingService();

$emails = ProcessedEmail::all();
$total = $emails->count();
$successCount = 0;
$failedCount = 0;
$skippedCount = 0;

$bar = str_repeat('.', 50);
$progress = 0;

foreach ($emails as $email) {
    try {
        $extracted = $matchingService->extractMissingFromTextBody($email);
        if ($extracted && $email->sender_name) {
            $successCount++;
        } else {
            $skippedCount++;
        }
    } catch (\Exception $e) {
        $failedCount++;
        echo "\n  Error on email ID {$email->id}: " . $e->getMessage() . "\n";
    }
    
    $progress++;
    if ($progress % max(1, floor($total / 50)) == 0) {
        echo ".";
    }
}

echo "\n\n";

// Step 4: Show results
$newCount = ProcessedEmail::whereNotNull('sender_name')->count();
$accuracy = $total > 0 ? round(($newCount / $total) * 100, 2) : 0;

echo "📈 Results:\n";
echo "  ✅ Successfully extracted: {$successCount}\n";
echo "  ⏭️  Skipped (no extraction): {$skippedCount}\n";
echo "  ❌ Failed: {$failedCount}\n";
echo "  📧 Total processed: {$total}\n";
echo "  📊 Emails with sender_name after extraction: {$newCount}\n";
echo "  🎯 Extraction accuracy: {$accuracy}%\n\n";

// Step 5: Show sample extracted names
echo "📝 Sample extracted names (first 10):\n";
$samples = ProcessedEmail::whereNotNull('sender_name')
    ->orderBy('id', 'desc')
    ->limit(10)
    ->get(['id', 'sender_name', 'subject']);

foreach ($samples as $sample) {
    echo "  ID {$sample->id}: " . substr($sample->sender_name, 0, 50) . "\n";
    echo "    Subject: " . substr($sample->subject ?? 'N/A', 0, 60) . "\n";
}

echo "\n✅ Test complete!\n";
