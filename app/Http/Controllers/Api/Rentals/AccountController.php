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
     * POST /api/v1/rentals/me/profile
     * Update required renter profile fields.
     */
    public function updateProfile(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $renter->update([
            'phone' => $data['phone'],
        ]);

        return response()->json([
            'success' => true,
            'renter' => [
                'id' => $renter->id,
                'name' => $renter->name,
                'email' => $renter->email,
                'phone' => $renter->phone,
            ],
        ]);
    }

    /**
     * POST /api/v1/rentals/wallet/fund
     * Create a transfer payment to fund rentals wallet.
     */
    public function fundWallet(Request $request, \App\Services\PaymentService $paymentService)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $amount = (float) $validator->validated()['amount'];

        $businessId = \App\Models\Setting::get('wallet_funding_business_id', null);
        $business = $businessId ? \App\Models\Business::find($businessId) : \App\Models\Business::whereNotNull('id')->first();
        if (! $business) {
            return response()->json([
                'message' => 'Unable to generate transfer details right now. Please try again.',
            ], 422);
        }

        $payerName = $renter->verified_account_name ?: $renter->name;

        try {
            $payment = $paymentService->createPayment([
                'amount' => $amount,
                'payer_name' => $payerName,
                'webhook_url' => $business->webhook_url ?? '',
                'service' => 'rentals_wallet',
                'business_website_id' => null,
            ], $business, $request, false);

            $payment->update([
                'renter_id' => $renter->id,
                'expires_at' => now()->addHours(24),
            ]);

            $account = $payment->accountNumberDetails ?: \App\Models\AccountNumber::where('account_number', $payment->account_number)->first();

            return response()->json([
                'success' => true,
                'payment' => [
                    'transaction_id' => $payment->transaction_id,
                    'amount' => (float) $payment->amount,
                    'status' => $payment->status,
                    'account_number' => $payment->account_number,
                    'bank' => $payment->bank,
                    'bank_name' => $account?->bank_name,
                    'account_name' => $account?->account_name,
                    'payer_name' => $payment->payer_name,
                    'expires_at' => optional($payment->expires_at)->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unable to generate transfer details right now. Please try again.',
            ], 422);
        }
    }

    /**
     * POST /api/v1/rentals/wallet/fund/check
     * Trigger email monitoring and return latest wallet-funding payment status + updated wallet balance.
     */
    public function checkWalletFunding(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $validated = $request->validate([
            'transaction_id' => 'required|string|max:255',
        ]);

        // Run email monitoring to fetch and process new emails (may approve payment and credit wallet).
        \Illuminate\Support\Facades\Artisan::call('payment:monitor-emails');

        $payment = \App\Models\Payment::where('transaction_id', $validated['transaction_id'])
            ->where('renter_id', $renter->id)
            ->first();

        // Backward-compat: older wallet-fund payments may have renter_id missing due to fillable.
        if (! $payment) {
            $candidate = \App\Models\Payment::where('transaction_id', $validated['transaction_id'])->first();
            if ($candidate && ! $candidate->renter_id) {
                $businessId = \App\Models\Setting::get('wallet_funding_business_id', null);
                $fundingBusiness = $businessId ? \App\Models\Business::find($businessId) : null;

                $payer = strtolower(trim((string) ($candidate->payer_name ?? '')));
                $renterName = strtolower(trim((string) ($renter->verified_account_name ?: $renter->name)));

                $looksLikeWalletFund = (! $candidate->rental_id)
                    && ($fundingBusiness ? (int) $candidate->business_id === (int) $fundingBusiness->id : true)
                    && $payer !== ''
                    && $payer === $renterName
                    && $candidate->created_at
                    && $candidate->created_at->greaterThanOrEqualTo(now()->subDays(2));

                if ($looksLikeWalletFund) {
                    $candidate->update(['renter_id' => $renter->id]);
                    $payment = $candidate->fresh();
                }
            }
        }

        return response()->json([
            'success' => true,
            'payment' => $payment ? [
                'transaction_id' => $payment->transaction_id,
                'status' => $payment->status,
                'amount' => (float) $payment->amount,
                'received_amount' => $payment->received_amount !== null ? (float) $payment->received_amount : null,
                'matched_at' => $payment->matched_at?->toISOString(),
            ] : null,
            'wallet_balance' => (float) ($renter->fresh()->wallet_balance ?? 0.0),
        ]);
    }

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
     * Wallet summary for rentals renter (separate from business balance).
     */
    public function wallet(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $history = \App\Models\Payment::query()
            ->where('renter_id', $renter->id)
            // Wallet funding payments: renter_id set, rental_id null
            // Wallet debit payments (rentals paid via wallet): transaction_id starts with WLT- and renter_id set
            ->where(function ($q) {
                $q->whereNull('rental_id')
                    ->orWhere('transaction_id', 'like', 'WLT-%');
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (\App\Models\Payment $p) {
                $isDebit = (bool) $p->rental_id || (str_starts_with((string) $p->transaction_id, 'WLT-') && (float) $p->amount > 0);
                return [
                    'id' => $p->id,
                    'type' => $isDebit ? 'debit' : 'credit',
                    'amount' => (float) $p->amount,
                    'status' => $p->status,
                    'reference' => $p->transaction_id,
                    'created_at' => $p->created_at?->toISOString(),
                    'rental_id' => $p->rental_id,
                ];
            })
            ->values()
            ->all();

        $businessId = \App\Models\Setting::get('wallet_funding_business_id', null);
        $business = $businessId ? \App\Models\Business::find($businessId) : \App\Models\Business::whereNotNull('id')->first();
        $accountNumber = $business ? \App\Models\AccountNumber::where('business_id', $business->id)->active()->first() : null;

        return response()->json([
            'wallet' => (float) ($renter->wallet_balance ?? 0.0),
            'balance' => (float) ($renter->wallet_balance ?? 0.0),
            'history' => $history,
            'funding_account' => $accountNumber ? [
                'bank_name' => $accountNumber->bank_name,
                'account_number' => $accountNumber->account_number,
                'account_name' => $accountNumber->account_name,
            ] : null,
        ]);
    }
}

