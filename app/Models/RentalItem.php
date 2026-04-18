<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
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
        'caution_fee_enabled',
        'caution_fee_percent',
        'quantity_available',
        'is_available',
        'images',
        'specifications',
        'terms_and_conditions',
        'is_active',
        'is_featured',
        'discount_active',
        'discount_percent',
        'discount_starts_at',
        'discount_ends_at',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'weekly_rate' => 'decimal:2',
        'monthly_rate' => 'decimal:2',
        'caution_fee_enabled' => 'boolean',
        'caution_fee_percent' => 'decimal:2',
        'quantity_available' => 'integer',
        'is_available' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'discount_active' => 'boolean',
        'discount_percent' => 'decimal:2',
        'discount_starts_at' => 'datetime',
        'discount_ends_at' => 'datetime',
        'images' => 'array',
        'specifications' => 'array',
    ];

    /**
     * @var array<int, string>
     */
    protected $appends = [
        'is_on_discount',
        'effective_daily_rate',
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
     * Normalize discount fields from admin / business web forms or API (multipart / JSON).
     */
    public static function discountFieldsFromRequest(Request $request): array
    {
        $percent = null;
        if ($request->filled('discount_percent')) {
            $p = (float) $request->input('discount_percent');
            $percent = $p > 0 ? min(95.0, max(0.0, $p)) : null;
        }

        return [
            'discount_active' => $request->boolean('discount_active'),
            'discount_percent' => $percent,
            'discount_starts_at' => $request->filled('discount_starts_at') ? $request->date('discount_starts_at') : null,
            'discount_ends_at' => $request->filled('discount_ends_at') ? $request->date('discount_ends_at') : null,
        ];
    }

    /**
     * For API PATCH: only set discount keys that appear on the request.
     *
     * @return array<string, mixed>
     */
    public static function discountPatchFromRequest(Request $request): array
    {
        if (! $request->hasAny(['discount_active', 'discount_percent', 'discount_starts_at', 'discount_ends_at'])) {
            return [];
        }

        $out = [];
        if ($request->has('discount_active')) {
            $out['discount_active'] = $request->boolean('discount_active');
        }
        if ($request->exists('discount_percent')) {
            $v = $request->input('discount_percent');
            $out['discount_percent'] = ($v === '' || $v === null)
                ? null
                : min(95.0, max(0.0, (float) $v));
        }
        if ($request->has('discount_starts_at')) {
            $out['discount_starts_at'] = $request->filled('discount_starts_at') ? $request->date('discount_starts_at') : null;
        }
        if ($request->has('discount_ends_at')) {
            $out['discount_ends_at'] = $request->filled('discount_ends_at') ? $request->date('discount_ends_at') : null;
        }

        return $out;
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
     * Whether the configured discount applies right now (business or admin set the same fields).
     */
    public function isDiscountActiveAt(?Carbon $moment = null): bool
    {
        $moment = $moment ?? now();

        if (! $this->discount_active) {
            return false;
        }

        $pct = (float) ($this->discount_percent ?? 0);
        if ($pct <= 0 || $pct >= 100) {
            return false;
        }

        if ($this->discount_starts_at && $moment->lt($this->discount_starts_at->copy()->startOfDay())) {
            return false;
        }

        if ($this->discount_ends_at && $moment->gt($this->discount_ends_at->copy()->endOfDay())) {
            return false;
        }

        return true;
    }

    /**
     * Multiplier applied to list prices when a discount is active (e.g. 0.85 for 15% off).
     */
    public function discountPriceMultiplier(): float
    {
        if (! $this->isDiscountActiveAt()) {
            return 1.0;
        }

        return round((100 - (float) $this->discount_percent) / 100, 4);
    }

    public function getIsOnDiscountAttribute(): bool
    {
        return $this->isDiscountActiveAt();
    }

    /**
     * Per-day amount renters pay when discounted; otherwise equals list daily_rate.
     */
    public function getEffectiveDailyRateAttribute(): string
    {
        $base = (float) $this->daily_rate;
        if (! $this->isDiscountActiveAt()) {
            return number_format($base, 2, '.', '');
        }

        return number_format(round($base * $this->discountPriceMultiplier(), 2), 2, '.', '');
    }

    /**
     * Calculate rate for period
     */
    public function getRateForPeriod(int $days): float
    {
        $mult = $this->discountPriceMultiplier();

        if ($days >= 30 && $this->monthly_rate) {
            return round((float) $this->monthly_rate * $mult, 2);
        }
        if ($days >= 7 && $this->weekly_rate) {
            return round((float) $this->weekly_rate * $mult, 2);
        }

        return round((float) $this->daily_rate * $days * $mult, 2);
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
        // Only block dates for rentals that still occupy inventory.
        // Once returned/completed, the item should be available again.
        ->whereIn('status', [Rental::STATUS_PENDING, Rental::STATUS_APPROVED, Rental::STATUS_ACTIVE])
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

    /**
     * Get dates in range where this item has no availability (fully booked).
     * Returns array of 'Y-m-d' strings.
     */
    public function getUnavailableDatesInRange($rangeStart, $rangeEnd): array
    {
        $start = \Carbon\Carbon::parse($rangeStart)->startOfDay();
        $end = \Carbon\Carbon::parse($rangeEnd)->endOfDay();
        $unavailable = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $dateStr = $d->format('Y-m-d');
            if (!$this->isAvailableForDates($dateStr, $dateStr)) {
                $unavailable[] = $dateStr;
            }
        }
        return $unavailable;
    }
}
