<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLinkPayment extends Model
{
    protected $fillable = [
        'payment_link_id',
        'payment_id',
        'amount',
        'counted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'counted_at' => 'datetime',
    ];

    public function paymentLink(): BelongsTo
    {
        return $this->belongsTo(PaymentLink::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
