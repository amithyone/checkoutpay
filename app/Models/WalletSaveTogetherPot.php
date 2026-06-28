<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletSaveTogetherPot extends Model
{
    public const STATUS_COLLECTING = 'collecting';

    public const STATUS_UNLOCKED = 'unlocked';

    public const STATUS_CLOSED = 'closed';

    public const MODE_FULL_CONTRIBUTION = 'full_contribution';

    public const MODE_TIME_DEADLINE = 'time_deadline';

    protected $fillable = [
        'public_id',
        'creator_wallet_id',
        'title',
        'target_amount',
        'per_member_share',
        'total_contributed',
        'completion_mode',
        'deadline_at',
        'status',
        'unlocked_at',
        'closed_at',
        'currency',
        'meta',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'per_member_share' => 'decimal:2',
        'total_contributed' => 'decimal:2',
        'deadline_at' => 'datetime',
        'unlocked_at' => 'datetime',
        'closed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function creatorWallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'creator_wallet_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WalletSaveTogetherMember::class, 'pot_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(WalletSaveTogetherContribution::class, 'pot_id');
    }

    public function isCollecting(): bool
    {
        return $this->status === self::STATUS_COLLECTING;
    }

    public function isUnlocked(): bool
    {
        return $this->status === self::STATUS_UNLOCKED;
    }
}
