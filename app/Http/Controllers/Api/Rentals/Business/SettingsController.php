<?php

namespace App\Http\Controllers\Api\Rentals\Business;

use App\Http\Controllers\Api\Rentals\Business\Concerns\ResolvesBusiness;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    use ResolvesBusiness;

    /**
     * GET /api/v1/rentals/business/settings
     */
    public function show(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        return response()->json([
            'data' => [
                'address' => $business->address ?? null,
                'rental_global_caution_fee_enabled' => (bool) ($business->rental_global_caution_fee_enabled ?? false),
                'rental_global_caution_fee_percent' => (float) ($business->rental_global_caution_fee_percent ?? 0),
                'has_withdrawal_pin' => ! empty($business->withdrawal_pin_hash),
            ],
        ]);
    }

    /**
     * PATCH /api/v1/rentals/business/settings
     */
    public function update(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        $validated = $request->validate([
            'address' => 'sometimes|required|string|max:1000',
            'rental_global_caution_fee_enabled' => 'sometimes|boolean',
            'rental_global_caution_fee_percent' => 'sometimes|numeric|min:0|max:100',
        ]);

        if (array_key_exists('rental_global_caution_fee_enabled', $validated) && ! $validated['rental_global_caution_fee_enabled']) {
            $validated['rental_global_caution_fee_percent'] = 0;
        }

        $business->update($validated);

        return response()->json([
            'data' => [
                'address' => $business->address ?? null,
                'rental_global_caution_fee_enabled' => (bool) ($business->rental_global_caution_fee_enabled ?? false),
                'rental_global_caution_fee_percent' => (float) ($business->rental_global_caution_fee_percent ?? 0),
                'has_withdrawal_pin' => ! empty($business->withdrawal_pin_hash),
            ],
        ]);
    }

    /**
     * POST /api/v1/rentals/business/settings/withdrawal-pin
     */
    public function setWithdrawalPin(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        $validated = $request->validate([
            'pin' => 'required|digits:4',
            'confirm_pin' => 'required|digits:4|same:pin',
            'current_pin' => 'nullable|digits:4',
        ]);

        if (! empty($business->withdrawal_pin_hash)) {
            $current = (string) ($validated['current_pin'] ?? '');
            if ($current === '' || ! Hash::check($current, (string) $business->withdrawal_pin_hash)) {
                return response()->json([
                    'message' => 'Current withdrawal PIN is incorrect.',
                ], 422);
            }
        }

        $business->update([
            'withdrawal_pin_hash' => Hash::make((string) $validated['pin']),
            'withdrawal_pin_set_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal PIN saved.',
            'has_withdrawal_pin' => true,
        ]);
    }

    /**
     * POST /api/v1/rentals/business/settings/withdrawal-pin/verify
     */
    public function verifyWithdrawalPin(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        $validated = $request->validate([
            'pin' => 'required|digits:4',
        ]);

        if (empty($business->withdrawal_pin_hash)) {
            return response()->json([
                'message' => 'Withdrawal PIN is not set yet.',
                'has_withdrawal_pin' => false,
            ], 422);
        }

        $ok = Hash::check((string) $validated['pin'], (string) $business->withdrawal_pin_hash);

        if (! $ok) {
            return response()->json([
                'message' => 'Incorrect withdrawal PIN.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'PIN verified.',
            'has_withdrawal_pin' => true,
        ]);
    }
}

