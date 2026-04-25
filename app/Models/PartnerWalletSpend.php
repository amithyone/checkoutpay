<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerWalletSpend extends Model
{
    protected $fillable = [
        'business_id',
        'idempotency_key',
        'phone_e164',
        'amount',
        'status',
        'payment_id',
        'whatsapp_wallet_transaction_id',
        'response_payload',
    ];

    /** @var array<string, string> Laravel 10 uses $casts; {@see casts()} is Laravel 11+. */
    protected $casts = [
        'amount' => 'decimal:2',
        'response_payload' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
