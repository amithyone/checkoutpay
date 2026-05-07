<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessLoanSchedule extends Model
{
    protected $fillable = [
        'business_loan_id',
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

    public function loan(): BelongsTo
    {
        return $this->belongsTo(BusinessLoan::class, 'business_loan_id');
    }

    public function remaining(): float
    {
        return max(0, (float) $this->amount_due - (float) $this->amount_paid);
    }
}
