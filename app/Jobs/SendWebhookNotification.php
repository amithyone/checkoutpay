<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\Setting;
use App\Support\InternalPaymentWebhookUrl;
use App\Support\SafeOutboundUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWebhookNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Populated after each synchronous run of handle() when at least one HTTP attempt was made.
     * Each entry: url, success, http_status, response_body (truncated), error (if any).
     *
     * @var list<array<string, mixed>>|null
     */
    public static ?array $lastHttpDeliveryLog = null;

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
        self::$lastHttpDeliveryLog = [];

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

            self::$lastHttpDeliveryLog[] = [
                'url' => null,
                'note' => 'No merchant webhook URLs resolved for this payment (website webhook empty or internal-only URL filtered).',
            ];
            
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

                self::$lastHttpDeliveryLog[] = [
                    'url' => $webhookUrl,
                    'success' => $response['success'],
                    'http_status' => $response['status'] ?? null,
                    'response_body' => $response['response_body'] ?? null,
                    'error' => $response['error'] ?? null,
                ];
                
                if ($response['success']) {
                    $successCount++;
                    $sentUrls[] = $webhookUrl;
                } else {
                    $failedUrls[] = $this->formatHttpFailure($webhookUrl, $response);
                    Log::warning('Webhook delivery failed', [
                        'payment_id' => $this->payment->id,
                        'transaction_id' => $this->payment->transaction_id,
                        'webhook_url' => $webhookUrl,
                        'http_status' => $response['status'] ?? null,
                        'response_body' => $response['response_body'] ?? null,
                        'error' => $response['error'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Exception sending webhook', [
                    'payment_id' => $this->payment->id,
                    'webhook_url' => $webhookUrl,
                    'error' => $e->getMessage(),
                ]);
                
                $failedUrls[] = $this->formatHttpFailure($webhookUrl, [
                    'error' => $this->formatFullError($e),
                    'status' => null,
                    'response_body' => null,
                    'via' => null,
                ]);

                self::$lastHttpDeliveryLog[] = [
                    'url' => $webhookUrl,
                    'success' => false,
                    'http_status' => null,
                    'response_body' => null,
                    'error' => $this->formatFullError($e),
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
            $updateData['webhook_last_error'] = json_encode(
                $failedUrls,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
        } else {
            $updateData['webhook_last_error'] = null;
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
     * Handle job failure - store full exception in webhook_last_error.
     */
    public function failed(\Throwable $exception): void
    {
        $this->payment->update([
            'webhook_last_error' => $this->formatFullError($exception),
            'webhook_status' => 'failed',
        ]);
    }

    /**
     * Get webhook URLs for this payment
     * Only sends to the website that generated the transaction, not all business websites
     */
    protected function getWebhookUrls(): array
    {
        $urls = [];

        // PRIORITY 1: Get webhook URL from the specific website that generated this payment
        if ($this->payment->business_website_id) {
            $website = $this->payment->website;
            if ($website && $website->is_approved && $website->webhook_url) {
                $urls[] = $website->webhook_url;
            }
        }

        // PRIORITY 2: Fallback to webhook_url from payment (legacy support for old payments)
        // Only use this if no website was found above
        if (empty($urls) && $this->payment->webhook_url) {
            $urls[] = $this->payment->webhook_url;
        }

        $urls = array_values(array_filter(array_unique($urls), function (string $url): bool {
            $url = InternalPaymentWebhookUrl::rewriteToAppUrl($url);

            if (InternalPaymentWebhookUrl::isInternal($url)) {
                return false;
            }

            $reason = SafeOutboundUrl::rejectionReason($url);
            if ($reason !== null) {
                Log::warning('Skipping unsafe webhook URL', [
                    'payment_id' => $this->payment->id,
                    'webhook_url' => $url,
                    'reason' => $reason,
                ]);

                return false;
            }

            return true;
        }));

        return $urls;
    }

    /**
     * Send webhook to a single URL (direct or via Namecheap egress relay).
     */
    protected function sendWebhook(string $webhookUrl): array
    {
        try {
            $payload = $this->buildWebhookPayload();
            $result = \App\Services\Webhook\WebhookEgressRelay::deliver($webhookUrl, $payload);

            if ($result['success']) {
                return [
                    'success' => true,
                    'status' => $result['status'],
                    'response_body' => $result['response_body'] ?? null,
                    'via' => $result['via'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Webhook delivery failed',
                'status' => $result['status'] ?? null,
                'response_body' => $result['response_body'] ?? null,
                'via' => $result['via'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $this->formatFullError($e),
                'status' => null,
                'response_body' => null,
            ];
        }
    }

    /**
     * @param  array{error?: ?string, status?: ?int, response_body?: ?string, via?: ?string}  $response
     * @return array{url: string, http_status: ?int, response_body: ?string, error: string, via: ?string}
     */
    protected function formatHttpFailure(string $webhookUrl, array $response): array
    {
        return [
            'url' => $webhookUrl,
            'http_status' => isset($response['status']) ? $response['status'] : null,
            'response_body' => $response['response_body'] ?? null,
            'error' => $response['error'] ?? 'Webhook delivery failed',
            'via' => $response['via'] ?? null,
        ];
    }

    /**
     * Format exception/throwable as full error string for storage in webhook_last_error.
     */
    protected function formatFullError(\Throwable $e): string
    {
        $parts = [
            get_class($e) . ': ' . $e->getMessage(),
            $e->getFile() . ':' . $e->getLine(),
            $e->getTraceAsString(),
        ];
        return implode("\n", $parts);
    }

    /**
     * Pay at Shop sessions use session_uuid as the primary key — there is no id column.
     */
    protected function linkedBroadcastSession(): ?object
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('broadcast_sessions')) {
                return null;
            }
            if (! \Illuminate\Support\Facades\Schema::hasColumn('broadcast_sessions', 'payment_id')) {
                return null;
            }

            $query = \Illuminate\Support\Facades\DB::table('broadcast_sessions')
                ->where('payment_id', $this->payment->id);

            if (\Illuminate\Support\Facades\Schema::hasColumn('broadcast_sessions', 'created_at')) {
                $query->orderByDesc('created_at');
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('broadcast_sessions', 'opened_at')) {
                $query->orderByDesc('opened_at');
            }

            return $query->first();
        } catch (\Throwable $e) {
            Log::warning('broadcast_session_lookup_for_webhook_failed', [
                'payment_id' => $this->payment->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build webhook payload
     */
    protected function buildWebhookPayload(): array
    {
        $emailData = $this->payment->email_data ?? [];

        $payerName = $this->payment->payer_name ?? $emailData['name'] ?? $emailData['sender_name'] ?? null;
        $payerAccount = $this->payment->payer_account_number ?? null;
        $payerBank = $this->payment->bank ?? null;

        $broadcastSession = $this->linkedBroadcastSession();

        $totalCharges = (float) ($this->payment->total_charges ?? 0);
        $shareAmount = $this->payment->developer_program_partner_share_amount !== null
            ? (float) $this->payment->developer_program_partner_share_amount
            : null;
        $sharePercentEffective = ($totalCharges > 0 && $shareAmount !== null && $shareAmount > 0)
            ? round(100 * $shareAmount / $totalCharges, 4)
            : null;

        $payload = [
            'event' => 'payment.approved',
            'transaction_id' => $this->payment->transaction_id,
            'external_reference' => $this->payment->external_reference,
            'status' => $this->payment->status,
            'amount' => (float) $this->payment->amount,
            'received_amount' => $this->payment->received_amount ? (float) $this->payment->received_amount : (float) $this->payment->amount,
            'payer_name' => $payerName,
            'payerName' => $payerName,
            'sender_name' => $payerName,
            'bank' => $payerBank,
            'bank_name' => $payerBank,
            'payer_bank' => $payerBank,
            'payer_account' => $payerAccount,
            'payer_account_number' => $payerAccount,
            'sender_account' => $payerAccount,
            'payer' => [
                'name' => $payerName,
                'account' => $payerAccount,
                'bank' => $payerBank,
            ],
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
            'payment_method' => $this->payment->payment_method_used,
            'email_data' => $emailData, // Include sanitized email data
            'developer_program_partner_business_id' => $this->payment->developer_program_partner_business_id
                ? (int) $this->payment->developer_program_partner_business_id
                : null,
            'developer_program_partner_share_amount' => $shareAmount,
            'developer_program_partner_share_percent_effective' => $sharePercentEffective,
            'developer_program_fee_share_base_description' => Setting::get('developer_program_fee_share_base_description'),
        ];

        if ($broadcastSession) {
            $payload['session_id'] = (string) $broadcastSession->session_uuid;
            $payload['reference'] = $this->payment->transaction_id ?? $this->payment->external_reference;
            $payload['broadcast_event'] = 'payment.confirmed';
        }

        return $payload;
    }
}
