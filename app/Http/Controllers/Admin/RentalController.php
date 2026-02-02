<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class RentalController extends Controller
{
    /**
     * Display a listing of rentals
     */
    public function index(Request $request): View
    {
        $query = Rental::with(['renter', 'business', 'items'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by business
        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('rental_number', 'like', "%{$search}%")
                  ->orWhere('renter_name', 'like', "%{$search}%")
                  ->orWhere('renter_email', 'like', "%{$search}%")
                  ->orWhereHas('business', function ($bq) use ($search) {
                      $bq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $rentals = $query->paginate(30);

        $stats = [
            'total' => Rental::count(),
            'pending' => Rental::where('status', 'pending')->count(),
            'approved' => Rental::where('status', 'approved')->count(),
            'active' => Rental::where('status', 'active')->count(),
            'completed' => Rental::where('status', 'completed')->count(),
            'cancelled' => Rental::where('status', 'cancelled')->count(),
            'rejected' => Rental::where('status', 'rejected')->count(),
            'total_revenue' => Rental::where('status', 'completed')->sum('total_amount'),
        ];

        $businesses = Business::orderBy('name')->get();

        return view('admin.rentals.index', compact('rentals', 'stats', 'businesses'));
    }

    /**
     * Show a specific rental
     */
    public function show(Rental $rental): View
    {
        $rental->load(['renter', 'business', 'items']);
        return view('admin.rentals.show', compact('rental'));
    }

    /**
     * Update rental status
     */
    public function updateStatus(Request $request, Rental $rental): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,active,completed,cancelled,rejected',
            'business_notes' => 'nullable|string|max:1000',
        ]);

        $rental->update([
            'status' => $validated['status'],
            'business_notes' => $validated['business_notes'] ?? $rental->business_notes,
        ]);

        if ($validated['status'] === 'approved' && !$rental->approved_at) {
            $rental->update(['approved_at' => now()]);
        }

        if ($validated['status'] === 'active' && !$rental->started_at) {
            $rental->update(['started_at' => now()]);
        }

        if ($validated['status'] === 'completed' && !$rental->completed_at) {
            $rental->update(['completed_at' => now()]);
        }

        if ($validated['status'] === 'cancelled' && !$rental->cancelled_at) {
            $rental->update(['cancelled_at' => now()]);
        }

        return redirect()->route('admin.rentals.show', $rental)
            ->with('success', 'Rental status updated successfully.');
    }

    /**
     * Delete a rental
     */
    public function destroy(Rental $rental): RedirectResponse
    {
        $rental->delete();
        return redirect()->route('admin.rentals.index')
            ->with('success', 'Rental deleted successfully.');
    }
}
