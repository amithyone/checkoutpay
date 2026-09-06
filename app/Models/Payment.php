<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'amount',
        'payer_name',
        'bank',
        'webhook_url',
        'account_number',
        'payer_account_number',
        'business_id',
        'developer_program_partner_business_id',
        'developer_program_partner_share_amount',
        'developer_program_partner_share_credited_at',
        'user_id',
        'renter_id',
        'business_website_id',
        'rental_id',
        'status',
        'payment_source',
        'payment_method_used',
        'external_reference',
        'checkout_pay_code',
        'checkout_pay_code_expires_at',
        'email_data',
        'matched_at',
        'expires_at',
        'is_mismatch',
        'received_amount',
        'mismatch_reason',
        'charge_percentage',
        'charge_fixed',
        'total_charges',
        'business_receives',
        'charges_paid_by_customer',
        'webhook_sent_at',
        'webhook_status',
        'webhook_attempts',
        'webhook_last_error',
        'webhook_urls_sent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'charge_percentage' => 'decimal:2',
        'charge_fixed' => 'decimal:2',
        'total_charges' => 'decimal:2',
        'business_receives' => 'decimal:2',
        'charges_paid_by_customer' => 'boolean',
        'email_data' => 'array',
        'matched_at' => 'datetime',
        'expires_at' => 'datetime',
        'checkout_pay_code_expires_at' => 'datetime',
        'webhook_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_mismatch' => 'boolean',
        'developer_program_partner_share_amount' => 'decimal:2',
        'developer_program_partner_share_credited_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    const SOURCE_INTERNAL = 'internal';

    const SOURCE_EXTERNAL_MEVONPAY = 'external_mevonpay';

    /** Merchant card checkout via Mevon/Paga hosted page (no VA). */
    const SOURCE_EXTERNAL_MEVONPAY_CARD = 'external_mevonpay_card';

    const SOURCE_EXTERNAL_SLA = 'external_sla';

    const SOURCE_EXTERNAL_MAVONPAY = 'external_mavonpay'; // legacy

    /** Tier 1 WhatsApp wallet temp VA top-up (admin payments visibility; not a merchant checkout). */
    const SOURCE_WHATSAPP_WALLET = 'whatsapp_wallet';

    /** Merchant Rubies pay-in VA (Mevon createrubies business account on `businesses.rubies_business_account_number`). */
    const SOURCE_BUSINESS_RUBIES_VA = 'business_rubies_va';

    /** @deprecated Use SOURCE_PARTNER_WALLET_API */
    const SOURCE_TAGINE_APP = 'tagine_app';

    /** Debit customer WhatsApp wallet, credit merchant via X-API-Key partner API (no chat PIN). */
    const SOURCE_PARTNER_WALLET_API = 'partner_wallet_api';

    /** Historical rows imported from CSV / legacy database dump. */
    const SOURCE_LEGACY_IMPORT = 'legacy_import';

    const METHOD_BANK_TRANSFER = 'bank_transfer';

    const METHOD_CARD = 'card';

    const METHOD_WHATSAPP_WALLET = 'whatsapp_wallet';

    public function isMevonCardCheckout(): bool
    {
        if ($this->payment_source === self::SOURCE_EXTERNAL_MEVONPAY_CARD) {
            return true;
        }

        $emailData = is_array($this->email_data) ? $this->email_data : [];

        return ($emailData['payment_method'] ?? null) === self::METHOD_CARD;
    }

    /**
     * @return array{checkout_url?: string, payment_reference?: string}|null
     */
    public function cardCheckoutPayload(): ?array
    {
        if (! $this->isMevonCardCheckout()) {
            return null;
        }

        $emailData = is_array($this->email_data) ? $this->email_data : [];
        $block = $emailData['card_checkout'] ?? null;
        if (! is_array($block)) {
            $block = [];
        }

        $checkoutUrl = trim((string) ($block['checkout_url'] ?? ''));
        $paymentReference = trim((string) ($block['payment_reference'] ?? $this->external_reference ?? ''));

        if ($checkoutUrl === '' && $paymentReference === '') {
            return null;
        }

        return array_filter([
            'checkout_url' => $checkoutUrl !== '' ? $checkoutUrl : null,
            'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
        ], static fn ($v) => $v !== null);
    }

    public static function tracksPaymentMethodUsed(): bool
    {
        static $tracks = null;

        if ($tracks === null) {
            $tracks = Schema::hasColumn((new static)->getTable(), 'payment_method_used');
        }

        return $tracks;
    }

    /**
     * Get pending payments eligible for bank-email matching (excludes stale non-invoice/membership rows).
     */
    public static function pending()
    {
        return static::where('status', self::STATUS_PENDING)->matchablePending();
    }

    /**
     * Pending rows that can still receive automatic bank-transfer matching.
     */
    public function scopeMatchablePending($query)
    {
        $cutoff = now()->subMinutes(self::PENDING_MAX_AGE_MINUTES);

        return $query->where(function ($eligible) use ($cutoff) {
            $eligible->where(function ($exempt) {
                $exempt->whereJsonContains('email_data->service', 'invoice')
                    ->orWhereJsonContains('email_data->service', 'membership')
                    ->orWhereJsonContains('email_data->service', 'payment_link')
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(email_data, '$.membership_id')) IS NOT NULL")
                    ->orWhereExists(function ($sub) {
                        $sub->from('invoice_payments')
                            ->selectRaw('1')
                            ->whereColumn('invoice_payments.payment_id', 'payments.id');
                    })
                    ->orWhereExists(function ($sub) {
                        $sub->from('payment_link_payments')
                            ->selectRaw('1')
                            ->whereColumn('payment_link_payments.payment_id', 'payments.id');
                    });
            })->orWhere(function ($regular) use ($cutoff) {
                $regular->where('created_at', '>', $cutoff)
                    ->where(function ($exp) {
                        $exp->whereNull('expires_at')->orWhere('expires_at', '>', now());
                    });
            });
        });
    }

    public function isStalePending(): bool
    {
        return $this->status === self::STATUS_PENDING
            && ! $this->shouldStayPendingIndefinitely()
            && ($this->isExpired() || ! $this->isWithinMatchWindow());
    }

    /** Pending checkout rows past expiry or max age (excluding invoice/membership). */
    public function scopeStalePending($query)
    {
        $cutoff = now()->subMinutes(self::PENDING_MAX_AGE_MINUTES);

        return $query->where('status', self::STATUS_PENDING)
            ->where(function ($q) use ($cutoff) {
                $q->where(function ($exp) {
                    $exp->whereNotNull('expires_at')->where('expires_at', '<=', now());
                })->orWhere('created_at', '<=', $cutoff);
            })
            ->where(function ($q) {
                $q->where(function ($row) {
                    $row->whereNull('email_data')
                        ->orWhere(function ($service) {
                            $service->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(email_data, '$.service')) NOT IN ('invoice', 'membership', 'payment_link')")
                                ->orWhereRaw("JSON_EXTRACT(email_data, '$.service') IS NULL");
                        });
                })
                    ->whereRaw("JSON_EXTRACT(email_data, '$.membership_id') IS NULL")
                    ->whereNotExists(function ($sub) {
                        $sub->from('invoice_payments')
                            ->selectRaw('1')
                            ->whereColumn('invoice_payments.payment_id', 'payments.id');
                    })
                    ->whereNotExists(function ($sub) {
                        $sub->from('payment_link_payments')
                            ->selectRaw('1')
                            ->whereColumn('payment_link_payments.payment_id', 'payments.id');
                    });
            });
    }

    /**
     * Check if payment is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Scope for expired payments
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
            ->where('status', self::STATUS_PENDING);
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if payment is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isInvoicePayment(): bool
    {
        if (($this->email_data['service'] ?? null) === 'invoice') {
            return true;
        }

        if ($this->relationLoaded('invoicePayment')) {
            return $this->invoicePayment !== null;
        }

        return InvoicePayment::where('payment_id', $this->id)->exists();
    }

    public function isMembershipPayment(): bool
    {
        $emailData = is_array($this->email_data) ? $this->email_data : [];

        return ($emailData['service'] ?? null) === 'membership'
            || ! empty($emailData['membership_id']);
    }

    public function isPaymentLinkPayment(): bool
    {
        if (($this->email_data['service'] ?? null) === 'payment_link') {
            return true;
        }

        return PaymentLinkPayment::where('payment_id', $this->id)->exists();
    }

    /** Invoice, membership, and payment-link checkouts stay open until paid or manually closed. */
    public function shouldStayPendingIndefinitely(): bool
    {
        return $this->isInvoicePayment() || $this->isMembershipPayment() || $this->isPaymentLinkPayment();
    }

    /** Hard cap: non-invoice/membership bank-transfer checkouts cannot stay matchable beyond this age. */
    public const PENDING_MAX_AGE_MINUTES = 2400;

    public static function pendingExpiryMinutes(): int
    {
        $configured = max(5, (int) \App\Models\Setting::get('transaction_pending_time_minutes', self::PENDING_MAX_AGE_MINUTES));

        return min($configured, self::PENDING_MAX_AGE_MINUTES);
    }

    public static function defaultExpiresAtForService(?string $service, bool $useInvoicePool = false): ?\Carbon\Carbon
    {
        $normalized = strtolower(trim((string) ($service ?? '')));
        if ($useInvoicePool || in_array($normalized, ['invoice', 'membership', 'payment_link'], true)) {
            return null;
        }

        return now()->addMinutes(self::pendingExpiryMinutes());
    }

    /** Non-invoice/membership payments older than {@see PENDING_MAX_AGE_MINUTES} must not auto-match. */
    public function isWithinMatchWindow(): bool
    {
        if ($this->shouldStayPendingIndefinitely()) {
            return true;
        }

        if (! $this->created_at) {
            return false;
        }

        return $this->created_at->gte(now()->subMinutes(self::PENDING_MAX_AGE_MINUTES));
    }

    public function isMatchEligible(): bool
    {
        if ($this->status !== self::STATUS_PENDING || $this->isExpired()) {
            return false;
        }

        if (! $this->isWithinMatchWindow()) {
            return false;
        }

        if ($this->isExternalGatewayPayment()) {
            return false;
        }

        $emailData = is_array($this->email_data) ? $this->email_data : [];
        if (! empty($emailData['skip_auto_match'])) {
            return false;
        }

        return true;
    }

    public function isExternalGatewayPayment(): bool
    {
        return in_array($this->payment_source, [
            self::SOURCE_EXTERNAL_MEVONPAY,
            self::SOURCE_EXTERNAL_SLA,
            self::SOURCE_EXTERNAL_MAVONPAY,
        ], true);
    }

    /**
     * Sanitize email data to keep only essential fields
     * Only stores: name, amount, time, subject, from, email_id
     * Preserves manual_verification if it exists (for admin tracking)
     */
    public static function sanitizeEmailData(array $emailData): array
    {
        $sanitized = [];

        // Only keep these essential fields
        if (isset($emailData['sender_name'])) {
            $sanitized['name'] = $emailData['sender_name'];
        } elseif (isset($emailData['payer_name'])) {
            $sanitized['name'] = $emailData['payer_name'];
        }

        // Prioritize received_amount if it exists (for edited amounts in manual approval)
        if (isset($emailData['received_amount'])) {
            $sanitized['amount'] = $emailData['received_amount'];
            $sanitized['received_amount'] = $emailData['received_amount']; // Keep both for webhook
        } elseif (isset($emailData['amount'])) {
            $sanitized['amount'] = $emailData['amount'];
        }

        if (isset($emailData['date'])) {
            $sanitized['time'] = $emailData['date'];
        } elseif (isset($emailData['transaction_date'])) {
            $sanitized['time'] = $emailData['transaction_date'];
        }

        if (isset($emailData['subject'])) {
            $sanitized['subject'] = $emailData['subject'];
        }

        if (isset($emailData['from'])) {
            $sanitized['from'] = $emailData['from'];
        }

        if (isset($emailData['processed_email_id'])) {
            $sanitized['email_id'] = $emailData['processed_email_id'];
        } elseif (isset($emailData['linked_email_id'])) {
            $sanitized['email_id'] = $emailData['linked_email_id'];
        }

        // Preserve manual_verification if it exists (admin tracking metadata)
        if (isset($emailData['manual_verification'])) {
            $sanitized['manual_verification'] = $emailData['manual_verification'];
        }

        return $sanitized;
    }

    /**
     * Metadata keys kept when approving a payment. Webhooks and bank emails only pass transfer
     * fields into approve(); without this, membership checkout (NigTax PRO, etc.) loses
     * membership_id / member_email and subscriptions or accounts are never created.
     *
     * @return list<string>
     */
    public static function preservedEmailDataKeysOnApprove(): array
    {
        return [
            'membership_id',
            'member_name',
            'member_email',
            'member_phone',
            'service',
            'return_url',
            'website_url',
            'skip_auto_match',
            'nigtax_certified_order_id',
            'manual_verification',
            'api_amount_update',
            'wa_topup',
            'whatsapp_wallet_id',
            'whatsapp_pending_topup_id',
            'wa_permanent_va',
            'rubies_business_va',
            'mevonpay_reference',
            'reported_amount',
            'mevonpay_inbound_webhook',
            'mevonpay_inbound_webhooks',
            'payment_method',
            'card_checkout',
            'customer_email',
            'customer_phone',
            'currency',
            'mevon_card_checkout',
            'gross_amount',
            'charge_applied',
            'developer_program_partner_business_id',
        ];
    }

    /**
     * Approve payment
     */
    public function approve(array $emailData = [], bool $isMismatch = false, ?float $receivedAmount = null, ?string $mismatchReason = null): bool
    {
        // Ensure amount is in email_data if not provided
        if (! isset($emailData['amount']) && ! isset($emailData['received_amount'])) {
            $emailData['amount'] = $this->amount;
        }

        $existingEmailData = is_array($this->email_data) ? $this->email_data : [];
        $preserved = array_intersect_key(
            $existingEmailData,
            array_flip(self::preservedEmailDataKeysOnApprove())
        );

        // Sanitize email_data to remove large text/html bodies
        $sanitizedEmailData = self::sanitizeEmailData($emailData);

        // Restore checkout / membership metadata not present in webhook or email payloads
        $mergedEmailData = array_merge($sanitizedEmailData, $preserved);

        $updateData = [
            'status' => self::STATUS_APPROVED,
            'email_data' => $mergedEmailData,
            'matched_at' => now(),
            'is_mismatch' => $isMismatch,
            'received_amount' => $receivedAmount,
            'mismatch_reason' => $mismatchReason,
        ];

        if (self::tracksPaymentMethodUsed() && empty($this->payment_method_used)) {
            $updateData['payment_method_used'] = self::METHOD_BANK_TRANSFER;
        }

        if (! $this->business_website_id && $this->business_id) {
            $websiteId = app(\App\Services\Business\PaymentWebsiteAttributionService::class)
                ->resolveWebsiteId($this);
            if ($websiteId) {
                $updateData['business_website_id'] = $websiteId;
            }
        }

        // Update payer_name, bank, and payer_account_number from email_data if provided
        // Map sender_name to payer_name if payer_name is not set (they are the same)
        $payerName = $emailData['payer_name'] ?? $emailData['sender_name'] ?? null;
        if (! empty($payerName)) {
            $updateData['payer_name'] = strtolower(trim($payerName));
        }
        // If payment doesn't have payer_name but email has sender_name, use it
        if (empty($this->payer_name) && ! empty($emailData['sender_name'])) {
            $updateData['payer_name'] = strtolower(trim($emailData['sender_name']));
        }
        if (! empty($emailData['bank'])) {
            $updateData['bank'] = $emailData['bank'];
        }
        if (! empty($emailData['payer_account_number'])) {
            $updateData['payer_account_number'] = $emailData['payer_account_number'];
        }

        $updated = $this->update($updateData);
        if ($updated && self::tracksPaymentMethodUsed() && $this->checkout_pay_code && $this->payment_method_used !== self::METHOD_WHATSAPP_WALLET) {
            app(\App\Services\Whatsapp\WhatsappCheckoutPayCodeService::class)->invalidateCode($this->fresh());
        }
        if ($updated && $this->user_id) {
            $this->creditUserWallet($receivedAmount ?? (float) $this->amount);
        }
        if ($updated && $this->renter_id) {
            $this->creditRenterWallet($receivedAmount ?? (float) $this->amount);
        }

        return $updated;
    }

    /**
     * Credit user wallet for wallet top-up payment and record transaction.
     */
    public function creditUserWallet(float $amount): void
    {
        $user = \App\Models\User::find($this->user_id);
        if (! $user) {
            return;
        }
        $user->increment('wallet_bal', $amount);
        \App\Models\UserWalletTransaction::create([
            'user_id' => $user->id,
            'type' => 'funding',
            'amount' => $amount,
            'description' => 'Wallet top-up',
            'reference_type' => self::class,
            'reference_id' => $this->id,
        ]);
    }

    /**
     * Credit renter wallet for rentals wallet top-up payment.
     */
    public function creditRenterWallet(float $amount): void
    {
        $renter = \App\Models\Renter::find($this->renter_id);
        if (! $renter) {
            return;
        }

        $renter->increment('wallet_balance', $amount);
        try {
            app(\App\Services\Rentals\RentalsPushNotifier::class)->walletCredit(
                (int) $renter->id,
                (float) $amount,
                (int) $this->id
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Push notify failed for wallet credit', [
                'payment_id' => $this->id,
                'renter_id' => $renter->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reject payment
     */
    public function reject(string $reason = ''): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'email_data' => array_merge($this->email_data ?? [], ['rejection_reason' => $reason]),
            'matched_at' => now(),
        ]);
    }

    /**
     * Get the business that owns this payment
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the user (for wallet top-up payments)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function renter()
    {
        return $this->belongsTo(\App\Models\Renter::class);
    }

    /**
     * Get the website that generated this payment
     */
    public function website()
    {
        return $this->belongsTo(BusinessWebsite::class, 'business_website_id');
    }

    /**
     * Resolved merchant webhook destination for UI — same priority as
     * {@see \App\Jobs\SendWebhookNotification::getWebhookUrls()} (approved website
     * {@see BusinessWebsite::$webhook_url} first, then legacy {@see Payment::$webhook_url}).
     */
    public function primaryMerchantWebhookUrl(): ?string
    {
        if ($this->business_website_id) {
            $website = $this->relationLoaded('website') ? $this->website : $this->website()->first();
            if ($website && $website->is_approved && filled($website->webhook_url)) {
                return $website->webhook_url;
            }
        }

        return $this->webhook_url ?: null;
    }

    /**
     * Parsed merchant webhook failure(s) stored on webhook_last_error.
     *
     * @return list<array{url: ?string, http_status: mixed, response_body: ?string, error: ?string, via: mixed}>
     */
    public function webhookFailureDetails(): array
    {
        $raw = trim((string) $this->webhook_last_error);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [[
                'url' => null,
                'http_status' => null,
                'response_body' => null,
                'error' => $raw,
                'via' => null,
            ]];
        }

        if ($decoded === []) {
            return [];
        }

        $rows = array_is_list($decoded) ? $decoded : [$decoded];
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                $out[] = [
                    'url' => null,
                    'http_status' => null,
                    'response_body' => null,
                    'error' => is_scalar($row) ? (string) $row : json_encode($row),
                    'via' => null,
                ];
                continue;
            }

            $out[] = [
                'url' => isset($row['url']) ? (string) $row['url'] : null,
                'http_status' => $row['http_status'] ?? $row['status'] ?? null,
                'response_body' => isset($row['response_body']) ? (string) $row['response_body'] : null,
                'error' => isset($row['error']) ? (string) $row['error'] : null,
                'via' => $row['via'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Get the rental this payment is for (when payment is for an approved rental)
     */
    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }

    /**
     * Get account number details
     */
    public function accountNumberDetails()
    {
        return $this->belongsTo(AccountNumber::class, 'account_number', 'account_number');
    }

    /**
     * Get match attempts for this payment
     */
    public function matchAttempts()
    {
        return $this->hasMany(MatchAttempt::class);
    }

    /**
     * Get status checks for this payment (API calls by business)
     */
    public function statusChecks()
    {
        return $this->hasMany(PaymentStatusCheck::class);
    }

    /**
     * Get the processed email that matched with this payment
     */
    public function matchedEmail()
    {
        return $this->hasOne(ProcessedEmail::class, 'matched_payment_id');
    }

    /**
     * Normalize webhook URL list (handles live-sync double-encoded JSON strings).
     *
     * @return list<mixed>
     */
    public static function decodeJsonList(mixed $value): array
    {
        while (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [$value];
            }
            $value = $decoded;
        }

        return is_array($value) ? array_values($value) : [];
    }

    protected function webhookUrlsSent(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => self::decodeJsonList($value),
            set: fn ($value) => json_encode(self::decodeJsonList($value)),
        );
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Invalidate account number cache when payment is created or status changes
        static::created(function ($payment) {
            if ($payment->account_number) {
                app(\App\Services\AccountNumberService::class)->invalidatePendingAccountsCache();
            }
        });

        static::updated(function ($payment) {
            // Invalidate cache if status or account_number changed (e.g. payment approved → account released after window)
            if ($payment->wasChanged(['status', 'account_number', 'expires_at'])) {
                app(\App\Services\AccountNumberService::class)->invalidatePendingAccountsCache();
            }
        });
    }
}
