<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\RentalCategory;
use App\Models\RentalItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RentalController extends Controller
{
    /**
     * Display public rentals catalog
     */
    public function index(Request $request): View
    {
        $query = RentalItem::with(['business', 'category'])
            ->withCount('rentals')
            ->where('is_active', true)
            ->where('is_available', true);

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filter by city
        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        // Search
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

        // Sort
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

        $items = $query->paginate(24);
        $categories = RentalCategory::where('is_active', true)->orderBy('sort_order')->get();
        
        // Use predefined major cities in Nigeria
        $cities = config('cities.major_cities', []);

        return view('rentals.index', compact('items', 'categories', 'cities'));
    }

    /**
     * Show single rental item
     */
    public function show(string $slug): View
    {
        $item = RentalItem::where('slug', $slug)
            ->where('is_active', true)
            ->with(['business', 'category'])
            ->firstOrFail();

        // Get related items from same category
        $relatedItems = RentalItem::where('category_id', $item->category_id)
            ->where('id', '!=', $item->id)
            ->where('is_active', true)
            ->where('is_available', true)
            ->limit(4)
            ->get();

        return view('rentals.show', compact('item', 'relatedItems'));
    }
}
