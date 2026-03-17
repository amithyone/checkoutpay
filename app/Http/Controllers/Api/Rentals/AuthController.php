<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\Renter;
use App\Models\User;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * POST /api/v1/rentals/auth/register
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:renters,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $renter = Renter::create([
            'name' => $validated['name'] ?? $validated['email'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => true,
        ]);

        $token = $renter->createToken('rentals-spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'renter' => $this->renterResource($renter),
        ], 201);
    }

    /**
     * POST /api/v1/rentals/auth/login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // 1) Try direct renter login first
        $renter = Renter::where('email', $validated['email'])->first();

        if (! $renter || ! Hash::check($validated['password'], $renter->password)) {
            // 2) Fallback: try existing My Account user
            $user = User::where('email', $validated['email'])->first();
            if ($user && Hash::check($validated['password'], $user->password)) {
                $renter = Renter::firstOrCreate(
                    ['email' => strtolower($user->email)],
                    [
                        'name' => $user->name ?? $user->email,
                        'email_verified_at' => $user->email_verified_at ?? null,
                        'password' => $user->password, // keep same hash
                    ]
                );
            } else {
                // 3) Fallback: try business account
                $business = Business::where('email', $validated['email'])->first();
                if ($business && Hash::check($validated['password'], $business->password)) {
                    $renter = Renter::firstOrCreate(
                        ['email' => strtolower($business->email)],
                        [
                            'name' => $business->name ?? $business->email,
                            'email_verified_at' => $business->email_verified_at ?? null,
                            'password' => $business->password,
                        ]
                    );
                }
            }

            // If renter is still null here, credentials are invalid everywhere
            if (! $renter) {
                return response()->json([
                    'message' => 'The provided credentials are incorrect.',
                ], 401);
            }
        }

        // Previously we blocked inactive renters here.
        // For rentals login we now allow all valid accounts to sign in.

        $token = $renter->createToken('rentals-spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'renter' => $this->renterResource($renter),
        ]);
    }

    /**
     * GET /api/v1/rentals/me
     */
    public function me(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        return response()->json([
            'renter' => $this->renterResource($renter),
        ]);
    }

    /**
     * POST /api/v1/rentals/auth/logout
     */
    public function logout(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ($renter && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    protected function renterResource(Renter $renter): array
    {
        return [
            'id' => $renter->id,
            'name' => $renter->name,
            'email' => $renter->email,
            'phone' => $renter->phone,
            'address' => $renter->address,
            'email_verified' => (bool) $renter->email_verified_at,
            'kyc_verified' => $renter->isKycVerified(),
        ];
    }
}

