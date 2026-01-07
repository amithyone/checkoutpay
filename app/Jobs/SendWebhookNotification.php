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

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [30, 60, 120];

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
        if (empty($this->payment->webhook_url)) {
            Log::error('No webhook URL provided for payment', [
                'transaction_id' => $this->payment->transaction_id,
            ]);
            return;
        }

        // Determine payload based on payment status - ALWAYS include transaction_id
        if ($this->payment->status === 'approved') {
            $payload = [
                'success' => true,
                'status' => 'approved',
                'transaction_id' => $this->payment->transaction_id, // Always included
                'amount' => (float) $this->payment->amount,
                'payer_name' => $this->payment->payer_name,
                'bank' => $this->payment->bank,
                'approved_at' => $this->payment->matched_at->toISOString(),
                'message' => 'Payment has been verified and approved',
            ];
        } elseif ($this->payment->status === 'rejected') {
            $payload = [
                'success' => false,
                'status' => 'rejected',
                'transaction_id' => $this->payment->transaction_id, // Always included
                'amount' => (float) $this->payment->amount,
                'payer_name' => $this->payment->payer_name,
                'rejected_at' => $this->payment->matched_at?->toISOString() ?? now()->toISOString(),
                'reason' => $this->payment->email_data['rejection_reason'] ?? 'Payment verification failed',
                'message' => 'Payment has been rejected',
            ];
        } else {
            // Fallback for other statuses (expired, etc.)
            $payload = [
                'success' => false,
                'status' => $this->payment->status,
                'transaction_id' => $this->payment->transaction_id, // Always included
                'amount' => (float) $this->payment->amount,
                'message' => 'Payment status updated',
            ];
        }

        Log::info('Sending webhook notification', [
            'transaction_id' => $this->payment->transaction_id,
            'webhook_url' => $this->payment->webhook_url,
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'EmailPaymentGateway/1.0',
                ])
                ->post($this->payment->webhook_url, $payload);

            if ($response->successful()) {
                Log::info('Webhook sent successfully', [
                    'transaction_id' => $this->payment->transaction_id,
                    'status_code' => $response->status(),
                ]);

                // Log webhook sent
                $logService->logWebhookSent($this->payment, [
                    'status_code' => $response->status(),
                    'response' => $response->json(),
                ]);
            } else {
                Log::warning('Webhook sent but received non-success status', [
                    'transaction_id' => $this->payment->transaction_id,
                    'status_code' => $response->status(),
                    'response' => $response->body(),
                ]);

                // Log webhook failure
                $logService->logWebhookFailed($this->payment, "HTTP {$response->status()}: {$response->body()}");

                // Retry on client/server errors
                if ($response->status() >= 500) {
                    $this->release(30);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error sending webhook', [
                'transaction_id' => $this->payment->transaction_id,
                'error' => $e->getMessage(),
            ]);

            // Log webhook failure
            $logService->logWebhookFailed($this->payment, $e->getMessage());

            throw $e; // Will trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook notification failed after retries', [
            'transaction_id' => $this->payment->transaction_id,
            'error' => $exception->getMessage(),
        ]);
    }
}
