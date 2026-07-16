<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalConditionReport extends Model
{
    protected $fillable = [
        'rental_id',
        'submitted_by_renter_id',
        'submitted_by_business_id',
        'phase',
        'notes',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
