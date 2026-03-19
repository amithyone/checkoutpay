<?php

namespace App\Http\Controllers\Api\Rentals\Business;

use App\Http\Controllers\Api\Rentals\Business\Concerns\ResolvesBusiness;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use Illuminate\Http\Request;

class RentalsController extends Controller
{
    use ResolvesBusiness;

    protected function maybeFinalizeReturn(Rental $rental): void
    {
        $rental->refresh();
        if ($rental->returned_at) {
            return;
        }
        if (! $rental->renter_return_requested_at || ! $rental->business_return_confirmed_at) {
            return;
        }
        $rental->update([
            'returned_at' => now(),
            'completed_at' => $rental->completed_at ?? now(),
            'status' => Rental::STATUS_COMPLETED,
        ]);
    }

    /**
     * GET /api/v1/rentals/business/rentals?status=pending|approved|active|completed|cancelled|rejected
     */
    public function index(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        $status = $request->query('status');

        $q = Rental::with(['items', 'business'])
            ->where('business_id', $business->id)
            ->latest();

        if (is_string($status) && trim($status) !== '') {
            $q->where('status', trim($status));
        }

        $rentals = $q->paginate(20);

        return response()->json([
            'data' => $rentals->items(),
            'meta' => [
                'current_page' => $rentals->currentPage(),
                'per_page' => $rentals->perPage(),
                'total' => $rentals->total(),
                'last_page' => $rentals->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/rentals/business/rentals/{rental}
     */
    public function show(Request $request, Rental $rental)
    {
        $business = $this->resolveBusinessOr403($request);
        if ($rental->business_id !== $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $rental->load(['items', 'business']);

        return response()->json([
            'data' => $rental,
        ]);
    }

    /**
     * POST /api/v1/rentals/business/rentals/{rental}/mark-picked-up
     */
    public function markPickedUp(Request $request, Rental $rental)
    {
        $business = $this->resolveBusinessOr403($request);
        if ($rental->business_id !== $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! in_array($rental->status, [Rental::STATUS_APPROVED, Rental::STATUS_ACTIVE], true)) {
            return response()->json([
                'message' => 'Rental must be approved before it can be marked as picked up.',
            ], 422);
        }

        if (! $rental->started_at || $rental->status !== Rental::STATUS_ACTIVE) {
            $rental->update([
                'status' => Rental::STATUS_ACTIVE,
                'started_at' => $rental->started_at ?? now(),
            ]);
        }

        $rental->load(['items', 'business']);

        return response()->json([
            'message' => 'Pickup confirmed. Rental is now active.',
            'data' => $rental,
        ]);
    }

    /**
     * POST /api/v1/rentals/business/rentals/{rental}/confirm-return
     */
    public function confirmReturn(Request $request, Rental $rental)
    {
        $business = $this->resolveBusinessOr403($request);
        if ($rental->business_id !== $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! in_array($rental->status, [Rental::STATUS_ACTIVE, Rental::STATUS_APPROVED, Rental::STATUS_COMPLETED], true)) {
            return response()->json([
                'message' => 'Rental must be active (or approved) to confirm return.',
            ], 422);
        }

        if (! $rental->renter_return_requested_at && ! $rental->returned_at) {
            return response()->json([
                'message' => 'Renter must request return first.',
            ], 422);
        }

        if (! $rental->business_return_confirmed_at) {
            $rental->update(['business_return_confirmed_at' => now()]);
        }

        $this->maybeFinalizeReturn($rental);

        $rental->load(['items', 'business']);

        return response()->json([
            'message' => $rental->fresh()->returned_at ? 'Return completed.' : 'Return confirmed by business. Awaiting renter confirmation.',
            'data' => $rental->fresh(),
        ]);
    }
}

