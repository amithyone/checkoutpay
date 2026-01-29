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

    public $tries = 5; // Increased tries for rate limiting
    public $timeout = 30;
    public $backoff = [60, 300, 900, 1800, 3600]; // Exponential backoff: 1min, 5min, 15min, 30min, 1hr

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
        $this->payment->load(['accountNumberDetails', 'website']);

        // Collect webhook URLs to send to
        // CRITICAL: Only send to the website that originated the transaction, not all business websites
        $webhookUrls = [];

        // PRIORITY 1: If payment has a website, ONLY send to that website's webhook URL
        // This is the ONLY website that should receive the webhook - the one that created the transaction
        if ($this->payment->website && $this->payment->website->webhook_url) {
            $webhookUrls[] = [
                'url' => $this->payment->website->webhook_url,
                'type' => 'website',
                'website_id' => $this->payment->website->id,
            ];
            
            Log::info('Added originating website webhook (ONLY webhook for this payment)', [
                'payment_id' => $this->payment->id,
                'website_id' => $this->payment->website->id,
                'website_url' => $this->payment->website->website_url,
                'webhook_url' => $this->payment->website->webhook_url,
            ]);
            
            // If payment has a website, ignore payment-specific and business webhooks
            // Only the originating website should receive the webhook
        } else {
            // PRIORITY 2: Add payment-specific webhook URL (only if payment has NO website)
            if ($this->payment->webhook_url) {
                $webhookUrls[] = [
                    'url' => $this->payment->webhook_url,
                    'type' => 'payment',
                ];
                
                Log::info('Added payment-specific webhook URL (no website found)', [
                    'payment_id' => $this->payment->id,
                    'webhook_url' => $this->payment->webhook_url,
                ]);
            }

            // PRIORITY 3: Add business-level webhook URL (fallback if no website and no payment webhook)
            if (empty($webhookUrls) && $this->payment->business && $this->payment->business->webhook_url) {
                $webhookUrls[] = [
                    'url' => $this->payment->business->webhook_url,
                    'type' => 'business',
                ];
                
                Log::info('Added business-level webhook URL (fallback - no website or payment webhook)', [
                    'payment_id' => $this->payment->id,
                    'business_id' => $this->payment->business_id,
                    'webhook_url' => $this->payment->business->webhook_url,
                ]);
            }
        }

        // REMOVED: We no longer send webhooks to ALL websites under the business
        // REMOVED: We no longer send to business-level webhook if website webhook exists
        // Only the originating website (the one that created the transaction) receives the webhook

        // Remove duplicates by URL (shouldn't happen now, but keep for safety)
        $uniqueUrls = [];
        $seenUrls = [];
        foreach ($webhookUrls as $webhook) {
            if (!in_array($webhook['url'], $seenUrls)) {
                $uniqueUrls[] = $webhook;
                $seenUrls[] = $webhook['url'];
            } else {
                Log::debug('Skipped duplicate webhook URL', [
                    'payment_id' => $this->payment->id,
                    'url' => $webhook['url'],
                    'website_id' => $webhook['website_id'] ?? null,
                    'type' => $webhook['type'] ?? null,
                ]);
            }
        }
        $webhookUrls = $uniqueUrls;

        if (empty($webhookUrls)) {
            Log::warning('Payment webhook skipped - no webhook URL', [
                'payment_id' => $this->payment->id,
                'transaction_id' => $this->payment->transaction_id,
                'has_website' => $this->payment->website ? true : false,
                'has_business' => $this->payment->business ? true : false,
                'website_webhook' => $this->payment->website?->webhook_url ?? null,
                'business_webhook' => $this->payment->business?->webhook_url ?? null,
            ]);
            
            // Update status even when no webhook URLs exist - mark as 'sent' since there's nothing to send
            $this->payment->update([
                'webhook_sent_at' => now(),
                'webhook_status' => 'sent', // No URLs = nothing to send, so consider it "sent"
                'webhook_attempts' => $this->payment->webhook_attempts + 1,
                'webhook_last_error' => null,
                'webhook_urls_sent' => [],
            ]);
            
            return;
        }

        // Build webhook payload
        $payload = $this->buildWebhookPayload();

        // Log webhook URLs that will be sent to
        Log::info('Sending webhook to originating website', [
            'payment_id' => $this->payment->id,
            'transaction_id' => $this->payment->transaction_id,
            'business_website_id' => $this->payment->business_website_id,
            'website_url' => $this->payment->website?->website_url ?? 'N/A',
            'total_webhook_urls' => count($webhookUrls),
            'webhook_urls' => array_map(function($w) {
                return [
                    'url' => $w['url'],
                    'type' => $w['type'],
                    'website_id' => $w['website_id'] ?? null,
                ];
            }, $webhookUrls),
        ]);
        
        // WARNING: If payment has a website but no webhook URL configured
        if ($this->payment->business_website_id && empty($webhookUrls)) {
            Log::warning('Payment has website but no webhook URL configured', [
                'payment_id' => $this->payment->id,
                'transaction_id' => $this->payment->transaction_id,
                'business_website_id' => $this->payment->business_website_id,
                'website_url' => $this->payment->website?->website_url ?? 'N/A',
            ]);
        }

        // Send webhook to all URLs
        $successCount = 0;
        $failureCount = 0;
        $errors = [];
        $sentUrls = [];

        foreach ($webhookUrls as $webhookInfo) {
            $webhookUrl = $webhookInfo['url'];
            $webhookType = $webhookInfo['type'];

            try {
                $response = Http::timeout(30)
                    ->post($webhookUrl, $payload);

                if ($response->successful()) {
                    // Log successful webhook
                    $logService->logWebhookSent($this->payment, [
                        'status_code' => $response->status(),
                        'response' => $response->body(),
                        'webhook_type' => $webhookType,
                        'webhook_url' => $webhookUrl,
                    ]);

                    Log::info('Payment webhook sent successfully', [
                        'payment_id' => $this->payment->id,
                        'transaction_id' => $this->payment->transaction_id,
                        'webhook_url' => $webhookUrl,
                        'webhook_type' => $webhookType,
                        'status_code' => $response->status(),
                    ]);

                    $successCount++;
                    $sentUrls[] = [
                        'url' => $webhookUrl,
                        'type' => $webhookType,
                        'status' => 'success',
                        'status_code' => $response->status(),
                        'website_id' => $webhookInfo['website_id'] ?? null,
                    ];
                } elseif ($response->status() === 429) {
                    // HTTP 429: Too Many Requests - Rate limiting
                    // Don't treat as permanent failure, will retry via queue
                    $errorMsg = "HTTP 429: Rate limited. Will retry.";
                    $logService->logWebhookFailed($this->payment, $errorMsg);

                    Log::warning('Payment webhook rate limited (429)', [
                        'payment_id' => $this->payment->id,
                        'transaction_id' => $this->payment->transaction_id,
                        'webhook_url' => $webhookUrl,
                        'webhook_type' => $webhookType,
                        'status_code' => 429,
                        'note' => 'Rate limited - will retry via queue',
                    ]);

                    // For 429, we want to retry, so throw exception to trigger retry
                    throw new \Exception("Rate limited (429) for {$webhookUrl}");
                } else {
                    // Log failed webhook
                    $responseBody = $response->body();
                    // Truncate very long error messages
                    if (strlen($responseBody) > 500) {
                        $responseBody = substr($responseBody, 0, 500) . '... (truncated)';
                    }
                    
                    $errorMsg = "HTTP {$response->status()}: {$responseBody}";
                    $logService->logWebhookFailed($this->payment, $errorMsg);

                    Log::warning('Payment webhook failed', [
                        'payment_id' => $this->payment->id,
                        'transaction_id' => $this->payment->transaction_id,
                        'webhook_url' => $webhookUrl,
                        'webhook_type' => $webhookType,
                        'status_code' => $response->status(),
                        'response_preview' => substr($response->body(), 0, 200),
                    ]);

                    $failureCount++;
                    $errors[] = "{$webhookUrl}: {$errorMsg}";
                    $sentUrls[] = [
                        'url' => $webhookUrl,
                        'type' => $webhookType,
                        'status' => 'failed',
                        'error' => $errorMsg,
                        'status_code' => $response->status(),
                        'website_id' => $webhookInfo['website_id'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                // If it's a 429 rate limit exception, re-throw to trigger retry
                if (strpos($e->getMessage(), '429') !== false || strpos($e->getMessage(), 'Rate limited') !== false) {
                    throw $e;
                }
                
                $logService->logWebhookFailed($this->payment, $e->getMessage());

                Log::error('Payment webhook error', [
                    'payment_id' => $this->payment->id,
                    'transaction_id' => $this->payment->transaction_id,
                    'webhook_url' => $webhookUrl,
                    'webhook_type' => $webhookType,
                    'error' => $e->getMessage(),
                ]);

                $failureCount++;
                $errors[] = "{$webhookUrl}: {$e->getMessage()}";
                $sentUrls[] = [
                    'url' => $webhookUrl,
                    'type' => $webhookType,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'website_id' => $webhookInfo['website_id'] ?? null,
                ];
            }
        }

        // Update payment webhook status
        $webhookStatus = 'pending';
        $lastError = null;
        
        if ($successCount > 0 && $failureCount === 0) {
            $webhookStatus = 'sent';
        } elseif ($successCount > 0 && $failureCount > 0) {
            $webhookStatus = 'partial';
            $lastError = implode('; ', array_slice($errors, 0, 3)); // Store first 3 errors
        } elseif ($failureCount > 0 && $successCount === 0) {
            $webhookStatus = 'failed';
            $lastError = implode('; ', array_slice($errors, 0, 3)); // Store first 3 errors
        }

        $this->payment->update([
            'webhook_sent_at' => now(),
            'webhook_status' => $webhookStatus,
            'webhook_attempts' => $this->payment->webhook_attempts + 1,
            'webhook_last_error' => $lastError,
            'webhook_urls_sent' => $sentUrls,
        ]);

        // If all webhooks failed, throw exception to trigger retry
        if ($failureCount > 0 && $successCount === 0) {
            throw new \Exception("All webhooks failed: " . implode('; ', $errors));
        }

        // Log summary
        Log::info('Payment webhook summary', [
            'payment_id' => $this->payment->id,
            'transaction_id' => $this->payment->transaction_id,
            'business_website_id' => $this->payment->business_website_id,
            'website_url' => $this->payment->website?->website_url ?? 'N/A',
            'total_webhooks' => count($webhookUrls),
            'successful' => $successCount,
            'failed' => $failureCount,
            'webhook_status' => $webhookStatus,
            'sent_webhooks' => array_map(function($w) {
                return [
                    'url' => $w['url'] ?? 'N/A',
                    'type' => $w['type'] ?? 'N/A',
                    'status' => $w['status'] ?? 'N/A',
                    'website_id' => $w['website_id'] ?? null,
                ];
            }, $sentUrls),
        ]);
    }

    /**
     * Build webhook payload
     * Optimized to include only essential transaction approval details
     */
    protected function buildWebhookPayload(): array
    {
        $payload = [
            'event' => 'payment.approved',
            'transaction_id' => $this->payment->transaction_id,
            'status' => $this->payment->status,
            'amount' => (float) $this->payment->amount,
            'received_amount' => (float) ($this->payment->received_amount ?? $this->payment->amount),
            'payer_name' => $this->payment->payer_name,
            'bank' => $this->payment->bank,
            'account_number' => $this->payment->account_number,
            'account_details' => $this->payment->accountNumberDetails ? [
                'account_name' => $this->payment->accountNumberDetails->account_name,
                'bank_name' => $this->payment->accountNumberDetails->bank_name,
            ] : null,
            'is_mismatch' => $this->payment->is_mismatch ?? false,
            'mismatch_reason' => $this->payment->mismatch_reason,
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

        // Removed unnecessary fields:
        // - payer_account_number (not needed for webhook)
        // - email object (subject, from, date - not needed for transaction approval)
        // - name_mismatch (redundant with is_mismatch)
        // - name_similarity_percent (not needed for webhook)

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
            'trace' => $exception->getTraceAsString(),
        ]);
        
        // Update payment status to 'failed' when job fails permanently
        try {
            $this->payment->refresh();
            $this->payment->update([
                'webhook_status' => 'failed',
                'webhook_last_error' => 'Job failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
                'webhook_attempts' => $this->payment->webhook_attempts + 1,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update webhook status after job failure', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
