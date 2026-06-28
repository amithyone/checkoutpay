<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletSaveTogetherMember extends Model
{
    public const ROLE_CREATOR = 'creator';

    public const ROLE_MEMBER = 'member';

    public const STATUS_INVITED = 'invited';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED_SHARE = 'completed_share';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_LEFT = 'left';

    protected $fillable = [
        'pot_id',
        'wallet_id',
        'phone_e164',
        'display_name',
        'role',
        'share_target',
        'contributed_amount',
        'withdrawn_amount',
        'status',
        'invited_at',
        'first_contributed_at',
        'share_completed_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'share_target' => 'decimal:2',
        'contributed_amount' => 'decimal:2',
        'withdrawn_amount' => 'decimal:2',
        'invited_at' => 'datetime',
        'first_contributed_at' => 'datetime',
        'share_completed_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function pot(): BelongsTo
    {
        return $this->belongsTo(WalletSaveTogetherPot::class, 'pot_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(WhatsappWallet::class, 'wallet_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(WalletSaveTogetherContribution::class, 'member_id');
    }

    public function remainingShare(): float
    {
        return max(0.0, round((float) $this->share_target - (float) $this->contributed_amount, 2));
    }

    public function refundableAmount(): float
    {
        return max(0.0, round((float) $this->contributed_amount - (float) $this->withdrawn_amount, 2));
    }

    public function countsTowardUnlock(): bool
    {
        return ! in_array($this->status, [self::STATUS_DECLINED, self::STATUS_LEFT], true);
    }

    public function hasCompletedShare(): bool
    {
        return (float) $this->contributed_amount + 0.0001 >= (float) $this->share_target;
    }
}
