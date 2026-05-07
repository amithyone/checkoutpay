<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessLoan extends Model
{
    public const STATUS_PENDING_ADMIN = 'pending_admin';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REPAID = 'repaid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_DEFAULTED = 'defaulted';

    protected $fillable = [
        'business_lending_offer_id',
        'borrower_business_id',
        'principal',
        'total_repayment',
        'status',
        'disbursed_at',
        'repaid_at',
        'borrower_message',
    ];

    protected $casts = [
        'principal' => 'decimal:2',
        'total_repayment' => 'decimal:2',
        'disbursed_at' => 'datetime',
        'repaid_at' => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(BusinessLendingOffer::class, 'business_lending_offer_id');
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'borrower_business_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(BusinessLoanSchedule::class)->orderBy('sequence');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(BusinessLoanLedgerEntry::class);
    }
}
