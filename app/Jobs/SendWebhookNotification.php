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
        // Reload payment with relationships - CRITICAL: Load business websites relationship
        $this->payment->load(['business.websites', 'accountNumberDetails', 'website']);

        // Collect all webhook URLs to send to
        $webhookUrls = [];

        // 1. Add website-specific webhook URL (if payment has a website)
        // CRITICAL: This ensures the payment's associated website ALWAYS gets webhook
        if ($this->payment->website && $this->payment->website->webhook_url) {
            $webhookUrls[] = [
                'url' => $this->payment->website->webhook_url,
                'type' => 'website',
                'website_id' => $this->payment->website->id,
            ];
            
            Log::info('Added payment-specific website webhook', [
                'payment_id' => $this->payment->id,
                'website_id' => $this->payment->website->id,
                'website_url' => $this->payment->website->website_url,
                'webhook_url' => $this->payment->website->webhook_url,
            ]);
        }

        // 2. Add payment-specific webhook URL
        if ($this->payment->webhook_url) {
            $webhookUrls[] = [
                'url' => $this->payment->webhook_url,
                'type' => 'payment',
            ];
        }

        // 3. Add business webhook URL
        if ($this->payment->business && $this->payment->business->webhook_url) {
            $webhookUrls[] = [
                'url' => $this->payment->business->webhook_url,
                'type' => 'business',
            ];
        }

        // 4. Add webhooks from ALL websites under the business (if payment has a business)
        if ($this->payment->business) {
            // CRITICAL: Query websites directly to ensure we get all approved websites with webhooks
            // Don't rely on the relationship being loaded - query fresh from database
            $businessWebsites = \App\Models\BusinessWebsite::where('business_id', $this->payment->business_id)
                ->where('is_approved', true)
                ->whereNotNull('webhook_url')
                ->where('webhook_url', '!=', '')
                ->get();

            Log::info('Loading business websites for webhook', [
                'payment_id' => $this->payment->id,
                'business_id' => $this->payment->business_id,
                'business_name' => $this->payment->business->name,
                'websites_found' => $businessWebsites->count(),
                'website_details' => $businessWebsites->map(function($w) {
                    return [
                        'id' => $w->id,
                        'url' => $w->website_url,
                        'webhook_url' => $w->webhook_url,
                    ];
                })->toArray(),
            ]);

            foreach ($businessWebsites as $website) {
                // CRITICAL: Always add fadded.net webhook if it's a business website, even if already added
                // This ensures fadded.net ALWAYS receives webhooks for all transactions
                $isFaddedNet = stripos($website->website_url, 'fadded.net') !== false;
                
                // Skip if already added (unless it's fadded.net - we want to ensure it's included)
                $alreadyAdded = false;
                foreach ($webhookUrls as $existing) {
                    if (isset($existing['website_id']) && $existing['website_id'] === $website->id) {
                        $alreadyAdded = true;
                        break;
                    }
                    if ($existing['url'] === $website->webhook_url) {
                        $alreadyAdded = true;
                        break;
                    }
                }

                // Always add if not already added, OR if it's fadded.net (to ensure it's always included)
                if (!$alreadyAdded || $isFaddedNet) {
                    // If already added but it's fadded.net, log it but don't duplicate
                    if ($alreadyAdded && $isFaddedNet) {
                        Log::info('Fadded.net webhook already included, ensuring it stays', [
                            'payment_id' => $this->payment->id,
                            'website_id' => $website->id,
                            'webhook_url' => $website->webhook_url,
                        ]);
                        continue; // Don't add duplicate, but log that we checked
                    }
                    
                    $webhookUrls[] = [
                        'url' => $website->webhook_url,
                        'type' => 'business_website',
                        'website_id' => $website->id,
                    ];
                    
                    Log::info('Added business website webhook URL', [
                        'payment_id' => $this->payment->id,
                        'website_id' => $website->id,
                        'website_url' => $website->website_url,
                        'webhook_url' => $website->webhook_url,
                        'is_fadded_net' => $isFaddedNet,
                    ]);
                } else {
                    Log::info('Skipped business website webhook (already added)', [
                        'payment_id' => $this->payment->id,
                        'website_id' => $website->id,
                        'webhook_url' => $website->webhook_url,
                    ]);
                }
            }
        }

        // Remove duplicates (same URL)
        $uniqueUrls = [];
        $seenUrls = [];
        foreach ($webhookUrls as $webhook) {
            if (!in_array($webhook['url'], $seenUrls)) {
                $uniqueUrls[] = $webhook;
                $seenUrls[] = $webhook['url'];
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

        // Log all webhook URLs that will be sent to
        Log::info('Sending webhooks to all URLs', [
            'payment_id' => $this->payment->id,
            'transaction_id' => $this->payment->transaction_id,
            'total_webhook_urls' => count($webhookUrls),
            'webhook_urls' => array_map(function($w) {
                return [
                    'url' => $w['url'],
                    'type' => $w['type'],
                    'website_id' => $w['website_id'] ?? null,
                ];
            }, $webhookUrls),
        ]);

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
                    ];
                } else {
                    // Log failed webhook
                    $errorMsg = "HTTP {$response->status()}: {$response->body()}";
                    $logService->logWebhookFailed($this->payment, $errorMsg);

                    Log::warning('Payment webhook failed', [
                        'payment_id' => $this->payment->id,
                        'transaction_id' => $this->payment->transaction_id,
                        'webhook_url' => $webhookUrl,
                        'webhook_type' => $webhookType,
                        'status_code' => $response->status(),
                        'response' => $response->body(),
                    ]);

                    $failureCount++;
                    $errors[] = "{$webhookUrl}: {$errorMsg}";
                    $sentUrls[] = [
                        'url' => $webhookUrl,
                        'type' => $webhookType,
                        'status' => 'failed',
                        'error' => $errorMsg,
                    ];
                }
            } catch (\Exception $e) {
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
            'total_webhooks' => count($webhookUrls),
            'successful' => $successCount,
            'failed' => $failureCount,
            'webhook_status' => $webhookStatus,
        ]);
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
