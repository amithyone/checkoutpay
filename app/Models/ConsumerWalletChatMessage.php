<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumerWalletChatMessage extends Model
{
    public const SENDER_USER = 'user';

    public const SENDER_SUPPORT = 'support';

    protected $fillable = [
        'whatsapp_wallet_id',
        'sender',
        'body',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
