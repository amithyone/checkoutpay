<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\TransactionLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Payment $payment
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TransactionLogService $logService): void
    {
        // Reload payment with relationships
        $this->payment->load(['business', 'accountNumberDetails', 'website']);

        $webhookUrl = $this->payment->webhook_url;

        if (!$webhookUrl) {
            Log::warning('Payment webhook skipped - no webhook URL', [
                'payment_id' => $this->payment->id,
                'transaction_id' => $this->payment->transaction_id,
            ]);
            return;
        }

        // Build webhook payload
        $payload = $this->buildWebhookPayload();

        try {
            $response = Http::timeout(30)
                ->post($webhookUrl, $payload);

            if ($response->successful()) {
                // Log successful webhook
                $logService->logWebhookSent($this->payment, [
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                ]);

                Log::info('Payment webhook sent successfully', [
                    'payment_id' => $this->payment->id,
                    'transaction_id' => $this->payment->transaction_id,
                    'webhook_url' => $webhookUrl,
                    'status_code' => $response->status(),
                ]);
            } else {
                // Log failed webhook
                $logService->logWebhookFailed($this->payment, "HTTP {$response->status()}: {$response->body()}");

                Log::warning('Payment webhook failed', [
                    'payment_id' => $this->payment->id,
                    'transaction_id' => $this->payment->transaction_id,
                    'webhook_url' => $webhookUrl,
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                ]);

                // Retry if not successful
                throw new \Exception("Webhook returned status {$response->status()}");
            }
        } catch (\Exception $e) {
            $logService->logWebhookFailed($this->payment, $e->getMessage());

            Log::error('Payment webhook error', [
                'payment_id' => $this->payment->id,
                'transaction_id' => $this->payment->transaction_id,
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Build webhook payload
     */
    protected function buildWebhookPayload(): array
    {
        $emailData = $this->payment->email_data ?? [];
        
        $payload = [
            'event' => 'payment.approved',
            'transaction_id' => $this->payment->transaction_id,
            'status' => $this->payment->status,
            'amount' => (float) $this->payment->amount,
            'received_amount' => (float) ($this->payment->received_amount ?? $this->payment->amount),
            'payer_name' => $this->payment->payer_name,
            'bank' => $this->payment->bank,
            'payer_account_number' => $this->payment->payer_account_number,
            'account_number' => $this->payment->account_number,
            'account_details' => $this->payment->accountNumberDetails ? [
                'account_name' => $this->payment->accountNumberDetails->account_name,
                'bank_name' => $this->payment->accountNumberDetails->bank_name,
            ] : null,
            'is_mismatch' => $this->payment->is_mismatch ?? false,
            'mismatch_reason' => $this->payment->mismatch_reason,
            'name_mismatch' => $emailData['name_mismatch'] ?? false,
            'name_similarity_percent' => $emailData['name_similarity_percent'] ?? null,
            'matched_at' => $this->payment->matched_at?->toISOString(),
            'approved_at' => $this->payment->approved_at?->toISOString(),
            'created_at' => $this->payment->created_at->toISOString(),
            'timestamp' => now()->toISOString(),
            'website' => $this->payment->website ? [
                'id' => $this->payment->website->id,
                'url' => $this->payment->website->website_url,
            ] : null,
            'charges' => [
                'percentage' => (float) ($this->payment->charge_percentage ?? 0),
                'fixed' => (float) ($this->payment->charge_fixed ?? 0),
                'total' => (float) ($this->payment->total_charges ?? 0),
                'paid_by_customer' => (bool) ($this->payment->charges_paid_by_customer ?? false),
                'business_receives' => (float) ($this->payment->business_receives ?? $this->payment->amount),
            ],
        ];

        // Include email data if available
        if (!empty($emailData)) {
            $payload['email'] = [
                'subject' => $emailData['subject'] ?? null,
                'from' => $emailData['from'] ?? $emailData['from_email'] ?? null,
                'date' => $emailData['date'] ?? $emailData['transaction_date'] ?? null,
            ];
        }

        return $payload;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Payment webhook job failed permanently', [
            'payment_id' => $this->payment->id,
            'transaction_id' => $this->payment->transaction_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
