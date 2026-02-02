<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    /**
     * Display public memberships catalog
     */
    public function index(Request $request): View
    {
        $query = Membership::with(['business', 'category'])
            ->where('is_active', true);

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filter by city
        if ($request->filled('city')) {
            $query->where(function ($q) use ($request) {
                $q->where('is_global', true)
                  ->orWhere('city', 'like', '%' . $request->city . '%');
            });
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('who_is_it_for', 'like', "%{$search}%")
                  ->orWhereHas('business', function ($bq) use ($search) {
                      $bq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Sort
        $sort = $request->get('sort', 'featured');
        switch ($sort) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'featured':
            default:
                $query->orderBy('is_featured', 'desc')->orderBy('created_at', 'desc');
                break;
        }

        $memberships = $query->paginate(12);
        $categories = MembershipCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Get cities from config (same as rentals)
        $cities = config('cities.major_cities', []);

        return view('memberships.index', compact('memberships', 'categories', 'cities'));
    }

    /**
     * Display a single membership
     */
    public function show(string $slug): View
    {
        $membership = Membership::with(['business', 'category'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        // Get related memberships from same business
        $relatedMemberships = Membership::where('business_id', $membership->business_id)
            ->where('id', '!=', $membership->id)
            ->where('is_active', true)
            ->limit(4)
            ->get();

        return view('memberships.show', compact('membership', 'relatedMemberships'));
    }
}
