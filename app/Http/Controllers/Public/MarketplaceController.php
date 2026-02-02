<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipCategory;
use App\Models\RentalItem;
use App\Models\RentalCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    /**
     * Display marketplace with all memberships and rentals
     */
    public function index(Request $request): View
    {
        $type = $request->get('type', 'all'); // all, memberships, rentals

        // Memberships query
        $membershipsQuery = Membership::with(['business', 'category'])
            ->where('is_active', true);

        // Rentals query
        $rentalsQuery = RentalItem::with(['business', 'category'])
            ->where('is_active', true)
            ->where('is_available', true);

        // Filter by category
        if ($request->filled('category')) {
            $categorySlug = $request->category;
            $membershipsQuery->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
            $rentalsQuery->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // Filter memberships by city
        if ($request->filled('city') && ($type === 'all' || $type === 'memberships')) {
            $city = $request->city;
            $membershipsQuery->where(function ($q) use ($city) {
                $q->where('is_global', true)
                  ->orWhere('city', 'like', '%' . $city . '%');
            });
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $membershipsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('business', function ($bq) use ($search) {
                      $bq->where('name', 'like', "%{$search}%");
                  });
            });
            $rentalsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('business', function ($bq) use ($search) {
                      $bq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Get results based on type
        $memberships = [];
        $rentals = [];

        if ($type === 'all' || $type === 'memberships') {
            $memberships = $membershipsQuery->orderBy('is_featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(12)
                ->get();
        }

        if ($type === 'all' || $type === 'rentals') {
            $rentals = $rentalsQuery->orderBy('is_featured', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(12)
                ->get();
        }

        // Get categories
        $membershipCategories = MembershipCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        
        $rentalCategories = RentalCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Cities for rentals
        $cities = config('cities.major_cities', []);

        return view('marketplace.index', compact(
            'memberships',
            'rentals',
            'membershipCategories',
            'rentalCategories',
            'cities',
            'type'
        ));
    }
}
