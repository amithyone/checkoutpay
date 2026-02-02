<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\RentalCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class RentalController extends Controller
{

    /**
     * Display a listing of rental requests
     */
    public function index(Request $request): View
    {
        $business = $request->user('business');
        
        $query = Rental::where('business_id', $business->id)
            ->with(['renter', 'items'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('rental_number', 'like', "%{$search}%")
                  ->orWhere('renter_name', 'like', "%{$search}%")
                  ->orWhere('renter_email', 'like', "%{$search}%");
            });
        }

        $rentals = $query->paginate(20);

        $stats = [
            'total' => Rental::where('business_id', $business->id)->count(),
            'pending' => Rental::where('business_id', $business->id)->where('status', 'pending')->count(),
            'approved' => Rental::where('business_id', $business->id)->where('status', 'approved')->count(),
            'active' => Rental::where('business_id', $business->id)->where('status', 'active')->count(),
            'completed' => Rental::where('business_id', $business->id)->where('status', 'completed')->count(),
        ];

        return view('business.rentals.index', compact('rentals', 'stats'));
    }

    /**
     * Show the form for creating a new rental item
     */
    public function createItem(): View
    {
        $business = request()->user('business');
        $categories = RentalCategory::where('is_active', true)->get();
        return view('business.rentals.create-item', compact('categories'));
    }

    /**
     * Store a newly created rental item
     */
    public function storeItem(Request $request): RedirectResponse
    {
        $business = $request->user('business');
        
        $validated = $request->validate([
            'category_id' => 'required|exists:rental_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'daily_rate' => 'required|numeric|min:0',
            'weekly_rate' => 'nullable|numeric|min:0',
            'monthly_rate' => 'nullable|numeric|min:0',
            'quantity_available' => 'required|integer|min:1',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
            'specifications' => 'nullable|array',
            'is_featured' => 'boolean',
        ]);

        $validated['business_id'] = $business->id;
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['is_available'] = $validated['is_available'] ?? true;

        // Handle image uploads
        if ($request->hasFile('images')) {
            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('rental-items', 'public');
                $imagePaths[] = $path;
            }
            $validated['images'] = $imagePaths;
        }

        RentalItem::create($validated);

        return redirect()->route('business.rentals.items')
            ->with('success', 'Rental item created successfully.');
    }

    /**
     * Display rental items
     */
    public function items(Request $request): View
    {
        $business = $request->user('business');
        
        $query = RentalItem::where('business_id', $business->id)
            ->with('category');

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true)->where('is_available', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'unavailable') {
                $query->where('is_available', false);
            }
        }

        $items = $query->latest()->paginate(20);
        $categories = RentalCategory::where('is_active', true)->get();

        return view('business.rentals.items', compact('items', 'categories'));
    }

    /**
     * Show the form for editing a rental item
     */
    public function editItem(RentalItem $item): View
    {
        $business = request()->user('business');
        
        if ($item->business_id !== $business->id) {
            abort(403);
        }

        $categories = RentalCategory::where('is_active', true)->get();
        return view('business.rentals.edit-item', compact('item', 'categories'));
    }

    /**
     * Update a rental item
     */
    public function updateItem(Request $request, RentalItem $item): RedirectResponse
    {
        $business = $request->user('business');
        
        if ($item->business_id !== $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:rental_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'daily_rate' => 'required|numeric|min:0',
            'weekly_rate' => 'nullable|numeric|min:0',
            'monthly_rate' => 'nullable|numeric|min:0',
            'quantity_available' => 'required|integer|min:1',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
            'specifications' => 'nullable|array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'remove_images' => 'nullable|array',
        ]);

        // Handle image removal
        if ($request->filled('remove_images')) {
            $currentImages = $item->images ?? [];
            foreach ($request->remove_images as $imageToRemove) {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($imageToRemove)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($imageToRemove);
                }
                $currentImages = array_filter($currentImages, function($img) use ($imageToRemove) {
                    return $img !== $imageToRemove;
                });
            }
            $validated['images'] = array_values($currentImages);
        }

        // Handle new image uploads
        if ($request->hasFile('images')) {
            $imagePaths = $item->images ?? [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('rental-items', 'public');
                $imagePaths[] = $path;
            }
            $validated['images'] = $imagePaths;
        }

        $item->update($validated);

        return redirect()->route('business.rentals.items')
            ->with('success', 'Rental item updated successfully.');
    }

    /**
     * Delete a rental item
     */
    public function deleteItem(RentalItem $item): RedirectResponse
    {
        $business = request()->user('business');
        
        if ($item->business_id !== $business->id) {
            abort(403);
        }

        $item->delete();

        return redirect()->route('business.rentals.items')
            ->with('success', 'Rental item deleted successfully.');
    }

    /**
     * Update rental status
     */
    public function updateStatus(Request $request, Rental $rental): RedirectResponse
    {
        $business = $request->user('business');
        
        if ($rental->business_id !== $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,active,completed,cancelled,rejected',
        ]);

        $rental->update(['status' => $validated['status']]);

        if ($validated['status'] === 'active' && !$rental->started_at) {
            $rental->update(['started_at' => now()]);
        }

        if ($validated['status'] === 'completed' && !$rental->completed_at) {
            $rental->update(['completed_at' => now()]);
        }

        if ($validated['status'] === 'cancelled' && !$rental->cancelled_at) {
            $rental->update(['cancelled_at' => now()]);
        }

        return redirect()->route('business.rentals.show', $rental)
            ->with('success', 'Rental status updated successfully.');
    }

    /**
     * Show a specific rental request
     */
    public function show(Rental $rental): View
    {
        $business = request()->user('business');
        
        if ($rental->business_id !== $business->id) {
            abort(403);
        }

        $rental->load(['renter', 'items', 'business']);

        return view('business.rentals.show', compact('rental'));
    }

    /**
     * Approve a rental request
     */
    public function approve(Request $request, Rental $rental): RedirectResponse
    {
        $business = $request->user('business');
        
        if ($rental->business_id !== $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'business_notes' => 'nullable|string|max:1000',
        ]);

        $rental->approve($validated['business_notes'] ?? null);

        return redirect()->route('business.rentals.show', $rental)
            ->with('success', 'Rental request approved successfully.');
    }

    /**
     * Reject a rental request
     */
    public function reject(Request $request, Rental $rental): RedirectResponse
    {
        $business = $request->user('business');
        
        if ($rental->business_id !== $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'business_notes' => 'required|string|max:1000',
        ]);

        $rental->reject($validated['business_notes']);

        return redirect()->route('business.rentals.show', $rental)
            ->with('success', 'Rental request rejected.');
    }
}
