<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RentalFeaturedBanner;
use App\Models\RentalItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RentalFeaturedBannerController extends Controller
{
    public function index(): View
    {
        $banners = RentalFeaturedBanner::query()
            ->with(['rentalItem', 'creator'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.rental-featured-banners.index', compact('banners'));
    }

    public function create(): View
    {
        $items = RentalItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('admin.rental-featured-banners.create', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedBanner($request, true);
        $validated['created_by'] = Auth::id();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('rentals/featured-banners', 'public');
        }

        RentalFeaturedBanner::query()->create($validated);

        return redirect()->route('admin.rental-featured-banners.index')
            ->with('success', 'Featured banner created.');
    }

    public function edit(RentalFeaturedBanner $rentalFeaturedBanner): View
    {
        $items = RentalItem::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('admin.rental-featured-banners.edit', [
            'banner' => $rentalFeaturedBanner,
            'items' => $items,
        ]);
    }

    public function update(Request $request, RentalFeaturedBanner $rentalFeaturedBanner): RedirectResponse
    {
        $validated = $this->validatedBanner($request, false);

        if ($request->hasFile('image')) {
            if ($rentalFeaturedBanner->image && Storage::disk('public')->exists($rentalFeaturedBanner->image)) {
                Storage::disk('public')->delete($rentalFeaturedBanner->image);
            }
            $validated['image'] = $request->file('image')->store('rentals/featured-banners', 'public');
        }

        $rentalFeaturedBanner->update($validated);

        return redirect()->route('admin.rental-featured-banners.index')
            ->with('success', 'Featured banner updated.');
    }

    public function destroy(RentalFeaturedBanner $rentalFeaturedBanner): RedirectResponse
    {
        if ($rentalFeaturedBanner->image && Storage::disk('public')->exists($rentalFeaturedBanner->image)) {
            Storage::disk('public')->delete($rentalFeaturedBanner->image);
        }

        $rentalFeaturedBanner->delete();

        return redirect()->route('admin.rental-featured-banners.index')
            ->with('success', 'Featured banner deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedBanner(Request $request, bool $creating): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'tag' => 'nullable|string|max:120',
            'subtitle' => 'nullable|string|max:500',
            'link_url' => 'nullable|url|max:2048',
            'rental_item_id' => 'nullable|integer|exists:rental_items,id',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'image' => ($creating ? 'required' : 'nullable').'|image|max:4096',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 100);
        $validated['rental_item_id'] = $validated['rental_item_id'] ?? null;
        $validated['tag'] = filled($validated['tag'] ?? null) ? $validated['tag'] : 'Sponsored';

        return $validated;
    }
}
