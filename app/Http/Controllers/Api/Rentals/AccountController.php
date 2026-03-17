<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Renter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{
    /**
     * POST /api/v1/rentals/password/change
     * Change password for the authenticated renter (and linked user/business).
     */
    public function changePassword(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $currentPassword = $data['current_password'];

        $passwordMatches = Hash::check($currentPassword, $renter->password);

        // Fallback checks against linked user/business if needed
        if (! $passwordMatches) {
            $user = User::where('email', $renter->email)->first();
            if ($user && Hash::check($currentPassword, $user->password)) {
                $passwordMatches = true;
            } else {
                $business = Business::where('email', $renter->email)->first();
                if ($business && Hash::check($currentPassword, $business->password)) {
                    $passwordMatches = true;
                }
            }
        }

        if (! $passwordMatches) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $newHash = Hash::make($data['password']);

        // Update renter password
        $renter->update(['password' => $newHash]);

        // Mirror to linked user/business where applicable
        $user = User::where('email', $renter->email)->first();
        if ($user) {
            $user->update(['password' => $newHash]);
        }

        $business = Business::where('email', $renter->email)->first();
        if ($business) {
            $business->update(['password' => $newHash]);
        }

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * GET /api/v1/rentals/wallet
     * Simple wallet summary for linked My Account user.
     */
    public function wallet(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $user = User::where('email', $renter->email)->first();

        return response()->json([
            'balance' => $user ? (float) $user->wallet_bal : 0.0,
        ]);
    }
}

