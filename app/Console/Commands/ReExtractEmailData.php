<?php

namespace App\Console\Commands;

use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;
use App\Services\TransactionLogService;
use Illuminate\Console\Command;

class ReExtractEmailData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:re-extract {--email-id= : Re-extract specific email by ID} {--all : Re-extract all emails}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-extract payment information from stored emails using improved parsing logic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $matchingService = new PaymentMatchingService(new TransactionLogService());

        if ($this->option('email-id')) {
            // Re-extract specific email
            $email = ProcessedEmail::find($this->option('email-id'));
            if (!$email) {
                $this->error("Email with ID {$this->option('email-id')} not found.");
                return 1;
            }
            $emails = collect([$email]);
        } elseif ($this->option('all')) {
            // Re-extract all emails
            $emails = ProcessedEmail::all();
            $this->info("Re-extracting data from {$emails->count()} emails...");
        } else {
            // Re-extract unmatched emails only
            $emails = ProcessedEmail::where('is_matched', false)->get();
            $this->info("Re-extracting data from {$emails->count()} unmatched emails...");
        }

        $updated = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($emails->count());
        $bar->start();

        foreach ($emails as $email) {
            try {
                // Re-extract payment info from html_body
                $emailData = [
                    'subject' => $email->subject,
                    'from' => $email->from_email,
                    'text' => $email->text_body ?? '',
                    'html' => $email->html_body ?? '',
                    'date' => $email->email_date ? $email->email_date->toDateTimeString() : null,
                ];

                $extractedInfo = $matchingService->extractPaymentInfo($emailData);

                if ($extractedInfo && isset($extractedInfo['amount']) && $extractedInfo['amount'] > 0) {
                    // Update email with new extracted data
                    $email->update([
                        'amount' => $extractedInfo['amount'],
                        'sender_name' => $extractedInfo['sender_name'] ?? null,
                        'account_number' => $extractedInfo['account_number'] ?? null,
                        'extracted_data' => $extractedInfo,
                    ]);
                    $updated++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $this->warn("\nError processing email ID {$email->id}: {$e->getMessage()}");
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Re-extraction complete!");
        $this->info("   Updated: {$updated} emails");
        $this->info("   Skipped: {$skipped} emails (no payment info extracted)");

        return 0;
    }
}
