<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Api\Rentals\Concerns\FormatsRentalDetail;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalConditionReport;
use App\Models\RentalDispute;
use App\Models\Renter;
use App\Services\Rentals\RentalEscrowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RentalRequestActionsController extends Controller
{
    use FormatsRentalDetail;

    public function __construct(
        protected RentalEscrowService $escrowService
    ) {}

    /**
     * POST /api/v1/rentals/requests/{rental}/cancel
     */
    public function cancel(Request $request, Rental $rental)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ($rental->renter_id !== $renter->id) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        if (! $this->escrowService->isCancellable($rental)) {
            return response()->json([
                'success' => false,
                'message' => 'This rental cannot be cancelled.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $this->escrowService->refundAll($rental, $validated['reason'] ?? 'renter_cancelled');

        $rental->update([
            'status' => Rental::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rental cancelled and refund processed.',
            'data' => $this->rentalDetailPayload($rental->fresh()),
        ]);
    }

    /**
     * POST /api/v1/rentals/requests/{rental}/refund
     */
    public function refund(Request $request, Rental $rental)
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'type' => 'required|string|in:full,partial,deposit_only',
            'reason' => 'required|string|max:1000',
        ]);

        $type = $validated['type'];

        if ($type === 'full') {
            $this->escrowService->refundAll($rental, $validated['reason']);
        } elseif ($type === 'deposit_only') {
            $this->escrowService->releaseDepositToRenter($rental, 0);
        } else {
            $amount = (float) ($validated['amount'] ?? 0);
            if ($amount <= 0) {
                return response()->json(['success' => false, 'message' => 'Amount required for partial refund.'], 422);
            }
            if ($rental->renter_id) {
                $renter = Renter::query()->find($rental->renter_id);
                if ($renter) {
                    $renter->wallet_balance = (float) ($renter->wallet_balance ?? 0) + $amount;
                    $renter->save();
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Refund processed.',
            'data' => $this->rentalDetailPayload($rental->fresh()),
        ]);
    }

    /**
     * POST /api/v1/rentals/requests/{rental}/condition-report
     */
    public function conditionReport(Request $request, Rental $rental)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ($rental->renter_id !== $renter->id) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'phase' => 'required|string|in:pickup,return',
            'notes' => 'nullable|string|max:2000',
            'images' => 'nullable|array',
            'images.*' => 'string|max:2048',
            'images_files' => 'nullable|array',
            'images_files.*' => 'image|max:4096',
        ]);

        $images = $validated['images'] ?? [];

        if ($request->hasFile('images_files')) {
            foreach ($request->file('images_files') as $file) {
                $path = $file->store('rentals/condition-reports', 'public');
                $images[] = Storage::disk('public')->url($path);
            }
        }

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
            'submitted_by_renter_id' => $renter->id,
            'phase' => $validated['phase'],
            'notes' => $validated['notes'] ?? null,
            'images' => $images,
        ]);

        return response()->json([
            'success' => true,
            'data' => $report,
        ], 201);
    }

    /**
     * POST /api/v1/rentals/requests/{rental}/disputes
     */
    public function openDispute(Request $request, Rental $rental)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ($rental->renter_id !== $renter->id) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'reason' => 'required|string|in:damage,missing,late,other',
            'description' => 'required|string|max:5000',
            'requested_deposit_capture' => 'nullable|numeric|min:0',
        ]);

        $dispute = RentalDispute::query()->create([
            'rental_id' => $rental->id,
            'opened_by_renter_id' => $renter->id,
            'reason' => $validated['reason'],
            'description' => $validated['description'],
            'requested_deposit_capture' => (float) ($validated['requested_deposit_capture'] ?? 0),
            'status' => RentalDispute::STATUS_OPEN,
        ]);

        $this->escrowService->freezeForDispute($rental);

        return response()->json([
            'success' => true,
            'data' => $dispute,
        ], 201);
    }

    /**
     * GET /api/v1/rentals/requests/{rental}/disputes
     */
    public function listDisputes(Request $request, Rental $rental)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ($rental->renter_id !== $renter->id) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $disputes = RentalDispute::query()
            ->where('rental_id', $rental->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $disputes,
        ]);
    }

    /**
     * POST /api/v1/rentals/disputes/{dispute}/resolve
     */
    public function resolveDispute(Request $request, RentalDispute $dispute)
    {
        $validated = $request->validate([
            'resolution' => 'required|string|in:release_deposit,capture_partial,capture_full',
            'capture_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        if ($validated['resolution'] === 'capture_partial' && ! isset($validated['capture_amount'])) {
            return response()->json([
                'success' => false,
                'message' => 'capture_amount is required for capture_partial.',
            ], 422);
        }

        $this->escrowService->resolveDispute(
            $dispute,
            $validated['resolution'],
            (float) ($validated['capture_amount'] ?? 0),
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $dispute->fresh(),
        ]);
    }
}
