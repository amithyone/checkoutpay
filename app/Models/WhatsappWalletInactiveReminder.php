<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappWalletInactiveReminder extends Model
{
    public const SLOT_MORNING = 'morning';

    public const SLOT_EVENING = 'evening';

    protected $fillable = [
        'whatsapp_wallet_id',
        'reminder_on',
        'slot',
        'push_sent',
        'whatsapp_sent',
    ];

    protected $casts = [
        'reminder_on' => 'date',
        'push_sent' => 'boolean',
        'whatsapp_sent' => 'boolean',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }
}
