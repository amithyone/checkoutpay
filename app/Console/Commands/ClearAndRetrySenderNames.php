<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;
use Illuminate\Console\Command;

class ClearAndRetrySenderNames extends Command
{
    protected $signature = 'payment:clear-and-retry-sender-names 
                            {--limit= : Limit number of emails to process (optional)}
                            {--report-only : Do not clear or extract; only list emails that have no sender_name}';

    protected $description = 'Clear all sender_name, retry extraction, and report which emails still have no name';

    public function handle(): void
    {
        if ($this->option('report-only')) {
            $this->reportFailures();
            return;
        }

        $this->info('🗑️  Clearing all sender_name fields...');

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
                ['⏭️ No name found (no pattern match)', $skippedCount],
                ['📧 Total Processed', $totalCount],
                ['📊 Emails with sender_name (after)', $newCount],
                ['📈 Extraction Rate', $accuracy . '%'],
            ]
        );
        
        $this->newLine();
        $this->info('✅ Re-extraction completed!');

        $this->reportFailures();
    }

    /**
     * List processed emails that still have no sender_name (so you can see what failed and why).
     */
    protected function reportFailures(): void
    {
        $failures = ProcessedEmail::whereNull('sender_name')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'subject', 'from_email', 'from_name', 'description_field', 'text_body', 'amount', 'email_date']);

        $totalNull = ProcessedEmail::whereNull('sender_name')->count();

        $this->newLine();
        $this->info("📋 Emails with NO sender_name (extraction failed): {$totalNull} total. Showing up to 200:");
        $this->newLine();

        if ($failures->isEmpty()) {
            $this->info('   None – all processed emails have a sender_name.');
            return;
        }

        $rows = [];
        foreach ($failures as $e) {
            $snippet = $e->description_field
                ? mb_substr(str_replace(["\r", "\n"], ' ', $e->description_field), 0, 80) . '...'
                : ($e->text_body ? mb_substr(str_replace(["\r", "\n"], ' ', strip_tags($e->text_body)), 0, 80) . '...' : '-');
            $rows[] = [
                $e->id,
                mb_substr($e->subject ?? '-', 0, 40),
                $e->from_email ?? '-',
                $e->from_name ?? '-',
                $snippet,
            ];
        }

        $this->table(
            ['ID', 'Subject', 'From (email)', 'From (name)', 'Description / text snippet'],
            $rows
        );

        $this->newLine();
        $this->comment('Why "From" is often not used: bank alerts usually come from noreply@bank.com – that is not the payer. We only use from_name when it looks like a real person (no @, not generic).');
    }
}
