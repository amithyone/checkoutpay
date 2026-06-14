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
        'business_loan_ledger_entry_id',
        'counterparty_business_id',
        'amount',
        'type',
        'status',
        'reference',
        'description',
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

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'counterparty_business_id');
    }

    public function loanLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(BusinessLoanLedgerEntry::class, 'business_loan_ledger_entry_id');
    }

    public function isLoanRepaymentOut(): bool
    {
        return $this->type === \App\Services\Business\BusinessLoanTransactionService::TYPE_REPAYMENT_OUT;
    }

    public function isLoanRepaymentIn(): bool
    {
        return $this->type === \App\Services\Business\BusinessLoanTransactionService::TYPE_REPAYMENT_IN;
    }
}
