<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDisbursementItem extends Model
{
    protected $fillable = [
        'batch_id',
        'business_employee_id',
        'recipient_name',
        'payment_method',
        'phone_e164',
        'bank_code',
        'account_number',
        'amount_ngn',
        'status',
        'idempotency_key',
        'wallet_transaction_id',
        'provider_reference',
        'error_message',
        'due_at',
        'processed_at',
    ];

    protected $casts = [
        'amount_ngn' => 'decimal:2',
        'due_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BusinessDisbursementBatch::class, 'batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(BusinessEmployee::class, 'business_employee_id');
    }
}
