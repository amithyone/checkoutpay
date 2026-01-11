<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;
use Illuminate\Console\Command;

class ReExtractFromTextBody extends Command
{
    protected $signature = 'payment:re-extract-text-body 
                            {--limit= : Limit number of emails to process}
                            {--force : Force re-extraction even if fields already exist}
                            {--missing-only : Only process emails where sender_name OR description_field is null}';

    protected $description = 'Re-extract sender_name and description_field from text_body for existing emails';

    public function handle(): void
    {
        $this->info('🔄 Re-extracting sender_name and description_field from text_body...');
        
        $matchingService = new PaymentMatchingService();
        
        // Build query
        $query = ProcessedEmail::query();
        
        // Filter: only emails where sender_name OR description_field is null
        if ($this->option('missing-only') || !$this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('sender_name')
                  ->orWhereNull('description_field');
            });
        }
        
        // Limit
        $limit = $this->option('limit');
        if ($limit) {
            $query->limit((int) $limit);
        }
        
        $emailsToProcess = $query->get();
        $totalCount = $emailsToProcess->count();
        
        if ($totalCount === 0) {
            $this->info('✅ No emails found that need re-extraction.');
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
                // Skip if both fields exist and not forcing
                if (!$this->option('force') && $email->sender_name && $email->description_field) {
                    $skippedCount++;
                    $bar->advance();
                    continue;
                }
                
                // Extract missing data from text_body
                $extracted = $matchingService->extractMissingFromTextBody($email);
                
                if ($extracted) {
                    $successCount++;
                } else {
                    $skippedCount++;
                }
                
            } catch (\Exception $e) {
                $failedCount++;
                \Illuminate\Support\Facades\Log::error('Failed to re-extract from text_body', [
                    'email_id' => $email->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Display summary
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Success', $successCount],
                ['❌ Failed', $failedCount],
                ['⏭️ Skipped', $skippedCount],
                ['📧 Total Processed', $totalCount],
            ]
        );
        
        if ($successCount > 0) {
            $this->info("✅ Successfully re-extracted data from text_body for {$successCount} email(s)!");
        }
        
        if ($failedCount > 0) {
            $this->warn("⚠️ Failed to re-extract data for {$failedCount} email(s). Check logs for details.");
        }
    }
}
