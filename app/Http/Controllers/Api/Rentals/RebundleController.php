<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Renter;
use App\Services\Rentals\RentalCatalogFormatter;
use App\Services\Rentals\RentalInventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RebundleController extends Controller
{
    public function __construct(
        protected RentalInventoryService $inventoryService
    ) {}

    /**
     * GET /api/v1/rentals/requests/{rental}/rebundle-preview
     */
    public function preview(Request $request, Rental $rental)
    {
        if (! $this->ownsRental($request, $rental)) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $rental->load(['items' => fn ($q) => $q->withTrashed()->with('category')]);

        $lines = $rental->items->map(function (RentalItem $item) {
            $reason = RentalCatalogFormatter::unavailableReason($item);

            return [
                'item_id' => $item->id,
                'name' => $item->name,
                'quantity' => (int) ($item->pivot->quantity ?? 1),
                'available' => $reason === null,
                'reason' => $reason,
            ];
        })->values()->all();

        $canRebundle = collect($lines)->contains(fn ($line) => $line['available']);

        return response()->json([
            'success' => true,
            'data' => [
                'can_rebundle' => $canRebundle,
                'lines' => $lines,
            ],
        ]);
    }

    /**
     * POST /api/v1/rentals/requests/{rental}/rebundle
     */
    public function rebundle(Request $request, Rental $rental)
    {
        if (! $this->ownsRental($request, $rental)) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $rental->load(['items' => fn ($q) => $q->withTrashed()->with(['category', 'business'])]);

        $validated = $request->validate([
            'selected_dates' => 'nullable|array|min:1',
            'selected_dates.*' => 'date|after_or_equal:today',
            'apply_to_all_items' => 'nullable|boolean',
            'items' => 'nullable|array|min:1',
            'items.*.id' => 'required_with:items|integer',
            'items.*.quantity' => 'nullable|integer|min:1',
            'items.*.selected_dates' => 'required_with:items|array|min:1',
            'items.*.selected_dates.*' => 'date|after_or_equal:today',
        ]);

        $cartItems = $this->buildCartItemsFromRequest($rental, $validated);
        if ($cartItems === []) {
            return response()->json([
                'success' => false,
                'message' => 'No rebundle items could be resolved.',
            ], 422);
        }

        $check = $this->inventoryService->checkCartAvailability($cartItems);
        if (! $check['ok']) {
            return response()->json([
                'success' => false,
                'code' => 'INVENTORY_OVERSELL',
                'message' => 'One or more items are unavailable for the selected dates.',
                'unavailable' => $check['unavailable'],
            ], 409);
        }

        $itemIds = collect($cartItems)->pluck('id')->all();
        $catalogItems = RentalItem::query()
            ->whereIn('id', $itemIds)
            ->with(['business', 'category'])
            ->get()
            ->keyBy('id');

        $lines = [];
        $unavailable = [];
        $totalAmount = 0.0;
        $depositAmount = 0.0;

        foreach ($cartItems as $entry) {
            $item = $catalogItems[$entry['id']] ?? null;
            if (! $item) {
                $unavailable[] = $entry['id'];

                continue;
            }

            $reason = RentalCatalogFormatter::unavailableReason($item);
            if ($reason !== null) {
                $unavailable[] = $entry['id'];

                continue;
            }

            $selected = array_values(array_unique($entry['selected_dates']));
            sort($selected);
            $days = count($selected);
            $quantity = (int) $entry['quantity'];
            $rate = $item->getRateForPeriod($days);
            $itemTotal = $rate * $quantity;

            $globalEnabled = (bool) ($item->business?->rental_global_caution_fee_enabled ?? false);
            $globalPercent = (float) ($item->business?->rental_global_caution_fee_percent ?? 0);
            $cautionPercent = $globalEnabled
                ? $globalPercent
                : ($item->caution_fee_enabled ? (float) $item->caution_fee_percent : 0.0);
            $itemCaution = $cautionPercent > 0 ? round(($itemTotal * $cautionPercent) / 100, 2) : 0.0;

            $totalAmount += $itemTotal;
            $depositAmount += $itemCaution;

            $lines[] = [
                'id' => $item->id,
                'quantity' => $quantity,
                'selected_dates' => $selected,
                'available' => true,
                'item' => RentalCatalogFormatter::itemSummary($item),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $lines,
                'unavailable' => array_values(array_unique($unavailable)),
                'quote' => [
                    'total_amount' => round($totalAmount, 2),
                    'deposit_amount' => round($depositAmount, 2),
                    'grand_total' => round($totalAmount + $depositAmount, 2),
                ],
            ],
        ]);
    }

    protected function ownsRental(Request $request, Rental $rental): bool
    {
        /** @var Renter $renter */
        $renter = $request->user();

        return (int) $rental->renter_id === (int) $renter->id;
    }

    /**
     * @return array<int, array{id: int, quantity: int, selected_dates: array<int, string>}>
     */
    protected function buildCartItemsFromRequest(Rental $rental, array $validated): array
    {
        if (! empty($validated['items'])) {
            return collect($validated['items'])->map(function ($entry) use ($rental) {
                $rentalLine = $rental->items->firstWhere('id', (int) $entry['id']);

                return [
                    'id' => (int) $entry['id'],
                    'quantity' => (int) ($entry['quantity'] ?? ($rentalLine?->pivot->quantity ?? 1)),
                    'selected_dates' => array_values(array_unique(array_map(
                        fn ($d) => Carbon::parse($d)->format('Y-m-d'),
                        $entry['selected_dates'] ?? []
                    ))),
                ];
            })->all();
        }

        $sharedDates = collect($validated['selected_dates'] ?? [])
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->unique()
            ->values()
            ->all();

        if ($sharedDates === []) {
            return [];
        }

        return $rental->items
            ->filter(fn (RentalItem $item) => RentalCatalogFormatter::unavailableReason($item) === null)
            ->map(fn (RentalItem $item) => [
                'id' => $item->id,
                'quantity' => (int) ($item->pivot->quantity ?? 1),
                'selected_dates' => $sharedDates,
            ])
            ->values()
            ->all();
    }
}
