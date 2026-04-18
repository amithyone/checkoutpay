<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\Renter;
use App\Services\Rentals\RenterPortalAccountBridge;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            'phone' => 'required|string|max:20',
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
            'phone' => $validated['phone'],
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

        $email = RenterPortalAccountBridge::normalizedEmail($validated['email']);
        $renter = Renter::where('email', $email)->first();

        if ($renter && Hash::check($validated['password'], $renter->password)) {
            // direct renter login
        } else {
            $renter = RenterPortalAccountBridge::linkRenterWithPasswordFromUserOrBusiness(
                $validated['email'],
                $validated['password']
            );
            if (! $renter) {
                return response()->json([
                    'message' => 'The provided credentials are incorrect.',
                ], 401);
            }
        }

        if (! (bool) $renter->is_active) {
            return response()->json([
                'message' => 'Your renter account is disabled. Please contact support.',
            ], 403);
        }

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

        $business = RenterPortalAccountBridge::businessLinkedToRenterEmail($renter->email);

        return response()->json([
            'renter' => $this->renterResource($renter),
            'is_business' => (bool) $business,
            'business' => $business ? [
                'id' => $business->id,
                'business_id' => $business->business_id ?? null,
                'name' => $business->name ?? null,
                'address' => $business->address ?? null,
            ] : null,
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

    /**
     * POST /api/v1/rentals/me/email/resend-verification
     */
    public function resendEmailVerification(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if ($renter->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Your email is already verified.',
            ]);
        }

        $renter->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification email sent. Please check your inbox (and spam).',
        ]);
    }

    /**
     * POST /api/v1/rentals/me/email/verify-pin
     */
    public function verifyEmailPin(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $validated = $request->validate([
            'pin' => 'required|digits:6',
        ]);

        if ($renter->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Your email is already verified.',
                'renter' => $this->renterResource($renter),
            ]);
        }

        $cacheKey = 'renter_email_verification_pin_'.$renter->getKey();
        $cachedPin = Cache::get($cacheKey);

        if (! $cachedPin || (string) $cachedPin !== (string) $validated['pin']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        if ($renter->markEmailAsVerified()) {
            event(new Verified($renter));
        }

        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
            'renter' => $this->renterResource($renter->fresh()),
        ]);
    }

    protected function renterResource(Renter $renter): array
    {
        $rentCount = \App\Models\Rental::query()
            ->where('renter_id', $renter->id)
            ->where('status', \App\Models\Rental::STATUS_COMPLETED)
            ->count();

        $linkedBusinessId = RenterPortalAccountBridge::businessLinkedToRenterEmail($renter->email)?->id;

        $salesCount = $linkedBusinessId
            ? \App\Models\Rental::query()
                ->where('business_id', $linkedBusinessId)
                ->where('status', \App\Models\Rental::STATUS_COMPLETED)
                ->count()
            : 0;

        $rentScore = min(100, $rentCount * 5);
        $salesScore = min(100, $salesCount * 5);

        return [
            'id' => $renter->id,
            'name' => $renter->name,
            'email' => $renter->email,
            'is_active' => (bool) $renter->is_active,
            'phone' => $renter->phone,
            'address' => $renter->address,
            'email_verified' => (bool) $renter->email_verified_at,
            'kyc_verified' => $renter->isKycVerified(),
            'bank_kyc_verified' => (bool) ($renter->kyc_verified_at && $renter->verified_account_number && $renter->verified_account_name),
            'verified_account_number' => $renter->verified_account_number,
            'verified_account_name' => $renter->verified_account_name,
            'verified_bank_name' => $renter->verified_bank_name,
            'verified_bank_code' => $renter->verified_bank_code,
            'rubies_account_number' => $renter->rubies_account_number,
            'rubies_account_name' => $renter->rubies_account_name,
            'rubies_bank_name' => $renter->rubies_bank_name,
            'rubies_bank_code' => $renter->rubies_bank_code,
            'rubies_reference' => $renter->rubies_reference,
            'rubies_account_created_at' => optional($renter->rubies_account_created_at)?->toIso8601String(),
            'bvn' => $renter->bvn,
            'age' => $renter->age,
            'instagram_url' => $renter->instagram_url,
            'wallet_balance' => (float) ($renter->wallet_balance ?? 0.0),
            'kyc_id_uploaded' => (bool) ($renter->kyc_id_front_path && $renter->kyc_id_back_path),
            'kyc_id_status' => $renter->kyc_id_status,
            'trust' => [
                'rent_count' => (int) $rentCount,
                'sales_count' => (int) $salesCount,
                'rent_score' => (int) $rentScore,
                'sales_score' => (int) $salesScore,
            ],
        ];
    }
}
