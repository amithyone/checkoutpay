<?php

namespace App\Http\Controllers\Api\Rentals\Business;

use App\Http\Controllers\Controller;
use App\Models\RentalVendorApplication;
use App\Models\Renter;
use Illuminate\Http\Request;

class VendorApplicationController extends Controller
{
    /**
     * POST /api/v1/rentals/business/apply
     */
    public function apply(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $existing = RentalVendorApplication::query()
            ->where('renter_id', $renter->id)
            ->whereIn('status', [RentalVendorApplication::STATUS_PENDING, RentalVendorApplication::STATUS_APPROVED])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => $existing->status === RentalVendorApplication::STATUS_APPROVED
                    ? 'You are already an approved vendor.'
                    : 'You already have a pending vendor application.',
            ], 422);
        }

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'address' => 'required|string|max:1000',
            'phone' => 'required|string|max:30',
            'description' => 'nullable|string|max:2000',
            'documents' => 'nullable|array',
            'documents.*.type' => 'required_with:documents|string|in:cac,id,other',
            'documents.*.url' => 'required_with:documents|string|max:2048',
        ]);

        $application = RentalVendorApplication::query()->create([
            'renter_id' => $renter->id,
            'business_name' => $validated['business_name'],
            'address' => $validated['address'],
            'phone' => $validated['phone'],
            'description' => $validated['description'] ?? null,
            'documents' => $validated['documents'] ?? [],
            'status' => RentalVendorApplication::STATUS_PENDING,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $application->id,
                'status' => $application->status,
                'business_name' => $application->business_name,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /api/v1/rentals/business/application
     */
    public function show(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $application = RentalVendorApplication::query()
            ->where('renter_id', $renter->id)
            ->latest('id')
            ->first();

        if (! $application) {
            return response()->json([
                'success' => false,
                'message' => 'No vendor application found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $application->id,
                'status' => $application->status,
                'business_name' => $application->business_name,
                'rejection_reason' => $application->rejection_reason,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'reviewed_at' => $application->reviewed_at?->toIso8601String(),
            ],
        ]);
    }
}
