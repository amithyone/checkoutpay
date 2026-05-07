<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessLoanLedgerEntry extends Model
{
    public const TYPE_DISBURSEMENT = 'disbursement';

    public const TYPE_COLLECTION = 'collection';

    protected $fillable = [
        'business_loan_id',
        'entry_type',
        'amount',
        'from_business_id',
        'to_business_id',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(BusinessLoan::class, 'business_loan_id');
    }
}
