<?php

namespace App\Jobs;

use App\Events\PaymentApproved;
use App\Models\Payment;
use App\Services\PaymentMatchingService;
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
    public function handle(PaymentMatchingService $matchingService): void
    {
        Log::info('Processing email payment', [
            'subject' => $this->emailData['subject'] ?? 'N/A',
            'from' => $this->emailData['from'] ?? 'N/A',
        ]);

        $payment = $matchingService->matchEmail($this->emailData);

        if ($payment) {
            // Approve payment
            $payment->approve($this->emailData);

            // Dispatch event to send webhook
            event(new PaymentApproved($payment));
        }
    }
}
