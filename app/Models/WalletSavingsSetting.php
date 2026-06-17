<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletSavingsSetting extends Model
{
    public const FREQUENCY_OFF = 'off';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_AFTER_SPEND = 'after_spend';

    protected $fillable = [
        'whatsapp_wallet_id',
        'spend_to_save_enabled',
        'spend_to_save_percent',
        'reminder_enabled',
        'reminder_frequency',
        'reminder_weekday',
        'reminder_hour_local',
        'last_reminder_sent_at',
    ];

    protected $casts = [
        'spend_to_save_enabled' => 'boolean',
        'spend_to_save_percent' => 'decimal:2',
        'reminder_enabled' => 'boolean',
        'reminder_weekday' => 'integer',
        'reminder_hour_local' => 'integer',
        'last_reminder_sent_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }
}
