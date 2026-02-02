<?php

namespace App\Jobs;

use App\Models\Payment;
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
     * The payment instance.
     *
     * @var Payment
     */
    public $payment;

    /**
     * Create a new job instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Reload payment with relationships
        $this->payment->refresh();
        $this->payment->load(['business.websites', 'website']);

        // Only send webhooks for approved payments
        if (!$this->payment->isApproved()) {
            Log::warning('Skipping webhook for non-approved payment', [
                'payment_id' => $this->payment->id,
                'status' => $this->payment->status,
            ]);
            return;
        }

        $webhookUrls = $this->getWebhookUrls();
        
        if (empty($webhookUrls)) {
            Log::info('No webhook URLs found for payment', [
                'payment_id' => $this->payment->id,
                'business_id' => $this->payment->business_id,
            ]);
            
            // Mark as sent even if no URLs (prevents retry loops)
            $this->payment->update([
                'webhook_status' => 'sent',
                'webhook_sent_at' => now(),
            ]);
            return;
        }

        $successCount = 0;
        $failedUrls = [];
        $sentUrls = [];

        foreach ($webhookUrls as $webhookUrl) {
            try {
                $response = $this->sendWebhook($webhookUrl);
                
                if ($response['success']) {
                    $successCount++;
                    $sentUrls[] = $webhookUrl;
                } else {
                    $failedUrls[] = [
                        'url' => $webhookUrl,
                        'error' => $response['error'],
                    ];
                }
            } catch (\Exception $e) {
                Log::error('Exception sending webhook', [
                    'payment_id' => $this->payment->id,
                    'webhook_url' => $webhookUrl,
                    'error' => $e->getMessage(),
                ]);
                
                $failedUrls[] = [
                    'url' => $webhookUrl,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Update payment webhook status
        $webhookStatus = $successCount > 0 ? 'sent' : 'failed';
        $webhookAttempts = ($this->payment->webhook_attempts ?? 0) + 1;
        
        $updateData = [
            'webhook_status' => $webhookStatus,
            'webhook_sent_at' => now(),
            'webhook_attempts' => $webhookAttempts,
            'webhook_urls_sent' => $sentUrls,
        ];

        if (!empty($failedUrls)) {
            $updateData['webhook_last_error'] = json_encode($failedUrls);
        }

        $this->payment->update($updateData);

        Log::info('Webhook notification processed', [
            'payment_id' => $this->payment->id,
            'transaction_id' => $this->payment->transaction_id,
            'success_count' => $successCount,
            'failed_count' => count($failedUrls),
            'total_urls' => count($webhookUrls),
        ]);
    }

    /**
     * Get all webhook URLs for this payment
     */
    protected function getWebhookUrls(): array
    {
        $urls = [];

        // Get webhook URL from payment (legacy support)
        if ($this->payment->webhook_url) {
            $urls[] = $this->payment->webhook_url;
        }

        // Get webhook URLs from all approved business websites
        if ($this->payment->business) {
            $business = $this->payment->business;
            $business->load('websites');
            
            foreach ($business->websites as $website) {
                if ($website->is_approved && $website->webhook_url) {
                    // Avoid duplicates
                    if (!in_array($website->webhook_url, $urls)) {
                        $urls[] = $website->webhook_url;
                    }
                }
            }
        }

        return array_unique($urls);
    }

    /**
     * Send webhook to a single URL
     */
    protected function sendWebhook(string $webhookUrl): array
    {
        try {
            $payload = $this->buildWebhookPayload();

            $response = Http::timeout(10)
                ->retry(2, 100) // Retry twice with 100ms delay
                ->post($webhookUrl, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => $response->status(),
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "HTTP {$response->status()}: {$response->body()}",
                    'status' => $response->status(),
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build webhook payload
     */
    protected function buildWebhookPayload(): array
    {
        $emailData = $this->payment->email_data ?? [];
        
        return [
            'event' => 'payment.approved',
            'transaction_id' => $this->payment->transaction_id,
            'status' => $this->payment->status,
            'amount' => (float) $this->payment->amount,
            'received_amount' => $this->payment->received_amount ? (float) $this->payment->received_amount : (float) $this->payment->amount,
            'payer_name' => $this->payment->payer_name ?? $emailData['name'] ?? $emailData['sender_name'] ?? null,
            'bank' => $this->payment->bank ?? null,
            'payer_account_number' => $this->payment->payer_account_number ?? null,
            'account_number' => $this->payment->account_number ?? null,
            'is_mismatch' => $this->payment->is_mismatch ?? false,
            'mismatch_reason' => $this->payment->mismatch_reason ?? null,
            'charges' => [
                'percentage' => $this->payment->charge_percentage ?? 0,
                'fixed' => $this->payment->charge_fixed ?? 0,
                'total' => $this->payment->total_charges ?? 0,
                'business_receives' => $this->payment->business_receives ?? $this->payment->amount,
            ],
            'timestamp' => $this->payment->matched_at ? $this->payment->matched_at->toISOString() : now()->toISOString(),
            'email_data' => $emailData, // Include sanitized email data
        ];
    }
}
