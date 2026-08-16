<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WithdrawalRequest;
use App\Models\Bank;
use App\Models\Business;
use App\Models\WithdrawalRequest as WithdrawalRequestModel;
use App\Services\TransactionLogService;
use App\Services\WithdrawalMavonPayPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WithdrawalController extends Controller
{
    private const WITHDRAWAL_BLOCKED_MESSAGE = 'Withdrawal could not be processed. Please try again shortly.';

    public function __construct(
        protected TransactionLogService $logService,
        protected WithdrawalMavonPayPayoutService $payout,
    ) {}

    /**
     * Create a withdrawal and pay out instantly (admin must enable Payout API).
     */
    public function store(WithdrawalRequest $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->user();

        if ($denied = $this->gatePayoutApi($business)) {
            return $denied;
        }

        $cooldownKey = "withdrawal:cooldown:business:{$business->id}";
        $lockKey = "withdrawal:submit-lock:business:{$business->id}";

        if (Cache::has($cooldownKey)) {
            return response()->json([
                'success' => false,
                'message' => self::WITHDRAWAL_BLOCKED_MESSAGE,
            ], 429);
        }

        $maxWithdraw = $business->getAvailableBalance();
        if ($request->amount > $maxWithdraw) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
                'available_balance' => (float) $maxWithdraw,
            ], 400);
        }

        $bankCode = $request->input('bank_code');
        if ($this->payout->isMavonConfigured() && ! $this->payout->resolveBankCode($bankCode, (string) $request->bank_name)) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to determine bank code. Pass bank_code from GET /api/v1/banks and try again.',
            ], 422);
        }

        if (! Cache::add($lockKey, true, now()->addSeconds(30))) {
            return response()->json([
                'success' => false,
                'message' => self::WITHDRAWAL_BLOCKED_MESSAGE,
            ], 429);
        }

        $withdrawal = WithdrawalRequestModel::create([
            'business_id' => $business->id,
            'amount' => $request->amount,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'bank_name' => $request->bank_name,
            'notes' => $request->input('notes'),
            'bank_narration' => filled($request->input('bank_narration'))
                ? trim((string) $request->input('bank_narration'))
                : null,
            'status' => WithdrawalRequestModel::STATUS_PENDING,
            'source' => WithdrawalRequestModel::SOURCE_PAYOUT_API,
        ]);

        $this->logService->logWithdrawalRequest($withdrawal, $request);

        $this->payout->processWithdrawal($withdrawal, $business, $bankCode);
        $withdrawal = $withdrawal->fresh();

        Cache::put($cooldownKey, true, now()->addMinutes(WithdrawalMavonPayPayoutService::COOLDOWN_MINUTES));
        Cache::forget($lockKey);

        return response()->json([
            'success' => true,
            'message' => $this->payout->merchantSummaryMessage($withdrawal),
            'data' => $this->withdrawalPayload($withdrawal),
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
            'data' => $withdrawals->map(fn ($withdrawal) => $this->withdrawalPayload($withdrawal)),
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
                'available_balance' => (float) $business->getAvailableBalance(),
                'currency' => 'NGN',
            ],
        ]);
    }

    /**
     * NIP bank directory for payout bank_code.
     */
    public function banks(Request $request): JsonResponse
    {
        /** @var Business $business */
        $business = $request->user();

        if ($denied = $this->gatePayoutApi($business)) {
            return $denied;
        }

        $banks = Bank::query()
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (Bank $bank) => [
                'code' => (string) $bank->code,
                'name' => (string) $bank->name,
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $banks,
        ]);
    }

    private function gatePayoutApi(Business $business): ?JsonResponse
    {
        if (! $business->payout_api_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'Payout API is not enabled for this merchant. Ask Checkout support to enable it on your business account.',
            ], 403);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function withdrawalPayload(WithdrawalRequestModel $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'amount' => (float) $withdrawal->amount,
            'status' => $withdrawal->status,
            'source' => $withdrawal->source,
            'payout_status' => $withdrawal->payout_status,
            'payout_reference' => $withdrawal->payout_reference,
            'payout_response_message' => app(\App\Services\Payout\MerchantPayoutMessageSanitizer::class)
                ->forWithdrawal($withdrawal),
            'account_number' => $withdrawal->account_number,
            'account_name' => $withdrawal->account_name,
            'bank_name' => $withdrawal->bank_name,
            'rejection_reason' => $withdrawal->rejection_reason,
            'created_at' => $withdrawal->created_at?->toISOString(),
            'processed_at' => $withdrawal->processed_at?->toISOString(),
        ];
    }
}
