<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'business_website_id',
        'status',
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
        'webhook_urls_sent' => 'array',
        'matched_at' => 'datetime',
        'expires_at' => 'datetime',
        'webhook_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_mismatch' => 'boolean',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get pending payments
     */
    public static function pending()
    {
        return static::where('status', self::STATUS_PENDING)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
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
     * Approve payment
     */
    public function approve(array $emailData = [], bool $isMismatch = false, ?float $receivedAmount = null, ?string $mismatchReason = null): bool
    {
        // Ensure amount is in email_data if not provided
        if (!isset($emailData['amount']) && !isset($emailData['received_amount'])) {
            $emailData['amount'] = $this->amount;
        }
        
        // Sanitize email_data to remove large text/html bodies
        $sanitizedEmailData = self::sanitizeEmailData($emailData);
        
        $updateData = [
            'status' => self::STATUS_APPROVED,
            'email_data' => $sanitizedEmailData,
            'matched_at' => now(),
            'is_mismatch' => $isMismatch,
            'received_amount' => $receivedAmount,
            'mismatch_reason' => $mismatchReason,
        ];

        // Update payer_name, bank, and payer_account_number from email_data if provided
        // Map sender_name to payer_name if payer_name is not set (they are the same)
        $payerName = $emailData['payer_name'] ?? $emailData['sender_name'] ?? null;
        if (!empty($payerName)) {
            $updateData['payer_name'] = strtolower(trim($payerName));
        }
        // If payment doesn't have payer_name but email has sender_name, use it
        if (empty($this->payer_name) && !empty($emailData['sender_name'])) {
            $updateData['payer_name'] = strtolower(trim($emailData['sender_name']));
        }
        if (!empty($emailData['bank'])) {
            $updateData['bank'] = $emailData['bank'];
        }
        if (!empty($emailData['payer_account_number'])) {
            $updateData['payer_account_number'] = $emailData['payer_account_number'];
        }

        return $this->update($updateData);
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
     * Get the website that generated this payment
     */
    public function website()
    {
        return $this->belongsTo(BusinessWebsite::class, 'business_website_id');
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
            // Invalidate cache if status or account_number changed
            if ($payment->isDirty(['status', 'account_number', 'expires_at'])) {
                app(\App\Services\AccountNumberService::class)->invalidatePendingAccountsCache();
            }
        });
    }
}
