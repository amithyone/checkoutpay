<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZapierLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'payload',
        'sender_name',
        'amount',
        'time_sent',
        'email_content',
        'extracted_from_email',
        'status',
        'status_message',
        'processed_email_id',
        'payment_id',
        'ip_address',
        'user_agent',
        'error_details',
    ];

    protected $casts = [
        'payload' => 'array',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the processed email associated with this log
     */
    public function processedEmail(): BelongsTo
    {
        return $this->belongsTo(ProcessedEmail::class);
    }

    /**
     * Get the payment associated with this log
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Scope to get logs by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get recent logs
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
