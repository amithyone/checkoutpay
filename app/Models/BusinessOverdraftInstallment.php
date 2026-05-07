<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessOverdraftInstallment extends Model
{
    protected $fillable = [
        'business_id',
        'sequence',
        'due_at',
        'amount_due',
        'amount_paid',
        'status',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
