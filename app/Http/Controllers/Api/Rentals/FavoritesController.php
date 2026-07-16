<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\RentalFavorite;
use App\Models\RentalItem;
use App\Models\Renter;
use App\Services\Rentals\RentalCatalogFormatter;
use Illuminate\Http\Request;

class FavoritesController extends Controller
{
    /**
     * GET /api/v1/rentals/favorites
     */
    public function index(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $favorites = RentalFavorite::query()
            ->where('renter_id', $renter->id)
            ->with(['item' => fn ($q) => $q->withTrashed()->with('category')])
            ->latest()
            ->get();

        $data = $favorites->map(function (RentalFavorite $favorite) {
            $item = $favorite->item;

            return [
                'id' => $favorite->id,
                'item_id' => $favorite->rental_item_id,
                'item' => $item ? RentalCatalogFormatter::itemSummary($item) : null,
                'created_at' => $favorite->created_at?->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $data,
            'item_ids' => $favorites->pluck('rental_item_id')->values()->all(),
        ]);
    }

    /**
     * POST /api/v1/rentals/favorites
     */
    public function store(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $validated = $request->validate([
            'item_id' => 'required|integer|exists:rental_items,id',
        ]);

        $itemId = (int) $validated['item_id'];
        $item = RentalItem::query()->find($itemId);
        if (! $item) {
            return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
        }

        $existing = RentalFavorite::query()
            ->where('renter_id', $renter->id)
            ->where('rental_item_id', $itemId)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'data' => ['item_id' => $itemId],
            ]);
        }

        RentalFavorite::query()->create([
            'renter_id' => $renter->id,
            'rental_item_id' => $itemId,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['item_id' => $itemId],
        ], 201);
    }

    /**
     * DELETE /api/v1/rentals/favorites/{itemId}
     * DELETE /api/v1/rentals/favorites  body: { "item_id": 4 }
     */
    public function destroy(Request $request, ?int $itemId = null)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $resolvedItemId = $itemId ?? $request->input('item_id');
        if ($resolvedItemId === null || $resolvedItemId === '') {
            return response()->json([
                'success' => false,
                'message' => 'item_id is required.',
            ], 422);
        }

        RentalFavorite::query()
            ->where('renter_id', $renter->id)
            ->where('rental_item_id', (int) $resolvedItemId)
            ->delete();

        return response()->json(['success' => true]);
    }
}
