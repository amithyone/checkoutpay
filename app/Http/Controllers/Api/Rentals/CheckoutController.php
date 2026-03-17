<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Mail\RentalApprovedPayNow;
use App\Mail\RentalReceipt;
use App\Mail\RentalRequestReceived;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Renter;
use App\Services\RentalPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckoutController extends Controller
{
    /**
     * POST /api/v1/rentals/checkout/quote
     * Compute totals for a cart + dates without creating rentals.
     */
    public function quote(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:rental_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selected_dates' => 'required|array|min:1',
            'items.*.selected_dates.*' => 'required|date|after_or_equal:today',
        ]);

        $itemIds = collect($validated['items'])->pluck('id')->all();
        $items = RentalItem::whereIn('id', $itemIds)
            ->with(['business', 'category'])
            ->get()
            ->keyBy('id');

        $totalAmount = 0;
        $businesses = [];

        foreach ($validated['items'] as $entry) {
            $item = $items[$entry['id']] ?? null;
            if (! $item) {
                continue;
            }

            $selected = array_values(array_unique($entry['selected_dates']));
            sort($selected);

            $startDate = \Carbon\Carbon::parse($selected[0]);
            $endDate = \Carbon\Carbon::parse($selected[array_key_last($selected)]);
            $days = count($selected);

            $rate = $item->getRateForPeriod($days);
            $itemTotal = $rate * $entry['quantity'];

            $totalAmount += $itemTotal;

            if (! isset($businesses[$item->business_id])) {
                $businesses[$item->business_id] = [
                    'business' => $item->business,
                    'items' => [],
                    'total' => 0,
                ];
            }

            $businesses[$item->business_id]['items'][] = [
                'item' => $item,
                'quantity' => $entry['quantity'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $days,
                'rate' => $rate,
                'total' => $itemTotal,
            ];
            $businesses[$item->business_id]['total'] += $itemTotal;
        }

        return response()->json([
            'total_amount' => $totalAmount,
            'businesses' => array_values(array_map(function ($data) {
                return [
                    'business' => [
                        'id' => $data['business']->id,
                        'name' => $data['business']->name,
                        'phone' => $data['business']->phone,
                    ],
                    'total' => $data['total'],
                ];
            }, $businesses)),
        ]);
    }

    /**
     * POST /api/v1/rentals/checkout/submit
     * Create rentals for authenticated renter.
     */
    public function submit(Request $request, RentalPaymentService $paymentService)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if (! $renter->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email first.',
            ], 422);
        }

        if (! $renter->isKycVerified()) {
            return response()->json([
                'message' => 'Please complete KYC verification before submitting a rental.',
            ], 422);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:rental_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selected_dates' => 'required|array|min:1',
            'items.*.selected_dates.*' => 'required|date|after_or_equal:today',
            'renter_notes' => 'nullable|string|max:1000',
        ]);

        $itemIds = collect($validated['items'])->pluck('id')->all();
        $items = RentalItem::whereIn('id', $itemIds)
            ->with(['business', 'category'])
            ->get()
            ->keyBy('id');

        $businesses = [];

        foreach ($validated['items'] as $entry) {
            $item = $items[$entry['id']] ?? null;
            if (! $item) {
                continue;
            }

            $selected = array_values(array_unique($entry['selected_dates']));
            sort($selected);

            $startDate = \Carbon\Carbon::parse($selected[0]);
            $endDate = \Carbon\Carbon::parse($selected[array_key_last($selected)]);
            $days = count($selected);

            $rate = $item->getRateForPeriod($days);
            $itemTotal = $rate * $entry['quantity'];

            if (! isset($businesses[$item->business_id])) {
                $businesses[$item->business_id] = [
                    'business' => $item->business,
                    'items' => [],
                    'total' => 0,
                ];
            }

            $businesses[$item->business_id]['items'][] = [
                'item' => $item,
                'quantity' => $entry['quantity'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days' => $days,
                'rate' => $rate,
                'total' => $itemTotal,
            ];
            $businesses[$item->business_id]['total'] += $itemTotal;
        }

        if (empty($businesses)) {
            return response()->json([
                'message' => 'No valid items in request.',
            ], 422);
        }

        $createdRentals = [];

        try {
            foreach ($businesses as $businessData) {
                $business = $businessData['business'];
                $businessItems = $businessData['items'];

                $businessTotal = 0;
                $totalDays = 0;
                foreach ($businessItems as $itemData) {
                    $businessTotal += $itemData['total'];
                    $totalDays = max($totalDays, $itemData['days']);
                }

                $rental = Rental::create([
                    'renter_id' => $renter->id,
                    'business_id' => $business->id,
                    'start_date' => $businessItems[0]['start_date'],
                    'end_date' => $businessItems[0]['end_date'],
                    'days' => $totalDays,
                    'daily_rate' => $businessTotal / $totalDays,
                    'total_amount' => $businessTotal,
                    'deposit_amount' => 0,
                    'currency' => 'NGN',
                    'status' => Rental::STATUS_PENDING,
                    'verified_account_number' => $renter->verified_account_number,
                    'verified_account_name' => $renter->verified_account_name,
                    'verified_bank_name' => $renter->verified_bank_name,
                    'verified_bank_code' => $renter->verified_bank_code,
                    'renter_name' => $renter->name,
                    'renter_email' => $renter->email,
                    'renter_phone' => $renter->phone,
                    'renter_address' => $renter->address,
                    'business_phone' => $business->phone,
                    'renter_notes' => $validated['renter_notes'] ?? null,
                ]);

                foreach ($businessItems as $itemData) {
                    $rental->items()->attach($itemData['item']->id, [
                        'quantity' => $itemData['quantity'],
                        'unit_rate' => $itemData['rate'],
                        'total_amount' => $itemData['total'],
                    ]);
                }

                Mail::to($business->email)->send(new RentalRequestReceived($rental));
                Mail::to($renter->email)->send(new RentalReceipt($rental));

                if ($business->rental_auto_approve ?? false) {
                    $rental->approve();
                    try {
                        $paymentService->createPaymentForRental($rental->fresh());
                        Mail::to($rental->renter_email)->send(new RentalApprovedPayNow($rental->fresh()));
                    } catch (\Exception $e) {
                        Log::error('Rental auto-approve (API): failed to create payment or send pay link', [
                            'rental_id' => $rental->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $createdRentals[] = $rental->fresh('items', 'business');
            }
        } catch (\Exception $e) {
            Log::error('Failed to submit rental request via API', [
                'renter_id' => $renter->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to submit rental request.',
            ], 500);
        }

        return response()->json([
            'message' => 'Rental request submitted successfully.',
            'rentals' => $createdRentals,
        ], 201);
    }

    /**
     * GET /api/v1/rentals/requests
     */
    public function listRentals(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $rentals = Rental::with(['business', 'items'])
            ->where('renter_id', $renter->id)
            ->latest()
            ->paginate(20);

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
     * GET /api/v1/rentals/requests/{rental}
     */
    public function showRental(Request $request, Rental $rental)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ($rental->renter_id !== $renter->id) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        $rental->load(['business', 'items']);

        return response()->json([
            'data' => $rental,
        ]);
    }
}

