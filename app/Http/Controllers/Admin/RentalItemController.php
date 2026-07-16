<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RentalItem;
use App\Models\RentalCategory;
use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RentalItemController extends Controller
{
    /**
     * Clone all rental items from one business to another.
     */
    public function cloneCatalog(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_business_id' => 'required|exists:businesses,id|different:target_business_id',
            'target_business_id' => 'required|exists:businesses,id',
        ]);

        $sourceBusiness = Business::findOrFail($validated['source_business_id']);
        $targetBusiness = Business::findOrFail($validated['target_business_id']);

        $sourceItems = RentalItem::where('business_id', $sourceBusiness->id)->get();

        if ($sourceItems->isEmpty()) {
            return redirect()->route('admin.rental-items.index')
                ->with('error', "No rental items found for {$sourceBusiness->name}.");
        }

        $clonedCount = 0;
        foreach ($sourceItems as $sourceItem) {
            $data = $sourceItem->only([
                'category_id',
                'name',
                'description',
                'city',
                'state',
                'address',
                'daily_rate',
                'weekly_rate',
                'monthly_rate',
                'currency',
                'caution_fee_enabled',
                'caution_fee_percent',
                'quantity_available',
                'is_available',
                'specifications',
                'terms_and_conditions',
                'is_active',
                'is_featured',
                'featured_tag',
                'featured_sort',
                'discount_active',
                'discount_percent',
                'discount_starts_at',
                'discount_ends_at',
                'how_to_videos',
            ]);
            $data['business_id'] = $targetBusiness->id;

            // Copy images so businesses keep independent media files.
            $newImages = [];
            $sourceImages = is_array($sourceItem->images) ? $sourceItem->images : [];
            foreach ($sourceImages as $src) {
                $src = (string) $src;
                if ($src === '' || !Storage::disk('public')->exists($src)) {
                    continue;
                }
                $ext = pathinfo($src, PATHINFO_EXTENSION);
                $dest = 'rental-items/' . $targetBusiness->id . '/catalog-clones/' . Str::random(20) . ($ext ? '.' . $ext : '');
                Storage::disk('public')->copy($src, $dest);
                $newImages[] = $dest;
            }
            if (!empty($newImages)) {
                $data['images'] = $newImages;
            } else {
                $data['images'] = null;
            }

            RentalItem::create($data);
            $clonedCount++;
        }

        return redirect()->route('admin.rental-items.index')
            ->with('success', "Cloned {$clonedCount} rental item(s) from {$sourceBusiness->name} to {$targetBusiness->name}.");
    }

    public function index(Request $request): View
    {
        $query = $this->filteredItemsQuery($request);

        $filteredTotal = (clone $query)->count();
        $items = $query->latest()->paginate(30)->withQueryString();
        $categories = RentalCategory::where('is_active', true)->get();
        $businesses = Business::orderBy('name')->get();

        return view('admin.rental-items.index', compact('items', 'categories', 'businesses', 'filteredTotal'));
    }

    public function bulkHowToVideos(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer|exists:rental_items,id',
            'select_all_filtered' => 'nullable|boolean',
            'mode' => 'required|in:replace,append,clear',
            'how_to_videos' => 'nullable|array',
            'how_to_videos.*.title' => 'nullable|string|max:200',
            'how_to_videos.*.url' => 'nullable|string|max:500',
            'category_id' => 'nullable|integer|exists:rental_categories,id',
            'business_id' => 'nullable|integer|exists:businesses,id',
            'status' => 'nullable|string|in:active,inactive,unavailable,featured',
            'how_to_filter' => 'nullable|string|in:with,without',
            'search' => 'nullable|string|max:255',
        ]);

        if ($request->boolean('select_all_filtered')) {
            $ids = $this->filteredItemsQuery($request)->pluck('id');
        } else {
            $ids = collect($validated['item_ids'] ?? [])->map(fn ($id) => (int) $id)->filter();
        }

        if ($ids->isEmpty()) {
            return redirect()->route('admin.rental-items.index', $request->only([
                'category_id', 'business_id', 'status', 'how_to_filter', 'search',
            ]))->with('error', 'Select at least one item, or use “select all matching filters”.');
        }

        $mode = $validated['mode'];
        $newVideos = RentalItem::normalizeHowToVideos($request->input('how_to_videos', []));

        if ($mode !== 'clear' && $newVideos === []) {
            return redirect()->route('admin.rental-items.index', $request->only([
                'category_id', 'business_id', 'status', 'how_to_filter', 'search',
            ]))->with('error', 'Add at least one valid YouTube URL, or choose “Clear videos”.');
        }

        $updated = 0;
        RentalItem::query()->whereIn('id', $ids->all())->chunkById(100, function ($chunk) use ($mode, $newVideos, &$updated) {
            foreach ($chunk as $item) {
                if ($mode === 'clear') {
                    $item->update(['how_to_videos' => null]);
                    $updated++;

                    continue;
                }

                if ($mode === 'replace') {
                    $item->update(['how_to_videos' => $newVideos ?: null]);
                    $updated++;

                    continue;
                }

                $merged = RentalItem::mergeHowToVideos(
                    RentalItem::normalizeHowToVideos($item->how_to_videos ?? []),
                    $newVideos
                );
                $item->update(['how_to_videos' => $merged ?: null]);
                $updated++;
            }
        });

        $message = match ($mode) {
            'clear' => "Cleared how-to videos on {$updated} item(s).",
            'replace' => "Set how-to videos on {$updated} item(s).",
            default => "Appended how-to videos on {$updated} item(s).",
        };

        return redirect()->route('admin.rental-items.index', $request->only([
            'category_id', 'business_id', 'status', 'how_to_filter', 'search',
        ]))->with('success', $message);
    }

    protected function filteredItemsQuery(Request $request): Builder
    {
        $query = RentalItem::query()->with(['business', 'category']);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'active' => $query->where('is_active', true)->where('is_available', true),
                'inactive' => $query->where('is_active', false),
                'unavailable' => $query->where('is_available', false),
                'featured' => $query->where('is_featured', true),
                default => null,
            };
        }

        if ($request->filled('how_to_filter')) {
            if ($request->how_to_filter === 'with') {
                $query->whereNotNull('how_to_videos')
                    ->whereRaw("JSON_LENGTH(how_to_videos) > 0");
            } elseif ($request->how_to_filter === 'without') {
                $query->where(function ($q) {
                    $q->whereNull('how_to_videos')
                        ->orWhereRaw("JSON_LENGTH(how_to_videos) = 0");
                });
            }
        }

        if ($request->filled('search')) {
            $search = (string) $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('business', function ($bq) use ($search) {
                        $bq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    /**
     * Show the form for creating a new item
     */
    public function create(): View
    {
        $categories = RentalCategory::where('is_active', true)->get();
        $businesses = Business::orderBy('name')->get();
        return view('admin.rental-items.create', compact('categories', 'businesses'));
    }

    /**
     * Store a newly created item
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
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
            'featured_tag' => 'nullable|string|max:120',
            'featured_sort' => 'nullable|integer|min:1|max:9999',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'discount_active' => 'sometimes|boolean',
            'discount_percent' => 'nullable|numeric|min:0|max:95',
            'discount_starts_at' => 'nullable|date',
            'discount_ends_at' => 'nullable|date',
            'how_to_videos' => 'nullable|array',
            'how_to_videos.*.title' => 'nullable|string|max:200',
            'how_to_videos.*.url' => 'nullable|string|max:500',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['is_available'] = $validated['is_available'] ?? true;
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated = array_merge(
            $validated,
            RentalItem::discountFieldsFromRequest($request),
            RentalItem::featuredFieldsFromRequest($request),
            ['how_to_videos' => RentalItem::normalizeHowToVideos($request->input('how_to_videos', [])) ?: null]
        );

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

        return redirect()->route('admin.rental-items.index')
            ->with('success', 'Rental item created successfully.');
    }

    /**
     * Show a specific item
     */
    public function show(RentalItem $rentalItem): View
    {
        $rentalItem->load(['business', 'category']);
        return view('admin.rental-items.show', compact('rentalItem'));
    }

    /**
     * Show the form for editing an item
     */
    public function edit(RentalItem $rentalItem): View
    {
        $categories = RentalCategory::where('is_active', true)->get();
        $businesses = Business::orderBy('name')->get();
        return view('admin.rental-items.edit', compact('rentalItem', 'categories', 'businesses'));
    }

    /**
     * Update an item
     */
    public function update(Request $request, RentalItem $rentalItem): RedirectResponse
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
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
            'featured_tag' => 'nullable|string|max:120',
            'featured_sort' => 'nullable|integer|min:1|max:9999',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'remove_images' => 'nullable|array',
            'discount_active' => 'sometimes|boolean',
            'discount_percent' => 'nullable|numeric|min:0|max:95',
            'discount_starts_at' => 'nullable|date',
            'discount_ends_at' => 'nullable|date',
            'how_to_videos' => 'nullable|array',
            'how_to_videos.*.title' => 'nullable|string|max:200',
            'how_to_videos.*.url' => 'nullable|string|max:500',
        ]);

        $validated = array_merge(
            $validated,
            RentalItem::discountFieldsFromRequest($request),
            RentalItem::featuredFieldsFromRequest($request),
            ['how_to_videos' => RentalItem::normalizeHowToVideos($request->input('how_to_videos', [])) ?: null]
        );

        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_available'] = $request->boolean('is_available');

        if (! $validated['is_featured']) {
            $validated['featured_tag'] = null;
            $validated['featured_sort'] = null;
        }

        $currentImages = is_array($rentalItem->images) ? array_values($rentalItem->images) : [];

        if ($request->filled('remove_images')) {
            foreach ((array) $request->remove_images as $imageToRemove) {
                if (! is_string($imageToRemove) || $imageToRemove === '' || str_contains($imageToRemove, '..')) {
                    continue;
                }
                if (Storage::disk('public')->exists($imageToRemove)) {
                    Storage::disk('public')->delete($imageToRemove);
                }
                $currentImages = array_values(array_filter($currentImages, fn ($img) => $img !== $imageToRemove));
            }
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                if (! $image) {
                    continue;
                }
                $currentImages[] = $image->store('rental-items', 'public');
            }
        }

        if ($request->filled('remove_images') || $request->hasFile('images')) {
            $validated['images'] = count($currentImages) > 0 ? $currentImages : null;
        }

        unset($validated['remove_images']);

        $rentalItem->update($validated);

        return redirect()->route('admin.rental-items.index')
            ->with('success', 'Rental item updated successfully.');
    }

    /**
     * Delete an item
     */
    public function destroy(RentalItem $rentalItem): RedirectResponse
    {
        // Delete images
        if ($rentalItem->images) {
            foreach ($rentalItem->images as $image) {
                if (Storage::disk('public')->exists($image)) {
                    Storage::disk('public')->delete($image);
                }
            }
        }

        $rentalItem->delete();
        return redirect()->route('admin.rental-items.index')
            ->with('success', 'Rental item deleted successfully.');
    }
}
