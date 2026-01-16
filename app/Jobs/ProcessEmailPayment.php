<?php

namespace App\Jobs;

use App\Events\PaymentApproved;
use App\Models\Payment;
use App\Services\PaymentMatchingService;
use App\Services\TransactionLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEmailPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120; // 2 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $emailData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PaymentMatchingService $matchingService, TransactionLogService $logService): void
    {
        $startTime = microtime(true);
        
        Log::info('Processing email payment', [
            'subject' => $this->emailData['subject'] ?? 'N/A',
            'from' => $this->emailData['from'] ?? 'N/A',
            'attempt' => $this->attempts(),
            'processed_email_id' => $this->emailData['processed_email_id'] ?? null,
        ]);

        try {
            // Log email received
            $logService->logEmailReceived('EMAIL-' . now()->timestamp, $this->emailData);

            $payment = $matchingService->matchEmail($this->emailData);

            if ($payment) {
                // Log payment matched
                $logService->logPaymentMatched($payment, $this->emailData);

                // Get match result details (including name_mismatch) if stored
                $matchResult = $payment->getAttribute('_match_result');
                $nameMismatch = $matchResult['name_mismatch'] ?? false;
                $isMismatch = $matchResult['is_mismatch'] ?? false;
                $receivedAmount = $matchResult['received_amount'] ?? null;
                $mismatchReason = $matchResult['mismatch_reason'] ?? null;
                
                // Add name_mismatch to email_data for webhook payload
                $emailDataWithMismatch = array_merge($this->emailData, [
                    'name_mismatch' => $nameMismatch,
                    'name_similarity_percent' => $matchResult['name_similarity_percent'] ?? null,
                ]);

                // Approve payment with mismatch flags
                $payment->approve($emailDataWithMismatch, $isMismatch, $receivedAmount, $mismatchReason);

                // Log payment approved
                $logService->logPaymentApproved($payment);

                // Update business balance if payment has a business
                if ($payment->business_id) {
                    $payment->business->incrementBalanceWithCharges($payment->amount, $payment, $receivedAmount);
                    $payment->business->refresh(); // Refresh to get updated balance
                    
                    // Send new deposit notification
                    $payment->business->notify(new \App\Notifications\NewDepositNotification($payment));
                    
                    // Check for auto-withdrawal
                    $payment->business->triggerAutoWithdrawal();
                }

                // Dispatch event to send webhook
                event(new PaymentApproved($payment));
                
                $processingTime = round((microtime(true) - $startTime) * 1000, 2);
                Log::info('Email payment processed successfully', [
                    'payment_id' => $payment->id,
                    'processing_time_ms' => $processingTime,
                    'attempt' => $this->attempts(),
                ]);
            } else {
                $processingTime = round((microtime(true) - $startTime) * 1000, 2);
                Log::info('Email payment processed but no match found', [
                    'processing_time_ms' => $processingTime,
                    'attempt' => $this->attempts(),
                ]);
            }
        } catch (\Exception $e) {
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::error('Error processing email payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts(),
                'processing_time_ms' => $processingTime,
                'subject' => $this->emailData['subject'] ?? 'N/A',
            ]);
            
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessEmailPayment job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'attempts' => $this->attempts(),
            'subject' => $this->emailData['subject'] ?? 'N/A',
            'from' => $this->emailData['from'] ?? 'N/A',
            'processed_email_id' => $this->emailData['processed_email_id'] ?? null,
        ]);
        
        // TODO: Store in dead letter queue table for manual review
        // You can create a failed_email_processing table to track these
    }
}
