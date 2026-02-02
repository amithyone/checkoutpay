<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RentalItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_id',
        'category_id',
        'name',
        'slug',
        'description',
        'city',
        'state',
        'address',
        'daily_rate',
        'weekly_rate',
        'monthly_rate',
        'currency',
        'quantity_available',
        'is_available',
        'images',
        'specifications',
        'terms_and_conditions',
        'is_active',
        'is_featured',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'weekly_rate' => 'decimal:2',
        'monthly_rate' => 'decimal:2',
        'quantity_available' => 'integer',
        'is_available' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'images' => 'array',
        'specifications' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (empty($item->slug)) {
                $item->slug = Str::slug($item->name) . '-' . Str::random(6);
            }
        });
    }

    /**
     * Get the business that owns this item
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the category
     */
    public function category()
    {
        return $this->belongsTo(RentalCategory::class, 'category_id');
    }

    /**
     * Get rentals for this item
     */
    public function rentals()
    {
        return $this->belongsToMany(Rental::class, 'rental_rental_item')
            ->withPivot('quantity', 'unit_rate', 'total_amount')
            ->withTimestamps();
    }

    /**
     * Calculate rate for period
     */
    public function getRateForPeriod(int $days): float
    {
        if ($days >= 30 && $this->monthly_rate) {
            return $this->monthly_rate;
        } elseif ($days >= 7 && $this->weekly_rate) {
            return $this->weekly_rate;
        }
        return $this->daily_rate * $days;
    }

    /**
     * Check if item is available for dates
     */
    public function isAvailableForDates($startDate, $endDate): bool
    {
        if (!$this->is_available || !$this->is_active) {
            return false;
        }

        // Check if there are conflicting rentals
        $conflictingRentals = Rental::whereHas('items', function ($q) {
            $q->where('rental_items.id', $this->id);
        })
        ->where('status', '!=', 'cancelled')
        ->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_date', '<=', $startDate)
                     ->where('end_date', '>=', $endDate);
              });
        })
        ->get();

        // Calculate total quantity rented during this period
        $totalRented = 0;
        foreach ($conflictingRentals as $rental) {
            $pivot = $rental->items()->where('rental_items.id', $this->id)->first();
            if ($pivot) {
                $totalRented += $pivot->pivot->quantity;
            }
        }

        return ($this->quantity_available - $totalRented) > 0;
    }
}
