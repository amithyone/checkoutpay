<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;
use Illuminate\Console\Command;

class TestSenderExtraction extends Command
{
    protected $signature = 'test:sender-extraction';
    protected $description = 'Clear all sender names and re-extract them to test accuracy';

    public function handle(): void
    {
        $this->info('=== Sender Name Extraction Test ===');
        $this->newLine();
        
        // Get initial stats
        $totalEmails = ProcessedEmail::count();
        $emailsWithSenderName = ProcessedEmail::whereNotNull('sender_name')->count();
        
        $this->info("📊 Current Status:");
        $this->line("  Total processed emails: {$totalEmails}");
        $this->line("  Emails with sender_name: {$emailsWithSenderName}");
        $this->line("  Emails without sender_name: " . ($totalEmails - $emailsWithSenderName));
        $this->newLine();
        
        // Confirm before clearing
        if (!$this->confirm('Clear all sender_name fields and re-extract?', true)) {
            $this->warn('Cancelled.');
            return;
        }
        
        // Clear all sender names
        $this->info("🗑️  Clearing all sender_name fields...");
        $cleared = ProcessedEmail::query()->update(['sender_name' => null]);
        $this->line("  Cleared {$cleared} sender_name fields");
        $this->newLine();
        
        // Re-extract sender names
        $this->info("🔄 Re-extracting sender names...");
        $matchingService = new PaymentMatchingService();
        
        $emails = ProcessedEmail::all();
        $total = $emails->count();
        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        
        foreach ($emails as $email) {
            try {
                $extracted = $matchingService->extractMissingFromTextBody($email);
                $email->refresh();
                if ($extracted && $email->sender_name) {
                    $successCount++;
                } else {
                    $skippedCount++;
                }
            } catch (\Exception $e) {
                $failedCount++;
                \Illuminate\Support\Facades\Log::error('Error extracting sender name', [
                    'email_id' => $email->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Get final stats
        $newCount = ProcessedEmail::whereNotNull('sender_name')->count();
        $accuracy = $totalEmails > 0 ? round(($newCount / $totalEmails) * 100, 2) : 0;
        
        // Display results
        $this->info("📈 Results:");
        $this->table(
            ['Metric', 'Count'],
            [
                ['✅ Successfully extracted', $successCount],
                ['⏭️ Skipped (no extraction)', $skippedCount],
                ['❌ Failed', $failedCount],
                ['📧 Total processed', $total],
                ['📊 Emails with sender_name after', $newCount],
                ['🎯 Extraction accuracy', $accuracy . '%'],
            ]
        );
        
        // Show sample extracted names
        $this->newLine();
        $this->info("📝 Sample extracted names (first 10):");
        $samples = ProcessedEmail::whereNotNull('sender_name')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get(['id', 'sender_name', 'subject']);
        
        $sampleData = [];
        foreach ($samples as $sample) {
            $sampleData[] = [
                'ID' => $sample->id,
                'Sender Name' => substr($sample->sender_name, 0, 40),
                'Subject' => substr($sample->subject ?? 'N/A', 0, 50),
            ];
        }
        
        if (!empty($sampleData)) {
            $this->table(['ID', 'Sender Name', 'Subject'], $sampleData);
        }
        
        $this->newLine();
        $this->info("✅ Test complete!");
    }
}
