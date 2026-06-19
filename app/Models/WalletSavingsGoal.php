<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletSavingsGoal extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ARCHIVED = 'archived';

    public const SAVE_TYPE_FLEXIBLE = 'flexible';

    public const SAVE_TYPE_STRICT = 'strict';

    public const COLLECTION_MANUAL = 'manual';

    public const COLLECTION_PER_INCOMING = 'per_incoming';

    public const COLLECTION_BALANCE_THRESHOLD = 'balance_threshold';

    public const LEDGER_PERSONAL = 'personal';

    public const LEDGER_BUSINESS = 'business';

    public const LEDGER_BOTH = 'both';

    protected $fillable = [
        'whatsapp_wallet_id',
        'name',
        'target_amount',
        'saved_amount',
        'status',
        'save_type',
        'target_date',
        'duration_days',
        'collection_mode',
        'auto_save_percent',
        'balance_threshold',
        'ledger_scope',
        'auto_save_enabled',
        'soft_lock_until',
        'completion_bonus_percent',
        'completion_bonus_paid',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'saved_amount' => 'decimal:2',
        'auto_save_percent' => 'decimal:2',
        'balance_threshold' => 'decimal:2',
        'completion_bonus_percent' => 'decimal:2',
        'target_date' => 'date',
        'auto_save_enabled' => 'boolean',
        'completion_bonus_paid' => 'boolean',
        'soft_lock_until' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function locks(): HasMany
    {
        return $this->hasMany(WalletSavingsLock::class, 'wallet_savings_goal_id');
    }

    public function isStrict(): bool
    {
        return $this->save_type === self::SAVE_TYPE_STRICT;
    }

    public function isFlexible(): bool
    {
        return $this->save_type === self::SAVE_TYPE_FLEXIBLE;
    }
}
