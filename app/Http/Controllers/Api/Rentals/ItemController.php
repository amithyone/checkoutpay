<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\RentalCategory;
use App\Models\RentalItem;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    /**
     * GET /api/v1/rentals/categories
     * Public categories list.
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

        $cats = $query->get();

        return response()->json([
            'data' => $cats,
        ]);
    }

    /**
     * GET /api/v1/rentals/items
     * Public catalog list with filters + pagination.
     */
    public function index(Request $request)
    {
        $query = RentalItem::with(['business', 'category'])
            ->withCount('rentals')
            ->where('is_active', true)
            ->where('is_available', true);

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('business', function ($bq) use ($search) {
                        $bq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $sort = $request->get('sort', 'featured');
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
                $query->orderBy('is_featured', 'desc')->latest();
        }

        $perPage = (int) $request->get('per_page', 24);
        $items = $query->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/rentals/items/{slug}
     * Public item detail.
     */
    public function show(string $slug)
    {
        $item = RentalItem::where('slug', $slug)
            ->where('is_active', true)
            ->with(['business', 'category'])
            ->firstOrFail();

        $relatedItems = RentalItem::where('category_id', $item->category_id)
            ->where('id', '!=', $item->id)
            ->where('is_active', true)
            ->where('is_available', true)
            ->limit(4)
            ->get();

        return response()->json([
            'data' => $item,
            'related' => $relatedItems,
        ]);
    }

    /**
     * GET /api/v1/rentals/items/{id}/unavailable-dates?month=YYYY-MM
     */
    public function unavailableDates(Request $request, int $id)
    {
        $item = RentalItem::findOrFail($id);

        $month = $request->get('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::parse($month . '-01')->startOfDay();
        $end = $start->copy()->endOfMonth();

        $unavailable = $item->getUnavailableDatesInRange($start, $end);

        return response()->json([
            'data' => [
                'item_id' => $item->id,
                'month' => $month,
                'unavailable_dates' => $unavailable,
            ],
        ]);
    }
}

