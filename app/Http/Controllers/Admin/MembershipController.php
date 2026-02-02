<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipCategory;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class MembershipController extends Controller
{
    /**
     * Display a listing of memberships
     */
    public function index(Request $request): View
    {
        $query = Membership::with(['business', 'category'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Filter by business
        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
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

        $memberships = $query->paginate(30);

        $stats = [
            'total' => Membership::count(),
            'active' => Membership::where('is_active', true)->count(),
            'featured' => Membership::where('is_featured', true)->count(),
        ];

        $businesses = Business::orderBy('name')->get();
        $categories = MembershipCategory::where('is_active', true)->get();

        return view('admin.memberships.index', compact('memberships', 'stats', 'businesses', 'categories'));
    }

    /**
     * Display the specified membership
     */
    public function show(Membership $membership): View
    {
        $membership->load(['business', 'category']);
        return view('admin.memberships.show', compact('membership'));
    }

    /**
     * Update membership status
     */
    public function updateStatus(Request $request, Membership $membership): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        $membership->update($validated);

        return redirect()->route('admin.memberships.show', $membership)
            ->with('success', 'Membership status updated successfully.');
    }

    /**
     * Remove the specified membership
     */
    public function destroy(Membership $membership): RedirectResponse
    {
        $membership->delete();

        return redirect()->route('admin.memberships.index')
            ->with('success', 'Membership deleted successfully.');
    }
}
