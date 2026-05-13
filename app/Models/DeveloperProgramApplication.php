<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperProgramApplication extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name',
        'business_id',
        'phone',
        'email',
        'whatsapp',
        'community_preference',
        'status',
        'partner_fee_share_percent',
        'admin_notes',
        'approved_at',
    ];

    protected $casts = [
        'partner_fee_share_percent' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function effectiveFeeSharePercent(?float $globalPercent): ?float
    {
        if ($this->partner_fee_share_percent !== null) {
            return (float) $this->partner_fee_share_percent;
        }

        return $globalPercent !== null ? (float) $globalPercent : null;
    }
}
