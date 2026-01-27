<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use App\Services\EmailExtractionService;
use Illuminate\Console\Command;

class TestMoniepointExtraction extends Command
{
    protected $signature = 'test:moniepoint-extraction {--clear : Clear names and amounts before re-extracting}';
    protected $description = 'Test Moniepoint email extraction by clearing and re-extracting data';

    public function handle()
    {
        $clear = $this->option('clear');
        
        // Find Moniepoint emails
        $emails = ProcessedEmail::where('from_email', 'like', '%moniepoint.com%')
            ->orWhere('text_body', 'like', '%Credit Amount%')
            ->orWhere('text_body', 'like', '%credit transaction occurred%')
            ->get();
        
        $this->info("Found {$emails->count()} Moniepoint emails");
        
        if ($emails->count() === 0) {
            $this->warn('No Moniepoint emails found');
            return;
        }
        
        $extractor = new EmailExtractionService();
        $results = [
            'total' => $emails->count(),
            'amount_extracted' => 0,
            'account_extracted' => 0,
            'name_extracted' => 0,
            'date_extracted' => 0,
        ];
        
        foreach ($emails as $email) {
            $this->line("Processing email ID: {$email->id}");
            $this->line("Subject: {$email->subject}");
            $this->line("From: {$email->from_email}");
            
            // Clear if requested
            if ($clear) {
                $this->info("  Clearing existing data...");
                $email->update([
                    'amount' => null,
                    'sender_name' => null,
                    'account_number' => null,
                    'extracted_data' => null,
                ]);
            }
            
            // Show current data
            $this->line("  Current Amount: " . ($email->amount ?? 'NULL'));
            $this->line("  Current Sender Name: " . ($email->sender_name ?? 'NULL'));
            $this->line("  Current Account Number: " . ($email->account_number ?? 'NULL'));
            
            // Re-extract
            $this->info("  Re-extracting from text_body...");
            $extracted = $extractor->extractFromTextBody(
                $email->text_body ?? '',
                $email->subject ?? '',
                $email->from_email ?? '',
                $email->email_date?->toDateTimeString()
            );
            
            if ($extracted) {
                $this->info("  ✓ Extraction successful!");
                $this->line("    Amount: " . ($extracted['amount'] ?? 'NULL'));
                $this->line("    Sender Name: " . ($extracted['sender_name'] ?? 'NULL'));
                $this->line("    Account Number: " . ($extracted['account_number'] ?? 'NULL'));
                $this->line("    Date: " . ($extracted['extracted_date'] ?? 'NULL'));
                $this->line("    Time: " . ($extracted['transaction_time'] ?? 'NULL'));
                
                if ($extracted['amount']) $results['amount_extracted']++;
                if ($extracted['account_number']) $results['account_extracted']++;
                if ($extracted['sender_name']) $results['name_extracted']++;
                if ($extracted['extracted_date']) $results['date_extracted']++;
                
                // Update email if clear was used
                if ($clear) {
                    $email->update([
                        'amount' => $extracted['amount'],
                        'sender_name' => $extracted['sender_name'],
                        'account_number' => $extracted['account_number'],
                        'extracted_data' => $extracted,
                    ]);
                    $this->info("  ✓ Email updated with extracted data");
                }
            } else {
                $this->error("  ✗ Extraction failed - no data extracted");
            }
            
            $this->newLine();
        }
        
        // Summary
        $this->info("=== Extraction Summary ===");
        $this->line("Total emails: {$results['total']}");
        $this->line("Amount extracted: {$results['amount_extracted']}");
        $this->line("Account number extracted: {$results['account_extracted']}");
        $this->line("Sender name extracted: {$results['name_extracted']}");
        $this->line("Date extracted: {$results['date_extracted']}");
    }
}
