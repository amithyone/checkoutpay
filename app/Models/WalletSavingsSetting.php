<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletSavingsSetting extends Model
{
    public const FREQUENCY_OFF = 'off';

    public const FREQUENCY_WEEKLY = 'weekly';

    public const FREQUENCY_AFTER_SPEND = 'after_spend';

    public const COLLECTION_PER_INCOMING = 'per_incoming';

    public const COLLECTION_BALANCE_THRESHOLD = 'balance_threshold';

    protected $fillable = [
        'whatsapp_wallet_id',
        'spend_to_save_enabled',
        'spend_to_save_percent',
        'strict_save_enabled',
        'strict_save_percent',
        'strict_ledger_scope',
        'strict_collection_mode',
        'strict_balance_threshold',
        'reminder_enabled',
        'reminder_frequency',
        'reminder_weekday',
        'reminder_hour_local',
        'last_reminder_sent_at',
    ];

    protected $casts = [
        'spend_to_save_enabled' => 'boolean',
        'spend_to_save_percent' => 'decimal:2',
        'strict_save_enabled' => 'boolean',
        'strict_save_percent' => 'decimal:2',
        'strict_balance_threshold' => 'decimal:2',
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
