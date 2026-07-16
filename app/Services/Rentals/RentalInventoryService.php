<?php

namespace App\Services\Rentals;

use App\Models\Rental;
use App\Models\RentalItem;
use Carbon\Carbon;

class RentalInventoryService
{
    /**
     * @param  array<int, array{id: int, quantity: int, selected_dates: array<int, string>}>  $cartItems
     * @return array{ok: bool, unavailable: array<string, array<int, string>>}
     */
    public function checkCartAvailability(array $cartItems, ?int $excludeRentalId = null): array
    {
        $unavailable = [];

        foreach ($cartItems as $entry) {
            $itemId = (int) ($entry['id'] ?? 0);
            $quantity = max(1, (int) ($entry['quantity'] ?? 1));
            $dates = array_values(array_unique(array_map(
                fn ($d) => Carbon::parse($d)->format('Y-m-d'),
                $entry['selected_dates'] ?? []
            )));

            $item = RentalItem::query()->find($itemId);
            if (! $item || ! $item->is_active || ! $item->is_available) {
                $unavailable[(string) $itemId] = $dates;

                continue;
            }

            $badDates = [];
            foreach ($dates as $date) {
                $booked = $this->bookedQuantityOnDate($item, $date, $excludeRentalId);
                if (($item->quantity_available - $booked) < $quantity) {
                    $badDates[] = $date;
                }
            }

            if ($badDates !== []) {
                $unavailable[(string) $itemId] = $badDates;
            }
        }

        return [
            'ok' => $unavailable === [],
            'unavailable' => $unavailable,
        ];
    }

    public function bookedQuantityOnDate(RentalItem $item, string $date, ?int $excludeRentalId = null): int
    {
        $day = Carbon::parse($date)->startOfDay();

        $rentals = Rental::query()
            ->whereHas('items', fn ($q) => $q->where('rental_items.id', $item->id))
            ->whereIn('status', [Rental::STATUS_PENDING, Rental::STATUS_APPROVED, Rental::STATUS_ACTIVE])
            ->when($excludeRentalId, fn ($q) => $q->where('id', '!=', $excludeRentalId))
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->with(['items' => fn ($q) => $q->where('rental_items.id', $item->id)])
            ->get();

        $total = 0;
        foreach ($rentals as $rental) {
            $pivot = $rental->items->first();
            if ($pivot) {
                $total += (int) $pivot->pivot->quantity;
            }
        }

        return $total;
    }

    /**
     * @param  array<int, array{id: int, quantity: int, selected_dates: array<int, string>}>  $cartItems
     */
    public function inventoryOversellResponse(array $unavailable): \Illuminate\Http\JsonResponse
    {
        $firstId = array_key_first($unavailable);

        return response()->json([
            'success' => false,
            'message' => 'One or more selected dates are no longer available for item '.$firstId.'.',
            'code' => 'INVENTORY_OVERSELL',
            'unavailable' => $unavailable,
        ], 409);
    }
}
