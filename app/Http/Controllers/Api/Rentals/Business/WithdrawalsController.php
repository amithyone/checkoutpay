<?php

namespace App\Http\Controllers\Api\Rentals\Business;

use App\Http\Controllers\Api\Rentals\Business\Concerns\ResolvesBusiness;
use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\WithdrawalRequest;
use App\Services\MavonPayTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        /** @var MavonPayTransferService $mavon */
        $mavon = app(MavonPayTransferService::class);
        $mavonConfigured = $mavon->isConfigured();

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
        if (! $bankCode && $mavonConfigured) {
            $bank = Bank::query()
                ->whereRaw('LOWER(name) = LOWER(?)', [$validated['bank_name']])
                ->first();
            $bankCode = $bank?->code;
        }

        if (! $bankCode && $mavonConfigured) {
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
            'payout_provider' => MavonPayTransferService::PROVIDER,
            'payout_status' => 'not_started',
        ]);

        // If MavonPay is not configured (or temporarily disabled), we fall back to manual processing.
        if (! $mavonConfigured) {
            $withdrawal->update([
                'payout_status' => 'failed',
                'payout_response_message' => 'Instant transfer is not available right now. Your withdrawal request has been submitted for manual processing.',
                'payout_attempted_at' => now(),
                'payout_failed_at' => now(),
            ]);

            return response()->json([
                'data' => $withdrawal->fresh(),
            ], 201);
        }

        $reference = 'wd_' . $withdrawal->id . '_' . Str::lower(Str::random(10));
        $sessionId = 'WD' . $withdrawal->id . '_' . now()->format('YmdHis');

        $result = $mavon->createTransfer([
            'amount' => (float) $withdrawal->amount,
            'bankCode' => $bankCode,
            'bankName' => $withdrawal->bank_name,
            'creditAccountName' => $withdrawal->account_name,
            'creditAccountNumber' => $withdrawal->account_number,
            'narration' => 'Business withdrawal',
            'reference' => $reference,
            'sessionId' => $sessionId,
        ]);

        $withdrawal->update([
            'payout_reference' => $reference,
            'payout_response_code' => $result['response_code'] ?? null,
            'payout_response_message' => $result['response_message'] ?? null,
            'payout_raw_response' => $result['raw'] ?? null,
            'payout_attempted_at' => now(),
            'payout_status' => $result['bucket'] ?? 'failed',
            'payout_failed_at' => ($result['bucket'] ?? null) === MavonPayTransferService::BUCKET_FAILED ? now() : null,
            'payout_succeeded_at' => ($result['bucket'] ?? null) === MavonPayTransferService::BUCKET_SUCCESSFUL ? now() : null,
            'status' => ($result['bucket'] ?? null) === MavonPayTransferService::BUCKET_SUCCESSFUL
                ? WithdrawalRequest::STATUS_PROCESSED
                : WithdrawalRequest::STATUS_PENDING,
        ]);

        // If the instant transfer succeeded, deduct from business balance immediately.
        if (($result['bucket'] ?? null) === MavonPayTransferService::BUCKET_SUCCESSFUL) {
            $business->decrement('balance', $withdrawal->amount);
        }

        return response()->json([
            'data' => $withdrawal->fresh(),
        ], 201);
    }
}

