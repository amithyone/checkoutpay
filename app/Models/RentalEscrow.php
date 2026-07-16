<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalEscrow extends Model
{
    const STATUS_HELD = 'held';

    const STATUS_PARTIALLY_RELEASED = 'partially_released';

    const STATUS_RELEASED = 'released';

    const STATUS_REFUNDED = 'refunded';

    const STATUS_FROZEN = 'frozen';

    protected $fillable = [
        'rental_id',
        'status',
        'rent_held',
        'deposit_held',
        'rent_released',
        'deposit_released',
        'rent_released_at',
        'deposit_released_at',
    ];

    protected $casts = [
        'rent_held' => 'decimal:2',
        'deposit_held' => 'decimal:2',
        'rent_released' => 'decimal:2',
        'deposit_released' => 'decimal:2',
        'rent_released_at' => 'datetime',
        'deposit_released_at' => 'datetime',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function toApiArray(): array
    {
        return [
            'status' => $this->status,
            'rent_held' => (float) $this->rent_held,
            'deposit_held' => (float) $this->deposit_held,
            'rent_released_at' => $this->rent_released_at?->toIso8601String(),
            'deposit_released_at' => $this->deposit_released_at?->toIso8601String(),
        ];
    }
}
