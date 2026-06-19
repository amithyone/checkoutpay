<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletSavingsLock extends Model
{
    public const SOURCE_SPEND_TO_SAVE = 'spend_to_save';

    public const SOURCE_INCOMING = 'incoming';

    public const SOURCE_BALANCE_THRESHOLD = 'balance_threshold';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_GOAL = 'goal';

    public const LOCK_TYPE_LOCKED = 'locked';

    public const LOCK_TYPE_FLEXIBLE = 'flexible';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_MATURED = 'matured';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'whatsapp_wallet_id',
        'wallet_savings_goal_id',
        'source_transaction_id',
        'source',
        'lock_type',
        'ledger_scope',
        'amount',
        'interest_rate_percent',
        'interest_amount',
        'locked_at',
        'matures_at',
        'matured_at',
        'status',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'interest_rate_percent' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'locked_at' => 'datetime',
        'matures_at' => 'datetime',
        'matured_at' => 'datetime',
        'meta' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'whatsapp_wallet_id');
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(WalletSavingsGoal::class, 'wallet_savings_goal_id');
    }

    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(WhatsappWalletTransaction::class, 'source_transaction_id');
    }
}
