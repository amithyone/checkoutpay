<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessedEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_account_id',
        'source', // 'webhook', 'imap', 'gmail_api'
        'message_id',
        'subject',
        'from_email',
        'from_name',
        'text_body',
        'html_body',
        'email_date',
        'amount',
        'sender_name',
        'account_number',
        'extracted_data',
        'matched_payment_id',
        'matched_at',
        'is_matched',
        'processing_notes',
        'last_match_reason',
        'match_attempts_count',
        'extraction_method',
    ];

    protected $casts = [
        'email_date' => 'datetime',
        'matched_at' => 'datetime',
        'amount' => 'decimal:2',
        'is_matched' => 'boolean',
        'extracted_data' => 'array',
    ];

    /**
     * Get the email account that received this email
     */
    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /**
     * Get the payment this email matched
     */
    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }

    /**
     * Mark this email as matched to a payment
     */
    public function markAsMatched(Payment $payment): void
    {
        $this->update([
            'matched_payment_id' => $payment->id,
            'matched_at' => now(),
            'is_matched' => true,
        ]);
    }

    /**
     * Scope to get unmatched emails
     */
    public function scopeUnmatched($query)
    {
        return $query->where('is_matched', false);
    }

    /**
     * Scope to get emails within date range
     */
    public function scopeSince($query, $date)
    {
        return $query->where('email_date', '>=', $date);
    }

    /**
     * Scope to get emails matching amount
     */
    public function scopeWithAmount($query, $amount)
    {
        return $query->where('amount', $amount);
    }
}
