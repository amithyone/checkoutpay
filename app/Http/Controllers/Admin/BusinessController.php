<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BusinessController extends Controller
{
    /**
     * Display a listing of businesses
     */
    public function index(Request $request)
    {
        $query = Business::withCount('verifications')
            ->with('verifications');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('website', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Website status filter
        if ($request->filled('website_status')) {
            if ($request->website_status === 'approved') {
                $query->where('website_approved', true);
            } elseif ($request->website_status === 'pending') {
                $query->where('website_approved', false)->whereNotNull('website');
            }
        }

        // KYC status filter
        if ($request->filled('kyc_status')) {
            if ($request->kyc_status === 'verified') {
                $query->whereHas('verifications', function ($q) {
                    $q->where('status', 'approved');
                });
            } elseif ($request->kyc_status === 'pending') {
                $query->whereHas('verifications', function ($q) {
                    $q->whereIn('status', ['pending', 'under_review']);
                });
            } elseif ($request->kyc_status === 'rejected') {
                $query->whereHas('verifications', function ($q) {
                    $q->where('status', 'rejected');
                });
            } elseif ($request->kyc_status === 'none') {
                $query->doesntHave('verifications');
            }
        }

        $businesses = $query->latest()->paginate(15);

        return view('admin.businesses.index', compact('businesses'));
    }

    /**
     * Show the form for creating a new business
     */
    public function create()
    {
        return view('admin.businesses.create');
    }

    /**
     * Store a newly created business
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email',
            'password' => 'required|string|min:8',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

        try {
            $business = Business::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'website' => $validated['website'] ?? null,
                'is_active' => $request->has('is_active') && $request->input('is_active') == '1',
            ]);

            return redirect()->route('admin.businesses.index')
                ->with('success', 'Business created successfully!');
        } catch (\Exception $e) {
            Log::error('Error creating business', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Failed to create business: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified business
     */
    public function show(Business $business)
    {
        $business->load('verifications', 'payments', 'withdrawalRequests', 'accountNumbers');
        return view('admin.businesses.show', compact('business'));
    }

    /**
     * Show the form for editing the specified business
     */
    public function edit(Business $business)
    {
        return view('admin.businesses.edit', compact('business'));
    }

    /**
     * Update the specified business
     */
    public function update(Request $request, Business $business)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email,' . $business->id,
            'password' => 'nullable|string|min:8',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
            'website_approved' => 'boolean',
        ]);

        // Only update password if provided
        if (!empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Handle checkbox values
        $validated['is_active'] = $request->has('is_active') && $request->input('is_active') == '1';
        $validated['website_approved'] = $request->has('website_approved') && $request->input('website_approved') == '1';

        try {
            $business->update($validated);
            return redirect()->route('admin.businesses.index')
                ->with('success', 'Business updated successfully!');
        } catch (\Exception $e) {
            Log::error('Error updating business', [
                'error' => $e->getMessage(),
                'business_id' => $business->id,
            ]);
            return back()->withInput()->with('error', 'Failed to update business: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified business
     */
    public function destroy(Business $business)
    {
        try {
            // Check if business has payments or withdrawals
            if ($business->payments()->count() > 0 || $business->withdrawalRequests()->count() > 0) {
                return back()->with('error', 'Cannot delete business that has payments or withdrawals.');
            }

            $business->delete();
            return redirect()->route('admin.businesses.index')
                ->with('success', 'Business deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Error deleting business', [
                'error' => $e->getMessage(),
                'business_id' => $business->id,
            ]);
            return back()->with('error', 'Failed to delete business: ' . $e->getMessage());
        }
    }
}
