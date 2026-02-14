<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawalRequest;
use App\Models\WithdrawalRequest as WithdrawalRequestModel;
use App\Services\TransactionLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(
        protected TransactionLogService $logService
    ) {}

    /**
     * Create a withdrawal request
     */
    public function store(WithdrawalRequest $request): JsonResponse
    {
        $business = $request->user();

        // Check if business has sufficient balance
        if ($business->balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
                'available_balance' => (float) $business->balance,
            ], 400);
        }

        $withdrawal = WithdrawalRequestModel::create([
            'business_id' => $business->id,
            'amount' => $request->amount,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'bank_name' => $request->bank_name,
            'status' => WithdrawalRequestModel::STATUS_PENDING,
        ]);

        // Log withdrawal request
        $this->logService->logWithdrawalRequest($withdrawal, $request);

        // Send notification to business
        $business->notify(new \App\Notifications\WithdrawalRequestedNotification($withdrawal));

        // Notify admin (Telegram + email)
        app(\App\Services\AdminWithdrawalAlertService::class)->send($withdrawal);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request submitted successfully',
            'data' => [
                'id' => $withdrawal->id,
                'amount' => (float) $withdrawal->amount,
                'status' => $withdrawal->status,
                'created_at' => $withdrawal->created_at->toISOString(),
            ],
        ], 201);
    }

    /**
     * Get withdrawal requests for authenticated business
     */
    public function index(Request $request): JsonResponse
    {
        $business = $request->user();

        $query = WithdrawalRequestModel::where('business_id', $business->id)
            ->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $withdrawals->map(function ($withdrawal) {
                return [
                    'id' => $withdrawal->id,
                    'amount' => (float) $withdrawal->amount,
                    'account_number' => $withdrawal->account_number,
                    'account_name' => $withdrawal->account_name,
                    'bank_name' => $withdrawal->bank_name,
                    'status' => $withdrawal->status,
                    'rejection_reason' => $withdrawal->rejection_reason,
                    'created_at' => $withdrawal->created_at->toISOString(),
                    'processed_at' => $withdrawal->processed_at?->toISOString(),
                ];
            }),
            'meta' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
            ],
        ]);
    }

    /**
     * Get business balance
     */
    public function balance(Request $request): JsonResponse
    {
        $business = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => (float) $business->balance,
                'currency' => 'NGN',
            ],
        ]);
    }
}
