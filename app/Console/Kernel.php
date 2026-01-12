<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Check if IMAP fetching is disabled
        $disableImap = \App\Models\Setting::get('disable_imap_fetching', false);

        // Monitor emails via IMAP (only if not disabled)
        if (!$disableImap) {
            $schedule->command('payment:monitor-emails')
                ->everyTenSeconds()
                ->withoutOverlapping()
                ->runInBackground();
        }

        // Master email processing cron (3 sequential steps):
        // STEP 1: Fetch emails from filesystem (no matching)
        $schedule->command('payment:read-emails-direct --all --no-match')
            ->everyFifteenSeconds()
            ->withoutOverlapping()
            ->runInBackground()
            ->name('step1-fetch-emails');
        
        // STEP 2: Fill sender_name from text_body if null
        $schedule->command('payment:re-extract-text-body --missing-only')
            ->everyFifteenSeconds()
            ->withoutOverlapping()
            ->runInBackground()
            ->name('step2-fill-sender-names');
        
        // STEP 3: Match transactions (global match)
        $schedule->call(function () {
            try {
                $matchingService = new \App\Services\PaymentMatchingService(
                    new \App\Services\TransactionLogService()
                );

                // Get all unmatched pending payments (not expired)
                $pendingPayments = \App\Models\Payment::where('status', \App\Models\Payment::STATUS_PENDING)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    })
                    ->whereNotExists(function ($query) {
                        $query->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('processed_emails')
                            ->whereColumn('processed_emails.matched_payment_id', 'payments.id')
                            ->where('processed_emails.is_matched', true);
                    })
                    ->with('business')
                    ->get();

                // Get all unmatched processed emails
                $unmatchedEmails = \App\Models\ProcessedEmail::where('is_matched', false)
                    ->latest()
                    ->get();

                // Extract missing sender_name and description_field from text_body
                foreach ($unmatchedEmails as $processedEmail) {
                    if (!$processedEmail->sender_name || !$processedEmail->description_field) {
                        try {
                            $matchingService->extractMissingFromTextBody($processedEmail);
                            $processedEmail->refresh();
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to extract from text_body in scheduler', [
                                'email_id' => $processedEmail->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                // Parse description fields
                foreach ($unmatchedEmails as $processedEmail) {
                    $processedEmail->refresh();
                    if ($processedEmail->description_field && !$processedEmail->account_number) {
                        try {
                            $descExtractor = new \App\Services\DescriptionFieldExtractor();
                            $parsedData = $descExtractor->parseDescriptionField($processedEmail->description_field);
                            if ($parsedData['account_number']) {
                                $currentExtractedData = $processedEmail->extracted_data ?? [];
                                $currentExtractedData['description_field'] = $processedEmail->description_field;
                                $currentExtractedData['account_number'] = $parsedData['account_number'];
                                $currentExtractedData['payer_account_number'] = $parsedData['payer_account_number'];
                                $currentExtractedData['date_from_description'] = $parsedData['extracted_date'];
                                
                                $processedEmail->update([
                                    'account_number' => $parsedData['account_number'],
                                    'extracted_data' => $currentExtractedData,
                                ]);
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to parse description field in scheduler', [
                                'email_id' => $processedEmail->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                // Match emails to payments
                foreach ($unmatchedEmails as $processedEmail) {
                    try {
                        $processedEmail->refresh();
                        if ($processedEmail->is_matched) {
                            continue;
                        }

                        $emailData = [
                            'subject' => $processedEmail->subject,
                            'from' => $processedEmail->from_email,
                            'text' => $processedEmail->text_body ?? '',
                            'html' => $processedEmail->html_body ?? '',
                            'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : null,
                            'email_account_id' => $processedEmail->email_account_id,
                            'processed_email_id' => $processedEmail->id,
                        ];

                        $matchedPayment = $matchingService->matchEmail($emailData);

                        if ($matchedPayment) {
                            \App\Jobs\ProcessEmailPayment::dispatchSync($emailData);
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Error matching email in scheduler', [
                            'email_id' => $processedEmail->id ?? 'unknown',
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Also check pending payments
                foreach ($pendingPayments as $payment) {
                    try {
                        $payment->refresh();
                        
                        if ($payment->status !== \App\Models\Payment::STATUS_PENDING || $payment->isExpired()) {
                            continue;
                        }

                        $matchingService->matchPaymentToStoredEmail($payment);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('Error matching payment in scheduler', [
                            'transaction_id' => $payment->transaction_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Step 3 matching failed in scheduler', [
                    'error' => $e->getMessage(),
                ]);
            }
        })
            ->everyFifteenSeconds()
            ->withoutOverlapping()
            ->runInBackground()
            ->name('step3-match-transactions');

        // Extract missing names from processed emails
        $schedule->command('payment:extract-missing-names --limit=50')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->name('extract-missing-names');

        // Expire old payments every hour
        $schedule->command('payment:expire')
            ->hourly()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
