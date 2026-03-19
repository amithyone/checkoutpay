<?php

namespace App\Http\Controllers\Api\Rentals\Business;

use App\Http\Controllers\Api\Rentals\Business\Concerns\ResolvesBusiness;
use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalMavonPayPayoutService;
use Illuminate\Http\Request;

class WithdrawalsController extends Controller
{
    use ResolvesBusiness;

    /**
     * GET /api/v1/rentals/business/withdrawals
     */
    public function index(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        $rows = WithdrawalRequest::where('business_id', $business->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/rentals/business/withdrawals
     */
    public function store(Request $request)
    {
        $business = $this->resolveBusinessOr403($request);

        /** @var WithdrawalMavonPayPayoutService $payout */
        $payout = app(WithdrawalMavonPayPayoutService::class);

        $maxWithdraw = $business->getAvailableBalance();
        if ($maxWithdraw < 1) {
            return response()->json([
                'message' => 'Insufficient balance.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:' . max(0, $maxWithdraw),
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:30',
            'account_name' => 'required|string|max:255',
            'bank_code' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ]);

        $bankCode = $validated['bank_code'] ?? null;
        if ($payout->isMavonConfigured() && ! $payout->resolveBankCode($bankCode, $validated['bank_name'])) {
            return response()->json([
                'message' => 'Unable to determine bank code. Please select a bank (bank_code) and try again.',
            ], 422);
        }

        $withdrawal = WithdrawalRequest::create([
            'business_id' => $business->id,
            'amount' => $validated['amount'],
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'account_name' => $validated['account_name'],
            'notes' => $validated['notes'] ?? null,
            'status' => WithdrawalRequest::STATUS_PENDING,
        ]);

        $payout->processWithdrawal($withdrawal, $business, $bankCode);

        return response()->json([
            'data' => $withdrawal->fresh(),
        ], 201);
    }
}

