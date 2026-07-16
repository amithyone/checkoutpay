<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalItemReview extends Model
{
    public const CONDITIONS = ['new', 'good', 'old', 'bad'];

    protected $fillable = [
        'rental_id',
        'rental_item_id',
        'renter_id',
        'rating',
        'condition',
        'missing_items',
        'remarks',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function rentalItem(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class, 'rental_item_id');
    }

    public function renter(): BelongsTo
    {
        return $this->belongsTo(Renter::class);
    }

    public static function normalizeCondition(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, self::CONDITIONS, true) ? $normalized : null;
    }

    public function hasContent(): bool
    {
        return $this->rating !== null
            || $this->condition !== null
            || filled($this->missing_items)
            || filled($this->remarks);
    }
}
