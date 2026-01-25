<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;
use Illuminate\Console\Command;

class ClearAndRetrySenderNames extends Command
{
    protected $signature = 'payment:clear-and-retry-sender-names 
                            {--limit= : Limit number of emails to process (optional)}';

    protected $description = 'Clear all sender_name fields and retry extraction with improved patterns';

    public function handle(): void
    {
        $this->info('🗑️  Clearing all sender_name fields...');
        
        // Clear all sender names
        $cleared = ProcessedEmail::query()->update(['sender_name' => null]);
        $this->info("✅ Cleared {$cleared} sender_name fields");
        $this->newLine();
        
        $this->info('🔄 Re-extracting sender names with improved patterns...');
        
        $matchingService = new PaymentMatchingService();
        
        // Build query
        $query = ProcessedEmail::query();
        
        // Limit if specified
        $limit = $this->option('limit');
        if ($limit) {
            $query->limit((int) $limit);
        }
        
        $emailsToProcess = $query->get();
        $totalCount = $emailsToProcess->count();
        
        if ($totalCount === 0) {
            $this->info('✅ No emails found to process.');
            return;
        }
        
        $this->info("Found {$totalCount} email(s) to process");
        
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();
        
        $successCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        
        foreach ($emailsToProcess as $email) {
            try {
                // Extract sender name from text_body
                $extracted = $matchingService->extractMissingFromTextBody($email);
                
                if ($extracted && $email->refresh()->sender_name) {
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
        $accuracy = $totalCount > 0 ? round(($newCount / $totalCount) * 100, 2) : 0;
        
        // Display summary
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Successfully Extracted', $successCount],
                ['❌ Failed', $failedCount],
                ['⏭️ Skipped (No extraction)', $skippedCount],
                ['📧 Total Processed', $totalCount],
                ['📊 Emails with sender_name (after)', $newCount],
                ['📈 Extraction Rate', $accuracy . '%'],
            ]
        );
        
        $this->newLine();
        $this->info('✅ Re-extraction completed!');
    }
}
