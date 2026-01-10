<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'processed_email_id',
        'transaction_id',
        'match_result',
        'reason',
        'payment_amount',
        'payment_name',
        'payment_account_number',
        'payment_created_at',
        'extracted_amount',
        'extracted_name',
        'extracted_account_number',
        'email_subject',
        'email_from',
        'email_date',
        'amount_diff',
        'name_similarity_percent',
        'time_diff_minutes',
        'extraction_method',
        'details',
        'html_snippet',
        'text_snippet',
        'manual_review_status',
        'manual_review_notes',
        'reviewed_at',
        'reviewed_by',
        'processing_time_ms',
    ];

    protected $casts = [
        'payment_amount' => 'decimal:2',
        'extracted_amount' => 'decimal:2',
        'amount_diff' => 'decimal:2',
        'payment_created_at' => 'datetime',
        'email_date' => 'datetime',
        'reviewed_at' => 'datetime',
        'details' => 'array',
        'processing_time_ms' => 'float',
        'name_similarity_percent' => 'integer',
        'time_diff_minutes' => 'integer',
    ];

    /**
     * Match result constants
     */
    const RESULT_MATCHED = 'matched';
    const RESULT_UNMATCHED = 'unmatched';
    const RESULT_REJECTED = 'rejected';
    const RESULT_PARTIAL = 'partial';

    /**
     * Review status constants
     */
    const REVIEW_PENDING = 'pending';
    const REVIEW_REVIEWED = 'reviewed';
    const REVIEW_CORRECT = 'correct';
    const REVIEW_INCORRECT = 'incorrect';

    /**
     * Get the payment this attempt tried to match
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the processed email this attempt used
     */
    public function processedEmail(): BelongsTo
    {
        return $this->belongsTo(ProcessedEmail::class);
    }

    /**
     * Scope to get matched attempts
     */
    public function scopeMatched($query)
    {
        return $query->where('match_result', self::RESULT_MATCHED);
    }

    /**
     * Scope to get unmatched attempts
     */
    public function scopeUnmatched($query)
    {
        return $query->where('match_result', self::RESULT_UNMATCHED);
    }

    /**
     * Scope to get pending reviews
     */
    public function scopePendingReview($query)
    {
        return $query->where('manual_review_status', self::REVIEW_PENDING);
    }

    /**
     * Scope to get attempts for a transaction
     */
    public function scopeForTransaction($query, string $transactionId)
    {
        return $query->where('transaction_id', $transactionId);
    }

    /**
     * Scope to get attempts with specific reason pattern
     */
    public function scopeWithReason($query, string $pattern)
    {
        return $query->where('reason', 'LIKE', "%{$pattern}%");
    }

    /**
     * Mark as reviewed
     */
    public function markAsReviewed(string $status, ?string $notes = null, ?int $reviewedBy = null): void
    {
        $this->update([
            'manual_review_status' => $status,
            'manual_review_notes' => $notes,
            'reviewed_at' => now(),
            'reviewed_by' => $reviewedBy,
        ]);
    }
}
