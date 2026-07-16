<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\RentalCategory;
use App\Models\RentalItem;
use App\Services\Rentals\RentalCatalogFormatter;
use App\Services\Rentals\RentalFeaturedSliderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    protected const FEATURED_SLIDE_LIMIT = 10;

    /**
     * GET /api/v1/rentals/categories
     */
    public function categories(Request $request)
    {
        $query = RentalCategory::query()
            ->where('is_active', true);

        if ($request->boolean('popular') || $request->input('sort') === 'popular') {
            $query->leftJoin('rental_items', 'rental_categories.id', '=', 'rental_items.category_id')
                ->leftJoin('rental_rental_item', 'rental_items.id', '=', 'rental_rental_item.rental_item_id')
                ->selectRaw('rental_categories.id, rental_categories.name, rental_categories.slug, rental_categories.icon, COUNT(rental_rental_item.rental_id) as rentals_count')
                ->groupBy('rental_categories.id', 'rental_categories.name', 'rental_categories.slug', 'rental_categories.icon')
                ->orderByDesc('rentals_count');
        } else {
            $query->orderBy('sort_order')
                ->orderBy('name')
                ->select(['id', 'name', 'slug', 'icon']);
        }

        if ($request->filled('limit')) {
            $query->limit((int) $request->input('limit'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }

    /**
     * GET /api/v1/rentals/featured
     */
    public function featured(Request $request)
    {
        $limit = min(self::FEATURED_SLIDE_LIMIT, max(1, (int) $request->input('limit', self::FEATURED_SLIDE_LIMIT)));

        $slides = app(RentalFeaturedSliderService::class)->buildSlides($limit);

        return response()->json([
            'success' => true,
            'data' => $slides,
        ]);
    }

    /**
     * GET /api/v1/rentals/items
     */
    public function index(Request $request)
    {
        $query = $this->rentableCatalogQuery();
        $filter = $this->applyCatalogFilters($query, $request);

        if ($request->boolean('featured') || $request->input('featured') === '1') {
            $query->where('is_featured', true);
            $query->orderByRaw('featured_sort IS NULL, featured_sort ASC')->orderBy('id');
        } else {
            $this->applyCatalogSort($query, $request->get('sort', 'featured'));
        }

        $perPage = min(48, max(1, (int) $request->get('per_page', 24)));
        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($items->items())
                ->map(fn (RentalItem $item) => RentalCatalogFormatter::catalogItem($item))
                ->values()
                ->all(),
            'meta' => array_filter([
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
                'filter' => $filter !== [] ? $filter : null,
            ]),
        ]);
    }

    /**
     * GET /api/v1/rentals/items/{slug}
     */
    public function show(string $slug)
    {
        $item = RentalItem::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with(['business', 'category'])
            ->withCount(['rentals as rentals_count' => fn ($q) => $q->where('status', \App\Models\Rental::STATUS_COMPLETED)])
            ->withCount(['publishedReviews as reviews_count'])
            ->withAvg(['publishedReviews as reviews_avg_rating'], 'rating')
            ->firstOrFail();

        $relatedItems = RentalItem::query()
            ->where('category_id', $item->category_id)
            ->where('id', '!=', $item->id)
            ->where('is_active', true)
            ->where('is_available', true)
            ->with(['business', 'category'])
            ->withCount(['rentals as rentals_count' => fn ($q) => $q->where('status', \App\Models\Rental::STATUS_COMPLETED)])
            ->withCount(['publishedReviews as reviews_count'])
            ->withAvg(['publishedReviews as reviews_avg_rating'], 'rating')
            ->limit(4)
            ->get();

        return response()->json([
            'success' => true,
            'data' => RentalCatalogFormatter::catalogItem($item),
            'related' => $relatedItems
                ->map(fn (RentalItem $related) => RentalCatalogFormatter::catalogItem($related))
                ->values()
                ->all(),
        ]);
    }

    /**
     * GET /api/v1/rentals/items/{id}/unavailable-dates?month=YYYY-MM
     */
    public function unavailableDates(Request $request, int $id)
    {
        $item = RentalItem::findOrFail($id);

        $month = $request->get('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::parse($month.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();

        return response()->json([
            'success' => true,
            'data' => [
                'item_id' => $item->id,
                'month' => $month,
                'unavailable_dates' => $item->getUnavailableDatesInRange($start, $end),
            ],
        ]);
    }

    protected function rentableCatalogQuery(): Builder
    {
        return RentalItem::query()
            ->with(['business', 'category'])
            ->withCount(['rentals as rentals_count' => fn ($q) => $q->where('status', \App\Models\Rental::STATUS_COMPLETED)])
            ->withCount(['publishedReviews as reviews_count'])
            ->withAvg(['publishedReviews as reviews_avg_rating'], 'rating')
            ->where('is_active', true)
            ->where('is_available', true)
            ->where('quantity_available', '>', 0);
    }

    protected function applyCatalogFilters(Builder $query, Request $request): array
    {
        $filter = [];

        if ($request->filled('category')) {
            $slug = (string) $request->category;
            $query->whereHas('category', fn ($q) => $q->where('slug', $slug));
            $filter['category'] = $slug;
        }

        $location = $request->input('city', $request->input('location'));
        if (is_string($location) && trim($location) !== '') {
            $location = trim($location);
            $query->where(function ($q) use ($location) {
                $q->where('city', 'like', '%'.$location.'%')
                    ->orWhere('state', 'like', '%'.$location.'%');
            });
            $filter['city'] = $location;
        }

        if ($request->filled('business_id')) {
            $businessId = (int) $request->business_id;
            $query->where('business_id', $businessId);
            $filter['business_id'] = $businessId;
        }

        if ($request->filled('vendor_id')) {
            $vendorId = (int) $request->vendor_id;
            $query->where('business_id', $vendorId);
            $filter['business_id'] = $vendorId;
        }

        if ($request->filled('business_slug')) {
            $businessSlug = Str::slug((string) $request->business_slug);
            $needle = str_replace('-', ' ', strtolower($businessSlug));
            $query->whereHas('business', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%']));
            $filter['business_slug'] = (string) $request->business_slug;
        }

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('business', fn ($bq) => $bq->where('name', 'like', "%{$search}%"));
            });
            $filter['search'] = $search;
        }

        if ($request->boolean('featured') || $request->input('featured') === '1') {
            $filter['featured'] = true;
        }

        return $filter;
    }

    protected function applyCatalogSort(Builder $query, string $sort): void
    {
        switch ($sort) {
            case 'price_low':
                $query->orderBy('daily_rate', 'asc');
                break;
            case 'price_high':
                $query->orderBy('daily_rate', 'desc');
                break;
            case 'newest':
                $query->latest();
                break;
            case 'most_rented':
                $query->orderBy('rentals_count', 'desc')->latest();
                break;
            default:
                $query->orderByRaw('featured_sort IS NULL, featured_sort ASC')
                    ->orderByDesc('is_featured')
                    ->latest();
        }
    }
}
