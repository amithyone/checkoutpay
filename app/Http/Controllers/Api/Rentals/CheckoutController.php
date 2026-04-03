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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckoutController extends Controller
{
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
        $depositAmount = 0;
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
            $globalEnabled = (bool) ($item->business?->rental_global_caution_fee_enabled ?? false);
            $globalPercent = (float) ($item->business?->rental_global_caution_fee_percent ?? 0);
            $cautionPercent = $globalEnabled
                ? $globalPercent
                : ($item->caution_fee_enabled ? (float) $item->caution_fee_percent : 0.0);
            $itemCaution = $cautionPercent > 0 ? round(($itemTotal * $cautionPercent) / 100, 2) : 0.0;

            $totalAmount += $itemTotal;
            $depositAmount += $itemCaution;

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
            'deposit_amount' => $depositAmount,
            'grand_total' => $totalAmount + $depositAmount,
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
        $payerName = $renter->verified_account_name ?: $renter->name;

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
            'payment_method' => 'nullable|string|in:wallet,transfer',
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
            $globalEnabled = (bool) ($item->business?->rental_global_caution_fee_enabled ?? false);
            $globalPercent = (float) ($item->business?->rental_global_caution_fee_percent ?? 0);
            $cautionPercent = $globalEnabled
                ? $globalPercent
                : ($item->caution_fee_enabled ? (float) $item->caution_fee_percent : 0.0);
            $itemCaution = $cautionPercent > 0 ? round(($itemTotal * $cautionPercent) / 100, 2) : 0.0;

            if (! isset($businesses[$item->business_id])) {
                $businesses[$item->business_id] = [
                    'business' => $item->business,
                    'items' => [],
                    'total' => 0,
                    'deposit_total' => 0,
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
                'deposit' => $itemCaution,
            ];
            $businesses[$item->business_id]['total'] += $itemTotal;
            $businesses[$item->business_id]['deposit_total'] += $itemCaution;
        }

        if (empty($businesses)) {
            return response()->json([
                'message' => 'No valid items in request.',
            ], 422);
        }

        $paymentMethod = $request->input('payment_method');
        $grandTotal = collect($businesses)->sum('total') + collect($businesses)->sum('deposit_total');

        if ($paymentMethod === 'wallet') {
            if ((float) ($renter->wallet_balance ?? 0.0) < $grandTotal) {
                return response()->json([
                    'message' => 'Insufficient wallet balance.',
                ], 422);
            }
        }

        $createdRentals = [];

        try {
            $createdRentals = DB::transaction(function () use ($businesses, $paymentMethod, $renter, $validated, $paymentService, $payerName) {
                $created = [];

                foreach ($businesses as $businessData) {
                    $business = $businessData['business'];
                    $businessItems = $businessData['items'];

                $businessTotal = 0;
                $businessDeposit = 0;
                $totalDays = 0;
                foreach ($businessItems as $itemData) {
                    $businessTotal += $itemData['total'];
                    $businessDeposit += $itemData['deposit'] ?? 0;
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
                    'deposit_amount' => $businessDeposit,
                    'currency' => 'NGN',
                    'status' => Rental::STATUS_PENDING,
                    'verified_account_number' => $renter->verified_account_number,
                    'verified_account_name' => $renter->verified_account_name,
                    'verified_bank_name' => $renter->verified_bank_name,
                    'verified_bank_code' => $renter->verified_bank_code,
                    'renter_name' => $payerName,
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

                if ($paymentMethod === 'wallet') {
                    $payable = $businessTotal + $businessDeposit;
                    $renter->wallet_balance = (float) ($renter->wallet_balance ?? 0.0) - $payable;
                    $renter->save();
                    try {
                        app(\App\Services\PushNotificationService::class)->notifyRenter(
                            (int) $renter->id,
                            'Wallet debited',
                            'Your wallet was debited for a rental payment.',
                            [
                                'type' => 'wallet_debit',
                                'amount' => (string) $payable,
                            ]
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Push notify failed for wallet debit', [
                            'renter_id' => $renter->id,
                            'amount' => $payable,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $payment = \App\Models\Payment::create([
                        'transaction_id' => 'WLT-' . strtoupper(uniqid()),
                        'amount' => $payable,
                        'payer_name' => $payerName,
                        'business_id' => $business->id,
                        'renter_id' => $renter->id,
                        'status' => \App\Models\Payment::STATUS_APPROVED,
                        'service' => 'rental',
                        'rental_id' => $rental->id,
                    ]);
                    
                    $rental->update([
                        'payment_id' => $payment->id,
                        'status' => Rental::STATUS_APPROVED
                    ]);

                    Mail::to($business->email)->send(new RentalRequestReceived($rental->fresh()));
                    Mail::to($renter->email)->send(new RentalReceipt($rental->fresh()));
                } else {
                    $assignedPayment = null;

                    if ($paymentMethod === 'transfer') {
                        $newNotes = trim(($rental->renter_notes ?? '') . "\n\n[System: User indicated intent to pay via Direct Transfer]");
                        $rental->update(['renter_notes' => $newNotes]);

                        // Must be live: if we can't generate transfer details, fail the request.
                        // Include deposit in payable amount by temporarily reflecting it in total_amount.
                        // RentalPaymentService uses total_amount for payment creation.
                        $rental->update(['total_amount' => $businessTotal + $businessDeposit]);
                        $assignedPayment = $paymentService->createPaymentForRental($rental->fresh());
                    }

                    Mail::to($business->email)->send(new RentalRequestReceived($rental));
                    Mail::to($renter->email)->send(new RentalReceipt($rental));

                    if ($business->rental_auto_approve ?? false) {
                        $rental->approve();
                        try {
                            if (!$assignedPayment && !$rental->payment_id) {
                                $paymentService = app(\App\Services\RentalPaymentService::class);
                                $assignedPayment = $paymentService->createPaymentForRental($rental->fresh());
                            }
                            Mail::to($rental->renter_email)->send(new RentalApprovedPayNow($rental->fresh()));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Rental auto-approve (API): failed to create payment or send pay link', [
                                'rental_id' => $rental->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $rentalData = $rental->fresh('items', 'business')->toArray();

                // Provide both payment options (external + internal) when available.
                $rental->loadMissing(['payment.accountNumberDetails', 'secondaryPayment.accountNumberDetails']);

                $primaryPayment = $rental->payment;
                $secondaryPayment = $rental->secondaryPayment;

                if ($primaryPayment) {
                    $rentalData['payment_instructions'] = [
                        'account_number' => $primaryPayment->account_number,
                        'bank' => $primaryPayment->accountNumberDetails->bank_name ?? null,
                        'account_name' => $primaryPayment->accountNumberDetails->account_name ?? null,
                        'amount' => (float) $primaryPayment->amount,
                        'transaction_id' => $primaryPayment->transaction_id,
                        'payment_source' => $primaryPayment->payment_source,
                    ];
                }

                if ($secondaryPayment) {
                    $rentalData['payment_instructions_secondary'] = [
                        'account_number' => $secondaryPayment->account_number,
                        'bank' => $secondaryPayment->accountNumberDetails->bank_name ?? null,
                        'account_name' => $secondaryPayment->accountNumberDetails->account_name ?? null,
                        'amount' => (float) $secondaryPayment->amount,
                        'transaction_id' => $secondaryPayment->transaction_id,
                        'payment_source' => $secondaryPayment->payment_source,
                    ];
                }
                $created[] = $rentalData;
            }

                return $created;
            });
        } catch (\Exception $e) {
            Log::error('Failed to submit rental request via API', [
                'renter_id' => $renter->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $paymentMethod === 'transfer'
                    ? 'Unable to generate live transfer details right now. Please try again or use Wallet.'
                    : 'Failed to submit rental request.',
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

    /**
     * POST /api/v1/rentals/requests/{rental}/check-payment
     * Trigger bank/email matching and return latest payment + rental status.
     */
    public function checkPayment(Request $request, Rental $rental)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ($rental->renter_id !== $renter->id) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        if (! $rental->payment_id && ! $rental->secondary_payment_id) {
            return response()->json([
                'message' => 'No payment attached to this rental.',
            ], 422);
        }

        // Run email monitoring to fetch and process new emails (may approve payment).
        Artisan::call('payment:monitor-emails');

        $rental->load(['payment', 'secondaryPayment']);
        $payment = $rental->payment;
        $secondaryPayment = $rental->secondaryPayment;

        if (
            $rental->status === Rental::STATUS_PENDING
            && (
                ($payment && $payment->status === \App\Models\Payment::STATUS_APPROVED)
                || ($secondaryPayment && $secondaryPayment->status === \App\Models\Payment::STATUS_APPROVED)
            )
        ) {
            $rental->approve('Auto-approved after payment was confirmed.');
            $rental->refresh();
        }

        return response()->json([
            'rental' => [
                'id' => $rental->id,
                'rental_number' => $rental->rental_number,
                'status' => $rental->status,
            ],
            'payment' => $payment ? [
                'id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'received_amount' => $payment->received_amount !== null ? (float) $payment->received_amount : null,
                'matched_at' => $payment->matched_at?->toISOString(),
            ] : null,
            'secondary_payment' => $secondaryPayment ? [
                'id' => $secondaryPayment->id,
                'transaction_id' => $secondaryPayment->transaction_id,
                'status' => $secondaryPayment->status,
                'amount' => (float) $secondaryPayment->amount,
                'received_amount' => $secondaryPayment->received_amount !== null ? (float) $secondaryPayment->received_amount : null,
                'matched_at' => $secondaryPayment->matched_at?->toISOString(),
            ] : null,
        ]);
    }

    /**
     * POST /api/v1/rentals/requests/{rental}/request-return
     * Renter signals return; business must also confirm to finalize.
     */
    public function requestReturn(Request $request, Rental $rental)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ($rental->renter_id !== $renter->id) {
            return response()->json([
                'message' => 'Not found.',
            ], 404);
        }

        if (! in_array($rental->status, [Rental::STATUS_APPROVED, Rental::STATUS_ACTIVE, Rental::STATUS_COMPLETED], true)) {
            return response()->json([
                'message' => 'This rental is not eligible for return yet.',
            ], 422);
        }

        if (! $rental->renter_return_requested_at) {
            $rental->update(['renter_return_requested_at' => now()]);
        }

        $this->maybeFinalizeReturn($rental);

        $rental->load(['business', 'items']);

        return response()->json([
            'message' => $rental->fresh()->returned_at
                ? 'Return completed.'
                : 'Return requested. Awaiting business confirmation.',
            'data' => $rental->fresh(),
        ]);
    }

    /**
     * POST /api/v1/rentals/requests/{rental}/fulfillment
     * Save renter pickup vs delivery choice after payment.
     */
    public function setFulfillment(Request $request, Rental $rental)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ($rental->renter_id !== $renter->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'fulfillment_method' => 'required|string|in:pickup,delivery',
            'delivery_address' => 'nullable|string|max:1000',
        ]);

        if ($validated['fulfillment_method'] === 'delivery' && empty(trim((string) ($validated['delivery_address'] ?? '')))) {
            return response()->json([
                'message' => 'Delivery address is required when delivery is selected.',
            ], 422);
        }

        $rental->update([
            'fulfillment_method' => $validated['fulfillment_method'],
            'delivery_address' => $validated['fulfillment_method'] === 'delivery'
                ? trim((string) $validated['delivery_address'])
                : null,
        ]);

        $rental->load(['business', 'items']);

        return response()->json([
            'message' => 'Fulfillment preference saved.',
            'data' => $rental->fresh(),
        ]);
    }

    /**
     * POST /api/v1/rentals/requests/{rental}/return-method
     * Save return logistics preference.
     */
    public function setReturnMethod(Request $request, Rental $rental)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ($rental->renter_id !== $renter->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'return_method' => 'required|string|in:pickup_return,rider_return',
        ]);

        $rental->update([
            'return_method' => $validated['return_method'],
        ]);

        $rental->load(['business', 'items']);

        return response()->json([
            'message' => 'Return method saved.',
            'data' => $rental->fresh(),
        ]);
    }
}

