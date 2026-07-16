<?php

namespace App\Services\Rentals;

use App\Models\Rental;
use App\Models\RentalItem;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class RentalCatalogFormatter
{
    public static function catalogItem(RentalItem $item): array
    {
        $item->loadMissing(['category', 'business']);

        $images = is_array($item->images) ? $item->images : [];
        $normalizedImages = array_values(array_filter(array_map(
            fn ($path) => self::publicUrl((string) $path),
            $images
        )));

        $business = $item->business;
        $category = $item->category;

        return [
            'id' => $item->id,
            'slug' => $item->slug,
            'name' => $item->name,
            'brand' => $item->brand,
            'description' => $item->description,
            'city' => $item->city,
            'state' => $item->state,
            'location' => self::locationLabel($item),
            'daily_rate' => (string) ($item->effective_daily_rate ?? $item->daily_rate),
            'weekly_rate' => $item->weekly_rate !== null ? (string) $item->weekly_rate : null,
            'monthly_rate' => $item->monthly_rate !== null ? (string) $item->monthly_rate : null,
            'currency' => $item->currency ?? 'NGN',
            'rating' => self::itemRating($item),
            'images' => $normalizedImages,
            'thumbnail' => $normalizedImages[0] ?? null,
            'is_featured' => (bool) $item->is_featured,
            'featured_tag' => $item->featured_tag,
            'featured_sort' => $item->featured_sort,
            'is_on_discount' => $item->is_on_discount ?? $item->isDiscountActiveAt(),
            'effective_daily_rate' => (string) ($item->effective_daily_rate ?? $item->daily_rate),
            'quantity_available' => (int) $item->quantity_available,
            'stock' => (int) $item->quantity_available,
            'is_active' => (bool) $item->is_active,
            'is_available' => (bool) $item->is_available,
            'available' => (bool) $item->is_active
                && (bool) $item->is_available
                && ! $item->trashed()
                && (int) $item->quantity_available > 0,
            'category' => $category ? [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->name,
            ] : null,
            'business' => $business ? [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => self::businessSlug($business),
            ] : null,
        ];
    }

    public static function featuredSlide(RentalItem $item): array
    {
        $tag = $item->featured_tag ?: 'Featured';

        return [
            'id' => $item->id,
            'sort_order' => (int) ($item->featured_sort ?? $item->id),
            'tag' => $tag,
            'item' => array_merge(self::catalogItem($item), [
                'featured_tag' => $tag,
            ]),
        ];
    }

    public static function itemSummary(RentalItem $item): array
    {
        $catalog = self::catalogItem($item);

        return [
            'id' => $catalog['id'],
            'slug' => $catalog['slug'],
            'name' => $catalog['name'],
            'daily_rate' => $catalog['daily_rate'],
            'category' => $catalog['category']['slug'] ?? null,
            'images' => $catalog['images'],
            'thumbnail' => $catalog['thumbnail'],
            'available' => $catalog['available'],
            'is_active' => $catalog['is_active'],
            'stock' => $catalog['stock'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function rentalDateRange(Rental $rental): array
    {
        if (! $rental->start_date || ! $rental->end_date) {
            return [];
        }

        $start = Carbon::parse($rental->start_date)->startOfDay();
        $end = Carbon::parse($rental->end_date)->startOfDay();

        if ($end->lt($start)) {
            return [];
        }

        return array_map(
            fn (Carbon $d) => $d->format('Y-m-d'),
            iterator_to_array(CarbonPeriod::create($start, $end))
        );
    }

    public static function rentalLineItem(Rental $rental, RentalItem $item): array
    {
        $quantity = (int) ($item->pivot->quantity ?? 1);

        return [
            'id' => $item->id,
            'name' => $item->name,
            'quantity' => $quantity,
            'selected_dates' => self::rentalDateRange($rental),
            'unit_rate' => (string) ($item->pivot->unit_rate ?? $item->daily_rate),
            'total_amount' => (string) ($item->pivot->total_amount ?? '0'),
            'item' => self::catalogItem($item),
        ];
    }

    public static function unavailableReason(RentalItem $item): ?string
    {
        if ($item->trashed()) {
            return 'deleted';
        }
        if (! $item->is_active) {
            return 'inactive';
        }
        if (! $item->is_available || (int) $item->quantity_available < 1) {
            return 'out_of_stock';
        }

        return null;
    }

    public static function itemRating(RentalItem $item): ?float
    {
        $count = (int) ($item->rentals_count ?? $item->rentals()
            ->where('status', Rental::STATUS_COMPLETED)
            ->count());

        if ($count <= 0) {
            return null;
        }

        return min(5.0, round(3.5 + ($count * 0.15), 1));
    }

    public static function locationLabel(RentalItem $item): ?string
    {
        $city = trim((string) ($item->city ?? ''));
        $state = trim((string) ($item->state ?? ''));

        if ($city !== '' && $state !== '') {
            return $city.', '.$state;
        }

        return $city !== '' ? $city : ($state !== '' ? $state : null);
    }

    public static function businessSlug(object $business): string
    {
        $name = trim((string) ($business->name ?? ''));
        if ($name !== '') {
            return Str::slug($name);
        }

        return 'vendor-'.(int) $business->id;
    }

    protected static function publicUrl(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }
}
