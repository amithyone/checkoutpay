<?php

namespace App\Http\Controllers\Api\Rentals\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Business;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\RentalVendorApplication;
use App\Models\Renter;
use App\Models\WithdrawalRequest;
use App\Services\Rentals\RentalCatalogFormatter;
use App\Services\Rentals\RentalEscrowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RentalsAdminController extends Controller
{
    public function __construct(
        protected RentalEscrowService $escrowService
    ) {}

    /**
     * GET /api/v1/rentals/admin/kyc-queue
     */
    public function kycQueue(Request $request)
    {
        $renters = Renter::query()
            ->where(function ($q) {
                $q->whereNotNull('kyc_id_front_path')
                    ->orWhereNotNull('kyc_id_back_path')
                    ->orWhereNotNull('kyc_id_card_path');
            })
            ->where(function ($q) {
                $q->whereNull('kyc_id_status')
                    ->orWhere('kyc_id_status', Renter::KYC_ID_STATUS_PENDING);
            })
            ->latest('updated_at')
            ->paginate(30);

        return response()->json(['success' => true, 'data' => $renters->items(), 'meta' => [
            'current_page' => $renters->currentPage(),
            'total' => $renters->total(),
        ]]);
    }

    public function approveKyc(Renter $userId)
    {
        $renter = $userId;
        $renter->update([
            'kyc_id_status' => Renter::KYC_ID_STATUS_APPROVED,
            'kyc_id_reviewed_at' => now(),
            'kyc_id_reviewed_by' => Auth::id(),
            'kyc_id_rejection_reason' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'KYC approved.']);
    }

    public function rejectKyc(Request $request, Renter $userId)
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:1000']);
        $userId->update([
            'kyc_id_status' => Renter::KYC_ID_STATUS_REJECTED,
            'kyc_id_reviewed_at' => now(),
            'kyc_id_reviewed_by' => Auth::id(),
            'kyc_id_rejection_reason' => $validated['reason'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'KYC rejected.']);
    }

    /**
     * GET /api/v1/rentals/admin/vendor-applications
     */
    public function vendorApplications(Request $request)
    {
        $status = $request->query('status', RentalVendorApplication::STATUS_PENDING);
        $apps = RentalVendorApplication::query()
            ->with('renter')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(30);

        return response()->json(['success' => true, 'data' => $apps->items(), 'meta' => [
            'current_page' => $apps->currentPage(),
            'total' => $apps->total(),
        ]]);
    }

    public function approveVendorApplication(RentalVendorApplication $id)
    {
        $application = $id;
        $renter = $application->renter;
        if (! $renter) {
            return response()->json(['success' => false, 'message' => 'Renter not found.'], 404);
        }

        $business = Business::query()->whereRaw('LOWER(email) = LOWER(?)', [$renter->email])->first();

        if (! $business) {
            $business = Business::query()->create([
                'name' => $application->business_name,
                'email' => $renter->email,
                'password' => $renter->password ?: Hash::make(Str::random(32)),
                'phone' => $application->phone,
                'address' => $application->address,
                'is_active' => true,
                'business_id' => 'RENT-'.strtoupper(Str::random(8)),
            ]);
        }

        $application->update([
            'status' => RentalVendorApplication::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
            'business_id' => $business->id,
            'rejection_reason' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Vendor approved.', 'data' => $application->fresh()]);
    }

    public function rejectVendorApplication(Request $request, RentalVendorApplication $id)
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:1000']);
        $id->update([
            'status' => RentalVendorApplication::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
            'rejection_reason' => $validated['reason'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'Vendor application rejected.']);
    }

    public function forceComplete(Rental $id)
    {
        $id->update([
            'status' => Rental::STATUS_COMPLETED,
            'completed_at' => now(),
            'returned_at' => $id->returned_at ?? now(),
        ]);
        $this->escrowService->releaseRentToVendor($id);
        $this->escrowService->releaseDepositToRenter($id);

        return response()->json(['success' => true, 'data' => $id->fresh()]);
    }

    public function forceCancel(Request $request, Rental $id)
    {
        $validated = $request->validate(['reason' => 'nullable|string|max:1000']);
        $this->escrowService->refundAll($id, $validated['reason'] ?? 'admin_force_cancel');
        $id->update([
            'status' => Rental::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $validated['reason'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $id->fresh()]);
    }

    public function refund(Request $request, Rental $id)
    {
        return app(\App\Http\Controllers\Api\Rentals\RentalRequestActionsController::class)
            ->refund($request, $id);
    }

    public function payouts(Request $request)
    {
        $rows = WithdrawalRequest::query()->with('business')->latest()->paginate(30);

        return response()->json(['success' => true, 'data' => $rows->items(), 'meta' => [
            'current_page' => $rows->currentPage(),
            'total' => $rows->total(),
        ]]);
    }

    public function holdPayout(WithdrawalRequest $id)
    {
        $id->update(['status' => WithdrawalRequest::STATUS_REJECTED]);

        return response()->json(['success' => true, 'message' => 'Payout held/rejected.']);
    }

    /**
     * GET /api/v1/rentals/admin/featured
     * Admin view of featured slider items (includes inactive/unavailable for editing).
     */
    public function featuredItems(Request $request)
    {
        $items = RentalItem::query()
            ->with(['business', 'category'])
            ->withTrashed()
            ->where('is_featured', true)
            ->orderByRaw('featured_sort IS NULL, featured_sort ASC')
            ->orderBy('id')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items->map(fn (RentalItem $item) => RentalCatalogFormatter::featuredSlide($item))->values()->all(),
        ]);
    }

    /**
     * PATCH /api/v1/rentals/admin/items/{item}
     * Update featured slider fields (and basic availability flags).
     */
    public function updateItem(Request $request, RentalItem $item)
    {
        $validated = $request->validate([
            'is_featured' => 'sometimes|boolean',
            'featured_tag' => 'sometimes|nullable|string|max:120',
            'featured_sort' => 'sometimes|nullable|integer|min:1|max:9999',
            'is_active' => 'sometimes|boolean',
            'is_available' => 'sometimes|boolean',
        ]);

        $payload = array_merge($validated, RentalItem::featuredFieldsFromRequest($request));

        if (array_key_exists('is_featured', $payload) && ! $payload['is_featured']) {
            $payload['featured_tag'] = null;
            $payload['featured_sort'] = null;
        }

        $item->update($payload);
        $item->load(['business', 'category']);

        return response()->json([
            'success' => true,
            'data' => RentalCatalogFormatter::catalogItem($item),
        ]);
    }
}
