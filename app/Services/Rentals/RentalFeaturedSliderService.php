<?php

namespace App\Services\Rentals;

use App\Models\RentalFeaturedBanner;
use App\Models\RentalItem;

class RentalFeaturedSliderService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildSlides(int $limit = 10, bool $includeInactiveItems = false): array
    {
        $banners = RentalFeaturedBanner::query()
            ->activeNow()
            ->with(['rentalItem' => fn ($q) => $q->with(['business', 'category'])])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $itemQuery = RentalItem::query()
            ->with(['business', 'category'])
            ->withCount(['rentals as rentals_count' => fn ($q) => $q->where('status', \App\Models\Rental::STATUS_COMPLETED)])
            ->where('is_featured', true)
            ->orderByRaw('featured_sort IS NULL, featured_sort ASC')
            ->orderBy('id');

        if ($includeInactiveItems) {
            $items = $itemQuery->limit($limit)->get();
        } else {
            $items = $itemQuery
                ->where('is_active', true)
                ->where('is_available', true)
                ->where('quantity_available', '>', 0)
                ->limit($limit)
                ->get();
        }

        return collect()
            ->merge($banners->map(fn (RentalFeaturedBanner $banner) => RentalCatalogFormatter::bannerSlide($banner)))
            ->merge($items->map(fn (RentalItem $item) => RentalCatalogFormatter::featuredSlide($item)))
            ->sortBy(fn (array $slide) => (int) ($slide['sort_order'] ?? 9999))
            ->values()
            ->take($limit)
            ->all();
    }
}
