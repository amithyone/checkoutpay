<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalFavorite extends Model
{
    protected $fillable = [
        'renter_id',
        'rental_item_id',
    ];

    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class, 'rental_item_id')->withTrashed();
    }
}
