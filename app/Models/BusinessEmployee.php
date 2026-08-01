<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessEmployee extends Model
{
    public const METHOD_BANK = 'bank';

    public const METHOD_WALLET = 'wallet';

    protected $fillable = [
        'business_id',
        'name',
        'payment_method',
        'phone_e164',
        'bank_code',
        'account_number',
        'account_name',
        'monthly_salary_ngn',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'monthly_salary_ngn' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
