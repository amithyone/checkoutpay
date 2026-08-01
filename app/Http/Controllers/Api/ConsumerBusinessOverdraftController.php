<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Consumer\ConsumerBusinessWalletLedgerService;
use App\Services\Credit\OverdraftEligibilityService;
use App\Services\Credit\OverdraftInstallmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsumerBusinessOverdraftController extends Controller
{
    public function __construct(
        private ConsumerBusinessWalletLedgerService $businessLedger,
        private OverdraftEligibilityService $eligibility,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $account = $request->user();
        $wallet = $account?->wallet;
        if (! $wallet) {
            return response()->json(['success' => false, 'message' => 'Wallet not found.'], 404);
        }

        $business = $this->businessLedger->resolveLinkedOrMatchedBusiness($wallet);
        if (! $business instanceof Business) {
            return response()->json(['success' => false, 'message' => 'Business wallet not linked.'], 422);
        }

        $this->eligibility->syncBusiness($business);
        $business = $business->fresh();
        $installments = $business->overdraftInstallments()->orderBy('sequence')->get();

        $balance = (float) $this->businessLedger->resolvedBalance($wallet);
        $available = (float) $business->getAvailableBalance();
        $limit = (float) $business->overdraft_limit;
        $overdraftUsed = $balance < 0 ? min($limit, abs($balance)) : 0.0;

        return response()->json([
            'success' => true,
            'data' => [
                'eligible' => (bool) $business->overdraft_eligible,
                'volume_90d' => (float) $business->overdraft_volume_90d,
                'volume_tier' => $business->overdraft_volume_tier,
                'tier_max_limit' => $this->eligibility->tierMaxLimit($business->overdraft_volume_tier),
                'can_apply' => $business->canApplyForOverdraft(),
                'status' => $business->overdraft_status ?? 'none',
                'limit' => $limit,
                'balance' => $balance,
                'available_balance' => $available,
                'overdraft_used' => $overdraftUsed,
                'repayment_mode' => $business->overdraft_repayment_mode,
                'installments' => $installments->map(fn ($row) => [
                    'sequence' => $row->sequence,
                    'amount' => (float) $row->amount,
                    'due_at' => optional($row->due_at)->toIso8601String(),
                    'status' => $row->status,
                ])->values(),
            ],
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $account = $request->user();
        $wallet = $account?->wallet;
        if (! $wallet) {
            return response()->json(['success' => false, 'message' => 'Wallet not found.'], 404);
        }

        $business = $this->businessLedger->resolveLinkedOrMatchedBusiness($wallet);
        if (! $business instanceof Business) {
            return response()->json(['success' => false, 'message' => 'Business wallet not linked.'], 422);
        }

        if (! $business->canApplyForOverdraft()) {
            return response()->json(['success' => false, 'message' => 'Not eligible to apply for overdraft.'], 422);
        }

        $validated = $request->validate([
            'overdraft_repayment_mode' => [
                'required',
                Rule::in([OverdraftInstallmentService::MODE_SINGLE, OverdraftInstallmentService::MODE_SPLIT_30D]),
            ],
            'overdraft_application_notes' => 'nullable|string|max:2000',
        ]);

        $business->update([
            'overdraft_status' => 'pending',
            'overdraft_requested_at' => now(),
            'overdraft_repayment_mode' => $validated['overdraft_repayment_mode'],
            'overdraft_application_notes' => $validated['overdraft_application_notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overdraft application submitted.',
        ]);
    }
}
