<?php

namespace App\Services\Rentals;

use App\Models\Rental;
use App\Models\RentalFeaturedBanner;
use App\Models\RentalItem;
use App\Models\RentalItemReview;
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
        $averageRating = self::averageRatingFromReviews(
            (float) ($item->reviews_avg_rating ?? $item->average_rating ?? 0),
            (int) ($item->reviews_count ?? 0)
        );

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
            'rating' => $averageRating,
            'average_rating' => $averageRating,
            'reviews_count' => (int) ($item->reviews_count ?? 0),
            'how_to_videos' => self::howToVideos($item),
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
            'type' => 'item',
            'item' => array_merge(self::catalogItem($item), [
                'featured_tag' => $tag,
            ]),
            'banner' => null,
        ];
    }

    public static function bannerSlide(RentalFeaturedBanner $banner): array
    {
        $banner->loadMissing(['rentalItem' => fn ($q) => $q->with(['business', 'category'])]);

        $imageUrl = self::publicUrl($banner->image);
        $linkedItem = $banner->rentalItem;

        return [
            'id' => 'banner-'.$banner->id,
            'sort_order' => (int) $banner->sort_order,
            'tag' => $banner->tag ?: 'Sponsored',
            'type' => 'banner',
            'item' => null,
            'banner' => [
                'id' => $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'image' => $imageUrl,
                'link_url' => $banner->link_url,
                'item_id' => $banner->rental_item_id,
                'item_slug' => $linkedItem?->slug,
                'item' => $linkedItem ? self::catalogItem($linkedItem) : null,
            ],
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
        return self::averageRatingFromReviews(
            (float) ($item->reviews_avg_rating ?? 0),
            (int) ($item->reviews_count ?? 0)
        );
    }

    public static function averageRatingFromReviews(float $avg, int $count): ?float
    {
        if ($count <= 0 || $avg <= 0) {
            return null;
        }

        return round(min(5.0, max(1.0, $avg)), 1);
    }

    /**
     * @return array<int, array{title: string, url: string}>
     */
    public static function howToVideos(RentalItem $item): array
    {
        return RentalItem::normalizeHowToVideos($item->how_to_videos ?? []);
    }

    public static function reviewEntry(RentalItemReview $review): array
    {
        $review->loadMissing('renter:id,name');

        $condition = $review->condition;
        $conditionLabel = $condition !== null ? ucfirst($condition) : null;

        return [
            'id' => $review->id,
            'item_id' => $review->rental_item_id,
            'rental_id' => $review->rental_id,
            'rating' => $review->rating,
            'condition' => $conditionLabel,
            'missing_items' => $review->missing_items,
            'remarks' => $review->remarks,
            'created_at' => $review->created_at?->toIso8601String(),
            'renter' => [
                'display_name' => self::reviewerDisplayName($review->renter?->name),
            ],
        ];
    }

    protected static function reviewerDisplayName(?string $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'Verified renter';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        if (count($parts) === 1) {
            return mb_substr($parts[0], 0, 1).'.';
        }

        return mb_substr($parts[0], 0, 1).'. '.mb_substr($parts[count($parts) - 1], 0, 1).'.';
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
