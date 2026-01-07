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
        Log::info('Processing email payment', [
            'subject' => $this->emailData['subject'] ?? 'N/A',
            'from' => $this->emailData['from'] ?? 'N/A',
        ]);

        // Log email received
        $logService->logEmailReceived('EMAIL-' . now()->timestamp, $this->emailData);

        $payment = $matchingService->matchEmail($this->emailData);

        if ($payment) {
            // Log payment matched
            $logService->logPaymentMatched($payment, $this->emailData);

            // Approve payment
            $payment->approve($this->emailData);

            // Log payment approved
            $logService->logPaymentApproved($payment);

            // Update business balance if payment has a business
            if ($payment->business_id) {
                $payment->business->increment('balance', $payment->amount);
            }

            // Dispatch event to send webhook
            event(new PaymentApproved($payment));
        }
    }
}
