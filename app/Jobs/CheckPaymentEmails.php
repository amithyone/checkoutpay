<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\ProcessedEmail;
use App\Services\PaymentMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to check for matching emails after payment is created
 * Runs 1 minute after payment creation to allow time for emails to arrive
 */
class CheckPaymentEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Payment $payment
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentMatchingService $matchingService): void
    {
        // Refresh payment to get latest status
        $this->payment->refresh();
        
        // Skip if payment is already matched or expired
        if ($this->payment->status !== Payment::STATUS_PENDING) {
            Log::info('Payment already processed, skipping email check', [
                'transaction_id' => $this->payment->transaction_id,
                'status' => $this->payment->status,
            ]);
            return;
        }

        if ($this->payment->isExpired()) {
            Log::info('Payment expired, skipping email check', [
                'transaction_id' => $this->payment->transaction_id,
            ]);
            return;
        }

        // Load business relationship if exists
        if ($this->payment->business_id) {
            $this->payment->load('business');
        }

        Log::info('Checking for matching emails for payment', [
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $this->payment->amount,
            'payer_name' => $this->payment->payer_name,
            'account_number' => $this->payment->account_number,
        ]);

        // Get unmatched stored emails that could potentially match this payment
        // Check emails from 5 minutes before payment creation to allow for timing differences
        $checkSince = $this->payment->created_at->subMinutes(5);
        
        $query = ProcessedEmail::where('matched_payment_id', null) // Not already matched
            ->where('email_date', '>=', $checkSince)
            ->where(function ($q) {
                // Match by amount (within 1 naira tolerance)
                $q->whereNotNull('amount')
                    ->whereBetween('amount', [
                        $this->payment->amount - 1,
                        $this->payment->amount + 1
                    ]);
                
                // OR match by account number if provided
                if ($this->payment->account_number) {
                    $q->orWhere(function ($accountQuery) {
                        $accountQuery->whereNotNull('account_number')
                            ->where('account_number', $this->payment->account_number);
                    });
                }
            });

        // Filter by email account if business has one assigned
        if ($this->payment->business_id && $this->payment->business->email_account_id) {
            $query->where('email_account_id', $this->payment->business->email_account_id);
        }

        $potentialEmails = $query->orderBy('email_date', 'desc')->get();

        Log::info('Found potential matching emails', [
            'transaction_id' => $this->payment->transaction_id,
            'count' => $potentialEmails->count(),
        ]);

        // Try to match each email using the full matching service
        foreach ($potentialEmails as $processedEmail) {
            try {
                // Rebuild email data for matching
                $emailData = [
                    'subject' => $processedEmail->subject,
                    'from' => $processedEmail->from_email,
                    'text' => $processedEmail->text_body ?? '',
                    'html' => $processedEmail->html_body ?? '',
                    'date' => $processedEmail->email_date ? $processedEmail->email_date->toDateTimeString() : now()->toDateTimeString(),
                    'email_account_id' => $processedEmail->email_account_id,
                    'processed_email_id' => $processedEmail->id, // CRITICAL: Pass ID for logging
                ];

                // Use the matching service to match email to payment
                // This uses all matching logic (amount, account, name, etc.)
                $matchedPayment = $matchingService->matchEmail($emailData);

                if ($matchedPayment && $matchedPayment->id === $this->payment->id) {
                    Log::info('Email matched to payment!', [
                        'transaction_id' => $this->payment->transaction_id,
                        'processed_email_id' => $processedEmail->id,
                    ]);

                    // Process the email payment (this will approve the payment)
                    // Use dispatchSync to process immediately since we're in a job already
                    ProcessEmailPayment::dispatchSync($emailData);
                    
                    // Break after first match (one email per payment)
                    return;
                }
            } catch (\Exception $e) {
                Log::error('Error checking email match', [
                    'transaction_id' => $this->payment->transaction_id,
                    'processed_email_id' => $processedEmail->id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        // If still no match, trigger email fetching from filesystem
        // This ensures we check for new emails that might have arrived
        if ($this->payment->status === Payment::STATUS_PENDING) {
            Log::info('No match found, triggering email fetch', [
                'transaction_id' => $this->payment->transaction_id,
            ]);
            
            // Dispatch command to read emails directly from filesystem
            \Illuminate\Support\Facades\Artisan::call('payment:read-emails-direct', [
                '--email' => $this->payment->business?->emailAccount?->email ?? 'notify@check-outpay.com',
            ]);
        }
    }
}
