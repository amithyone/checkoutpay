<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'payment_id',
        'amount',
        'type',
        'status',
        'transaction_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    /**
     * Get the business that owns this transaction
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the payment associated with this transaction
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
