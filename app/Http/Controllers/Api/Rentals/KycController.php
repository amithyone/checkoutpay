<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Renter;
use App\Services\MevonPayBankService;
use App\Services\NubanValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class KycController extends Controller
{
    public function __construct(
        protected NubanValidationService $nubanService,
        protected MevonPayBankService $mevonBankService,
    ) {}

    /**
     * POST /api/v1/rentals/kyc/banks
     * Get possible banks for the given account number from NUBAN API.
     * This mirrors how CheckoutPay discovers banks without hardcoding.
     */
    public function banksForAccount(Request $request)
    {
        $validated = $request->validate([
            'account_number' => 'required|string|size:10',
        ]);

        // Prefer NUBAN possible banks for this specific account (faster), MevonPay is not used here to avoid latency.
        $banks = $this->nubanService->getPossibleBanks($validated['account_number']);

        // Cache/update banks in our own DB so they are instantly available to frontends
        if (is_array($banks)) {
            foreach ($banks as $bank) {
                $code = $bank['bankCode'] ?? $bank['bank_code'] ?? $bank['code'] ?? null;
                $name = $bank['bankName'] ?? $bank['name'] ?? $bank['bank_name'] ?? null;
                if (! $code || ! $name) {
                    continue;
                }
                Bank::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name],
                );
            }
        }

        return response()->json([
            'banks' => $banks,
        ]);
    }

    /**
     * GET /api/v1/rentals/banks
     * Return all known banks from the Checkout database (populated from NUBAN).
     */
    public function banksFromDatabase()
    {
        $banks = Cache::remember('rentals:banks:list:v1', now()->addHours(6), function () {
            return Bank::query()
                ->orderBy('name')
                ->get(['code', 'name'])
                ->toArray();
        });

        return response()->json([
            'banks' => $banks,
        ]);
    }

    /**
     * POST /api/v1/rentals/kyc/verify
     * Public AJAX-style verification of account number.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'account_number' => 'required|string|size:10',
            'bank_code' => 'required|string',
            'bvn' => 'nullable|digits:11',
            'age' => 'nullable|integer|min:18|max:120',
            'instagram_url' => 'nullable|url|max:255',
        ]);

        // Prefer NUBAN validation (faster and already integrated), fallback to MevonPay name enquiry only if NUBAN fails.
        $result = $this->nubanService->validate($validated['account_number'], $validated['bank_code']);
        if (! $result || ! isset($result['account_name'])) {
            $result = $this->mevonBankService->nameEnquiry($validated['bank_code'], $validated['account_number']);
        }

        if (! $result || ! isset($result['account_name'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify account. Please check your account number and bank.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'account_name' => $result['account_name'],
            'bank_name' => $result['bank_name'] ?? null,
        ]);
    }

    /**
     * POST /api/v1/rentals/me/kyc-id
     * Upload government-issued ID (front and back) with type and attach to renter profile.
     *
     * Expected multipart/form-data:
     * - id_type: nin | drivers_license | passport | voters_card
     * - id_front: jpeg/png image (front)
     * - id_back: jpeg/png image (back)
     */
    public function uploadId(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $validated = $request->validate([
            'id_type' => 'required|string|in:nin,drivers_license,passport,voters_card',
            'id_front' => 'required|file|mimes:jpeg,jpg,png|max:5120',
            'id_back' => 'required|file|mimes:jpeg,jpg,png|max:5120',
        ]);

        $prefix = $renter->id ? "renters/{$renter->id}" : 'renters';

        $frontPath = $request->file('id_front')->store("rentals/kyc/{$prefix}", 'public');
        $backPath = $request->file('id_back')->store("rentals/kyc/{$prefix}", 'public');

        $renter->update([
            'kyc_id_type' => $validated['id_type'],
            'kyc_id_front_path' => $frontPath,
            'kyc_id_back_path' => $backPath,
            'kyc_id_status' => \App\Models\Renter::KYC_ID_STATUS_PENDING,
            'kyc_id_reviewed_at' => null,
            'kyc_id_reviewed_by' => null,
            'kyc_id_rejection_reason' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ID card uploaded successfully.',
            'renter' => [
                'id' => $renter->id,
                'name' => $renter->name,
                'email' => $renter->email,
                'kyc_id_type' => $renter->kyc_id_type,
            ],
        ]);
    }

    /**
     * POST /api/v1/rentals/me/kyc
     * Authenticated renter KYC update (without file upload for now).
     */
    public function update(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $validated = $request->validate([
            'account_number' => 'required|string|size:10',
            'bank_code' => 'required|string',
        ]);

        // Prefer NUBAN validation for renter KYC as well, fallback to MevonPay only if NUBAN fails.
        $result = $this->nubanService->validate($validated['account_number'], $validated['bank_code']);
        if (! $result || ! isset($result['account_name'])) {
            $result = $this->mevonBankService->nameEnquiry($validated['bank_code'], $validated['account_number']);
        }

        if (! $result || ! isset($result['account_name'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify account. Please check your account number and bank.',
            ], 422);
        }

        $renter->update([
            'verified_account_number' => $validated['account_number'],
            'verified_account_name' => $result['account_name'],
            'verified_bank_name' => $result['bank_name'] ?? null,
            'verified_bank_code' => $result['bank_code'] ?? $validated['bank_code'],
            'bvn' => $validated['bvn'] ?? $renter->bvn,
            'age' => $validated['age'] ?? $renter->age,
            'instagram_url' => $validated['instagram_url'] ?? $renter->instagram_url,
            'kyc_verified_at' => now(),
            'name' => $result['account_name'],
        ]);

        return response()->json([
            'success' => true,
            'renter' => [
                'id' => $renter->id,
                'name' => $renter->name,
                'email' => $renter->email,
                'kyc_verified' => $renter->isKycVerified(),
            ],
        ]);
    }
}
