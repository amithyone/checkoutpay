<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use App\Services\EmailExtractionService;
use Illuminate\Console\Command;

class ReExtractAllEmails extends Command
{
    protected $signature = 'emails:re-extract-all {--limit= : Limit number of emails to process}';
    protected $description = 'Re-extract all emails from text_body';

    public function handle()
    {
        $limit = $this->option('limit');
        
        $query = ProcessedEmail::query();
        
        if ($limit) {
            $query->limit((int) $limit);
        }
        
        $emails = $query->get();
        
        $this->info("Processing {$emails->count()} emails...");
        
        $extractor = new EmailExtractionService();
        $results = [
            'total' => $emails->count(),
            'amount_extracted' => 0,
            'account_extracted' => 0,
            'name_extracted' => 0,
            'date_extracted' => 0,
            'time_extracted' => 0,
            'updated' => 0,
        ];
        
        $bar = $this->output->createProgressBar($emails->count());
        $bar->start();
        
        foreach ($emails as $email) {
            // Re-extract from text_body
            $extracted = $extractor->extractFromTextBody(
                $email->text_body ?? '',
                $email->subject ?? '',
                $email->from_email ?? '',
                $email->email_date?->toDateTimeString()
            );
            
            if ($extracted) {
                $updateData = [];
                
                if ($extracted['amount']) {
                    $updateData['amount'] = $extracted['amount'];
                    $results['amount_extracted']++;
                }
                
                if ($extracted['account_number']) {
                    $updateData['account_number'] = $extracted['account_number'];
                    $results['account_extracted']++;
                }
                
                if ($extracted['sender_name']) {
                    $updateData['sender_name'] = $extracted['sender_name'];
                    $results['name_extracted']++;
                }
                
                if ($extracted['extracted_date']) {
                    $updateData['extracted_date'] = $extracted['extracted_date'];
                    $results['date_extracted']++;
                }
                
                if ($extracted['transaction_time']) {
                    $updateData['transaction_time'] = $extracted['transaction_time'];
                    $results['time_extracted']++;
                }
                
                if (!empty($updateData)) {
                    $updateData['extracted_data'] = $extracted;
                    $email->update($updateData);
                    $results['updated']++;
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Summary
        $this->info("=== Re-extraction Summary ===");
        $this->line("Total emails processed: {$results['total']}");
        $this->line("Emails updated: {$results['updated']}");
        $this->line("Amount extracted: {$results['amount_extracted']}");
        $this->line("Account number extracted: {$results['account_extracted']}");
        $this->line("Sender name extracted: {$results['name_extracted']}");
        $this->line("Date extracted: {$results['date_extracted']}");
        $this->line("Time extracted: {$results['time_extracted']}");
        
        // Show Moniepoint specific stats
        $moniepointEmails = ProcessedEmail::where('from_email', 'like', '%moniepoint.com%')->count();
        $moniepointWithAmount = ProcessedEmail::where('from_email', 'like', '%moniepoint.com%')
            ->whereNotNull('amount')
            ->count();
        $moniepointWithName = ProcessedEmail::where('from_email', 'like', '%moniepoint.com%')
            ->whereNotNull('sender_name')
            ->count();
        
        $this->newLine();
        $this->info("=== Moniepoint Emails ===");
        $this->line("Total Moniepoint emails: {$moniepointEmails}");
        $this->line("With amount extracted: {$moniepointWithAmount}");
        $this->line("With sender name extracted: {$moniepointWithName}");
    }
}
