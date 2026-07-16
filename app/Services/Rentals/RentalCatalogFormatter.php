<?php

namespace App\Services\Rentals;

use App\Models\Rental;
use App\Models\RentalItem;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Storage;

class RentalCatalogFormatter
{
    public static function itemSummary(RentalItem $item): array
    {
        $item->loadMissing('category');

        $images = is_array($item->images) ? $item->images : [];
        $normalizedImages = array_values(array_filter(array_map(
            fn ($path) => self::publicUrl((string) $path),
            $images
        )));

        $available = (bool) $item->is_active
            && (bool) $item->is_available
            && ! $item->trashed();

        return [
            'id' => $item->id,
            'slug' => $item->slug,
            'name' => $item->name,
            'daily_rate' => (string) ($item->effective_daily_rate ?? $item->daily_rate),
            'category' => $item->category?->slug,
            'images' => $normalizedImages,
            'thumbnail' => $normalizedImages[0] ?? null,
            'available' => $available,
            'is_active' => (bool) $item->is_active,
            'stock' => (int) $item->quantity_available,
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
            'item' => self::itemSummary($item),
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
