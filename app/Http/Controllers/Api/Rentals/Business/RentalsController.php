<?php

namespace App\Http\Controllers\Api\Rentals\Business;

use App\Http\Controllers\Api\Rentals\Business\Concerns\ResolvesBusiness;
use App\Http\Controllers\Api\Rentals\Concerns\FormatsRentalDetail;
use App\Http\Controllers\Controller;
use App\Mail\RentalApprovedPayNow;
use App\Models\Rental;
use App\Models\RentalConditionReport;
use App\Models\RentalItem;
use App\Services\RentalPaymentService;
use App\Services\Rentals\RentalEscrowService;
use App\Services\Rentals\RentalInventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class RentalsController extends Controller
{
    use FormatsRentalDetail, ResolvesBusiness;

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
        if ((int) $rental->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $rental->load(['items', 'business']);

        return response()->json([
            'data' => $this->rentalDetailPayload($rental),
        ]);
    }

    /**
     * POST /api/v1/rentals/business/rentals/{rental}/approve
     */
    public function approve(Request $request, Rental $rental, RentalPaymentService $paymentService)
    {
        $business = $this->resolveBusinessOr403($request);
        if ((int) $rental->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! $rental->isPending()) {
            return response()->json(['message' => 'Only pending rentals can be approved.'], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $rental->approve($validated['reason'] ?? null);

        if (! $rental->payment_id) {
            try {
                $paymentService->createPaymentForRental($rental->fresh());
                Mail::to($rental->renter_email)->send(new RentalApprovedPayNow($rental->fresh()));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Business API approve: payment link failed', [
                    'rental_id' => $rental->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rental approved.',
            'data' => $this->rentalDetailPayload($rental->fresh()),
        ]);
    }

    /**
     * POST /api/v1/rentals/business/rentals/{rental}/reject
     */
    public function reject(Request $request, Rental $rental, RentalEscrowService $escrowService)
    {
        $business = $this->resolveBusinessOr403($request);
        if ((int) $rental->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        if (! $rental->isPending()) {
            return response()->json(['message' => 'Only pending rentals can be rejected.'], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $escrowService->refundAll($rental, $validated['reason'] ?? 'business_rejected');
        $rental->reject($validated['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Rental rejected.',
            'data' => $this->rentalDetailPayload($rental->fresh()),
        ]);
    }

    /**
     * POST /api/v1/rentals/business/rentals — walk-in / offline booking
     */
    public function store(Request $request, RentalInventoryService $inventoryService, RentalEscrowService $escrowService)
    {
        $business = $this->resolveBusinessOr403($request);
        /** @var \App\Models\Renter $hostRenter */
        $hostRenter = $request->user();

        $validated = $request->validate([
            'renter_name' => 'required|string|max:255',
            'renter_phone' => 'required|string|max:30',
            'renter_email' => 'nullable|email|max:255',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:rental_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selected_dates' => 'required|array|min:1',
            'items.*.selected_dates.*' => 'required|date|after_or_equal:today',
            'payment_note' => 'nullable|string|max:255',
            'total_amount' => 'nullable|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $check = $inventoryService->checkCartAvailability($validated['items']);
        if (! $check['ok']) {
            return $inventoryService->inventoryOversellResponse($check['unavailable']);
        }

        $itemIds = collect($validated['items'])->pluck('id')->all();
        $items = RentalItem::query()
            ->whereIn('id', $itemIds)
            ->where('business_id', $business->id)
            ->get()
            ->keyBy('id');

        if ($items->count() !== count(array_unique($itemIds))) {
            return response()->json(['message' => 'One or more items do not belong to your business.'], 422);
        }

        try {
            $rental = DB::transaction(function () use ($validated, $business, $hostRenter, $items, $inventoryService, $escrowService) {
            $recheck = $inventoryService->checkCartAvailability($validated['items']);
            if (! $recheck['ok']) {
                throw new \RuntimeException('INVENTORY_OVERSELL');
            }

            $firstEntry = $validated['items'][0];
            $selected = array_values(array_unique($firstEntry['selected_dates']));
            sort($selected);
            $startDate = Carbon::parse($selected[0]);
            $endDate = Carbon::parse($selected[array_key_last($selected)]);
            $days = count($selected);

            $totalAmount = (float) ($validated['total_amount'] ?? 0);
            $depositAmount = (float) ($validated['deposit_amount'] ?? 0);

            if ($totalAmount <= 0) {
                foreach ($validated['items'] as $entry) {
                    $item = $items[$entry['id']];
                    $entryDays = count(array_unique($entry['selected_dates']));
                    $totalAmount += $item->getRateForPeriod($entryDays) * (int) $entry['quantity'];
                }
            }

            $rental = Rental::query()->create([
                'renter_id' => $hostRenter->id,
                'business_id' => $business->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $days,
                'daily_rate' => $days > 0 ? $totalAmount / $days : $totalAmount,
                'total_amount' => $totalAmount,
                'deposit_amount' => $depositAmount,
                'currency' => 'NGN',
                'status' => Rental::STATUS_APPROVED,
                'approved_at' => now(),
                'is_walk_in' => true,
                'walk_in_payment_note' => $validated['payment_note'] ?? null,
                'renter_name' => $validated['renter_name'],
                'renter_email' => $validated['renter_email'] ?? ('walkin+'.uniqid().'@local.invalid'),
                'renter_phone' => $validated['renter_phone'],
                'business_phone' => $business->phone,
                'business_notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $entry) {
                $item = $items[$entry['id']];
                $entryDays = count(array_unique($entry['selected_dates']));
                $rate = $item->getRateForPeriod($entryDays);
                $rental->items()->attach($item->id, [
                    'quantity' => (int) $entry['quantity'],
                    'unit_rate' => $rate,
                    'total_amount' => $rate * (int) $entry['quantity'],
                ]);
            }

            $escrowService->holdForRental($rental);

            return $rental;
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'INVENTORY_OVERSELL') {
                return $inventoryService->inventoryOversellResponse(
                    $inventoryService->checkCartAvailability($validated['items'])['unavailable']
                );
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'data' => $this->rentalDetailPayload($rental->fresh(['items', 'business'])),
        ], 201);
    }

    /**
     * POST /api/v1/rentals/business/rentals/{rental}/condition-report
     */
    public function conditionReport(Request $request, Rental $rental)
    {
        $business = $this->resolveBusinessOr403($request);
        if ((int) $rental->business_id !== (int) $business->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'phase' => 'required|string|in:pickup,return',
            'notes' => 'nullable|string|max:2000',
            'images' => 'nullable|array',
            'images.*' => 'string|max:2048',
        ]);

        $images = $validated['images'] ?? [];
        if ($request->hasFile('images')) {
            foreach ((array) $request->file('images') as $file) {
                if ($file) {
                    $path = $file->store('rentals/condition-reports', 'public');
                    $images[] = Storage::disk('public')->url($path);
                }
            }
        }

        $report = RentalConditionReport::query()->create([
            'rental_id' => $rental->id,
            'submitted_by_business_id' => $business->id,
            'phase' => $validated['phase'],
            'notes' => $validated['notes'] ?? null,
            'images' => $images,
        ]);

        return response()->json(['success' => true, 'data' => $report], 201);
    }

    /**
     * POST /api/v1/rentals/business/rentals/{rental}/mark-picked-up
     */
    public function markPickedUp(Request $request, Rental $rental)
    {
        $business = $this->resolveBusinessOr403($request);
        if ((int) $rental->business_id !== (int) $business->id) {
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
        if ((int) $rental->business_id !== (int) $business->id) {
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

        if ($rental->fresh()->returned_at) {
            app(RentalEscrowService::class)->releaseRentToVendor($rental);
        }

        $rental->load(['items', 'business']);

        return response()->json([
            'message' => $rental->fresh()->returned_at ? 'Return completed.' : 'Return confirmed by business. Awaiting renter confirmation.',
            'data' => $this->rentalDetailPayload($rental->fresh()),
        ]);
    }
}

