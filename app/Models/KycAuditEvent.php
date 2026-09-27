<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycAuditEvent extends Model
{
    protected $fillable = [
        'whatsapp_wallet_id',
        'business_account_application_id',
        'actor_type',
        'actor_id',
        'action',
        'payload',
        'ip',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }
}
