<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'payment_id',
        'business_id',
        'event_type',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Event types
    const EVENT_PAYMENT_REQUESTED = 'payment_requested';
    const EVENT_ACCOUNT_ASSIGNED = 'account_assigned';
    const EVENT_EMAIL_RECEIVED = 'email_received';
    const EVENT_PAYMENT_MATCHED = 'payment_matched';
    const EVENT_PAYMENT_APPROVED = 'payment_approved';
    const EVENT_PAYMENT_REJECTED = 'payment_rejected';
    const EVENT_PAYMENT_EXPIRED = 'payment_expired';
    const EVENT_WEBHOOK_SENT = 'webhook_sent';
    const EVENT_WEBHOOK_FAILED = 'webhook_failed';
    const EVENT_WITHDRAWAL_REQUESTED = 'withdrawal_requested';
    const EVENT_WITHDRAWAL_APPROVED = 'withdrawal_approved';
    const EVENT_WITHDRAWAL_REJECTED = 'withdrawal_rejected';
    const EVENT_WITHDRAWAL_PROCESSED = 'withdrawal_processed';

    /**
     * Get the payment associated with this log
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the business associated with this log
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Scope for specific transaction
     */
    public function scopeForTransaction($query, string $transactionId)
    {
        return $query->where('transaction_id', $transactionId)->orderBy('created_at');
    }

    /**
     * Scope for event type
     */
    public function scopeEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }
}
