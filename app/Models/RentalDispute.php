<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalDispute extends Model
{
    const STATUS_OPEN = 'open';

    const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'rental_id',
        'opened_by_renter_id',
        'opened_by_business_id',
        'reason',
        'description',
        'requested_deposit_capture',
        'status',
        'resolution',
        'capture_amount',
        'resolution_notes',
        'resolved_at',
        'resolved_by_admin_id',
    ];

    protected $casts = [
        'requested_deposit_capture' => 'decimal:2',
        'capture_amount' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
