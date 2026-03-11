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
        'description_field', // The 43-digit description field value
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

    /**
     * Scope to only include emails whose from_email is whitelisted.
     * Non-whitelisted emails are excluded from inbox and from match/unmatched consideration.
     */
    public function scopeFromWhitelisted($query)
    {
        $entries = WhitelistedEmailAddress::where('is_active', true)->get();
        if ($entries->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }
        return $query->where(function ($q) use ($entries) {
            foreach ($entries as $entry) {
                $email = strtolower(trim($entry->email));
                if (str_starts_with($email, '@')) {
                    $domain = substr($email, 1);
                    $q->orWhereRaw('LOWER(TRIM(COALESCE(from_email, ""))) LIKE ?', ['%@' . $domain]);
                } else {
                    $q->orWhereRaw('LOWER(TRIM(COALESCE(from_email, ""))) = ?', [$email])
                        ->orWhereRaw('LOWER(TRIM(COALESCE(from_email, ""))) LIKE ?', ['%' . $email . '%']);
                }
            }
        });
    }
}
