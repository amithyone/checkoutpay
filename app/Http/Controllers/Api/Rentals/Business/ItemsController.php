<?php

namespace App\Http\Controllers\Api\Rentals\Business;

use App\Http\Controllers\Api\Rentals\Business\Concerns\ResolvesBusiness;
use App\Http\Controllers\Controller;
use App\Models\RentalCategory;
use App\Models\RentalItem;
use Illuminate\Http\Request;

class ItemsController extends Controller
{
    use ResolvesBusiness;

    /**
     * GET /api/v1/rentals/business/items
     */
    public function index(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        $items = RentalItem::where('business_id', $business->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/rentals/business/items
     */
    public function store(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:rental_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'daily_rate' => 'required|numeric|min:0',
            'weekly_rate' => 'nullable|numeric|min:0',
            'monthly_rate' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'quantity_available' => 'required|integer|min:1',
            'is_available' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'terms_and_conditions' => 'nullable|string|max:8000',
            'specifications' => 'nullable|array',
            // Accept either JSON array of paths/urls OR multipart uploads `images[]`
            'images' => 'nullable|array',
            'images.*' => 'nullable',
            'images_uploads' => 'nullable',
        ]);

        $categoryId = $validated['category_id'] ?? null;
        if (! $categoryId) {
            $categoryId = RentalCategory::query()->where('is_active', true)->orderBy('sort_order')->value('id');
        }
        if (! $categoryId) {
            return response()->json(['message' => 'No rental category available. Please contact support.'], 422);
        }

        $images = [];
        if (is_array($validated['images'] ?? null)) {
            $images = array_values(array_filter(array_map(fn ($x) => is_string($x) ? trim($x) : null, $validated['images'])));
        }
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                if (! $img) continue;
                $path = $img->store('rental-items', 'public');
                $images[] = $path;
            }
        }

        $item = RentalItem::create(array_merge($validated, [
            'business_id' => $business->id,
            'category_id' => $categoryId,
            'currency' => $validated['currency'] ?? 'NGN',
            'is_available' => (bool) ($validated['is_available'] ?? true),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'images' => $images ?: null,
        ]));

        return response()->json([
            'data' => $item,
        ], 201);
    }

    /**
     * PATCH /api/v1/rentals/business/items/{item}
     */
    public function update(Request $request, RentalItem $item)
    {
        $business = $this->resolveBusinessOr403($request);
        if ($item->business_id !== $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'category_id' => 'sometimes|nullable|integer|exists:rental_categories,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:5000',
            'daily_rate' => 'sometimes|required|numeric|min:0',
            'weekly_rate' => 'sometimes|nullable|numeric|min:0',
            'monthly_rate' => 'sometimes|nullable|numeric|min:0',
            'currency' => 'sometimes|nullable|string|max:10',
            'quantity_available' => 'sometimes|required|integer|min:1',
            'is_available' => 'sometimes|nullable|boolean',
            'is_active' => 'sometimes|nullable|boolean',
            'address' => 'sometimes|nullable|string|max:1000',
            'city' => 'sometimes|nullable|string|max:255',
            'state' => 'sometimes|nullable|string|max:255',
            'terms_and_conditions' => 'sometimes|nullable|string|max:8000',
            'specifications' => 'sometimes|nullable|array',
            'images' => 'sometimes|nullable|array',
            'images.*' => 'nullable',
            'remove_images' => 'sometimes|nullable|array',
            'remove_images.*' => 'string',
        ]);

        // Handle images: allow removal + appending uploads
        $images = is_array($item->images) ? $item->images : [];
        if (isset($validated['images']) && is_array($validated['images'])) {
            $images = array_values(array_filter(array_map(fn ($x) => is_string($x) ? trim($x) : null, $validated['images'])));
        }
        if (isset($validated['remove_images']) && is_array($validated['remove_images'])) {
            $toRemove = array_map('strval', $validated['remove_images']);
            $images = array_values(array_filter($images, fn ($p) => !in_array($p, $toRemove, true)));
        }
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                if (! $img) continue;
                $path = $img->store('rental-items', 'public');
                $images[] = $path;
            }
        }
        $validated['images'] = $images ?: null;

        $item->update($validated);

        return response()->json([
            'data' => $item->fresh(),
        ]);
    }
}

