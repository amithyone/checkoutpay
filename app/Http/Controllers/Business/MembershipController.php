<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipCategory;
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
        $business = $request->user('business');
        
        $query = Membership::where('business_id', $business->id)
            ->with('category')
            ->latest();

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $memberships = $query->paginate(20);
        $categories = MembershipCategory::where('is_active', true)->get();

        $stats = [
            'total' => Membership::where('business_id', $business->id)->count(),
            'active' => Membership::where('business_id', $business->id)->where('is_active', true)->count(),
            'featured' => Membership::where('business_id', $business->id)->where('is_featured', true)->count(),
        ];

        return view('business.memberships.index', compact('memberships', 'categories', 'stats'));
    }

    /**
     * Show the form for creating a new membership
     */
    public function create(): View
    {
        $categories = MembershipCategory::where('is_active', true)->get();
        
        // Default suggestions for "who is it for"
        $defaultSuggestions = [
            'Fitness enthusiasts',
            'Beginners',
            'Professionals',
            'Students',
            'Seniors',
            'Athletes',
            'Weight loss seekers',
            'Muscle builders',
            'Yoga practitioners',
            'Dance lovers',
            'Martial arts practitioners',
            'Swimmers',
            'Runners',
            'Cyclists',
        ];

        return view('business.memberships.create', compact('categories', 'defaultSuggestions'));
    }

    /**
     * Store a newly created membership
     */
    public function store(Request $request): RedirectResponse
    {
        $business = $request->user('business');
        
        $validated = $request->validate([
            'category_id' => 'nullable|exists:membership_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'who_is_it_for' => 'nullable|string',
            'who_is_it_for_suggestions' => 'nullable|array',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'duration_type' => 'required|in:days,weeks,months,years',
            'duration_value' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
            'card_logo' => 'nullable|image|max:2048',
            'card_graphics' => 'nullable|image|max:2048',
            'terms_and_conditions' => 'nullable|string',
            'is_featured' => 'boolean',
            'max_members' => 'nullable|integer|min:1',
            'city' => 'nullable|string|max:255',
            'is_global' => 'boolean',
        ]);

        $validated['business_id'] = $business->id;
        $validated['is_active'] = $request->has('is_active');
        $validated['is_global'] = $request->has('is_global');
        
        // If global, clear city
        if ($validated['is_global']) {
            $validated['city'] = null;
        }

        // Handle image uploads
        if ($request->hasFile('images')) {
            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('memberships', 'public');
                $imagePaths[] = $path;
            }
            $validated['images'] = $imagePaths;
        }

        // Handle card logo upload
        if ($request->hasFile('card_logo')) {
            $validated['card_logo'] = $request->file('card_logo')->store('membership-cards', 'public');
        }

        // Handle card graphics upload
        if ($request->hasFile('card_graphics')) {
            $validated['card_graphics'] = $request->file('card_graphics')->store('membership-cards', 'public');
        }

        Membership::create($validated);

        return redirect()->route('business.memberships.index')
            ->with('success', 'Membership created successfully.');
    }

    /**
     * Display the specified membership
     */
    public function show(Membership $membership): View
    {
        $business = request()->user('business');
        
        if ($membership->business_id !== $business->id) {
            abort(403);
        }

        $membership->load('category');
        return view('business.memberships.show', compact('membership'));
    }

    /**
     * Show the form for editing the specified membership
     */
    public function edit(Membership $membership): View
    {
        $business = request()->user('business');
        
        if ($membership->business_id !== $business->id) {
            abort(403);
        }

        $categories = MembershipCategory::where('is_active', true)->get();
        
        $defaultSuggestions = [
            'Fitness enthusiasts',
            'Beginners',
            'Professionals',
            'Students',
            'Seniors',
            'Athletes',
            'Weight loss seekers',
            'Muscle builders',
            'Yoga practitioners',
            'Dance lovers',
            'Martial arts practitioners',
            'Swimmers',
            'Runners',
            'Cyclists',
        ];

        return view('business.memberships.edit', compact('membership', 'categories', 'defaultSuggestions'));
    }

    /**
     * Update the specified membership
     */
    public function update(Request $request, Membership $membership): RedirectResponse
    {
        $business = $request->user('business');
        
        if ($membership->business_id !== $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'category_id' => 'nullable|exists:membership_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'who_is_it_for' => 'nullable|string',
            'who_is_it_for_suggestions' => 'nullable|array',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'duration_type' => 'required|in:days,weeks,months,years',
            'duration_value' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
            'card_logo' => 'nullable|image|max:2048',
            'card_graphics' => 'nullable|image|max:2048',
            'terms_and_conditions' => 'nullable|string',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'max_members' => 'nullable|integer|min:1',
            'remove_images' => 'nullable|array',
        ]);

        // Handle image removal
        if ($request->filled('remove_images')) {
            $currentImages = $membership->images ?? [];
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
            $imagePaths = $membership->images ?? [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('memberships', 'public');
                $imagePaths[] = $path;
            }
            $validated['images'] = $imagePaths;
        }

        // Handle card logo upload
        if ($request->hasFile('card_logo')) {
            // Delete old logo if exists
            if ($membership->card_logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($membership->card_logo)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($membership->card_logo);
            }
            $validated['card_logo'] = $request->file('card_logo')->store('membership-cards', 'public');
        }

        // Handle card graphics upload
        if ($request->hasFile('card_graphics')) {
            // Delete old graphics if exists
            if ($membership->card_graphics && \Illuminate\Support\Facades\Storage::disk('public')->exists($membership->card_graphics)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($membership->card_graphics);
            }
            $validated['card_graphics'] = $request->file('card_graphics')->store('membership-cards', 'public');
        }

        // Handle global/location logic
        $validated['is_global'] = $request->has('is_global');
        if ($validated['is_global']) {
            $validated['city'] = null;
        }

        $membership->update($validated);

        return redirect()->route('business.memberships.index')
            ->with('success', 'Membership updated successfully.');
    }

    /**
     * Remove the specified membership
     */
    public function destroy(Membership $membership): RedirectResponse
    {
        $business = request()->user('business');
        
        if ($membership->business_id !== $business->id) {
            abort(403);
        }

        $membership->delete();

        return redirect()->route('business.memberships.index')
            ->with('success', 'Membership deleted successfully.');
    }
}
