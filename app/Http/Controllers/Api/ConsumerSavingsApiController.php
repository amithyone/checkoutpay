<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consumer\StoreSavingsGoalRequest;
use App\Http\Requests\Consumer\UpdateSavingsGoalRequest;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WalletSavingsLock;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletSavingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerSavingsApiController extends Controller
{
    public function __construct(
        private ConsumerWalletSavingsService $savings,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);

        return response()->json([
            'ok' => true,
            'data' => $this->savings->getSummary($wallet),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $validated = $request->validate([
            'spend_to_save_enabled' => 'sometimes|boolean',
            'spend_to_save_percent' => 'sometimes|numeric|min:0|max:100',
            'strict_save_enabled' => 'sometimes|boolean',
            'strict_save_percent' => 'sometimes|numeric|min:0|max:100',
            'strict_ledger_scope' => 'sometimes|string|in:personal,business,both',
            'strict_collection_mode' => 'sometimes|string|in:per_incoming,balance_threshold',
            'strict_balance_threshold' => 'sometimes|nullable|numeric|min:0',
            'reminder_enabled' => 'sometimes|boolean',
            'reminder_frequency' => 'sometimes|string|in:off,weekly,after_spend',
            'reminder_weekday' => 'sometimes|nullable|integer|min:0|max:6',
            'reminder_hour_local' => 'sometimes|nullable|integer|min:0|max:23',
        ]);

        $result = $this->savings->updateSettings($wallet, $validated);
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'Could not update settings.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => ['settings' => $result['settings']],
        ]);
    }

    public function goals(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);

        return response()->json([
            'ok' => true,
            'data' => [
                'goals' => $this->savings->getSummary($wallet)['goals'],
            ],
        ]);
    }

    public function previewGoal(Request $request): JsonResponse
    {
        $this->walletFor($request);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:120',
            'target_amount' => 'required|numeric|min:100',
            'saved_amount' => 'sometimes|numeric|min:0',
            'save_type' => 'sometimes|string|in:flexible,strict',
            'target_date' => 'sometimes|nullable|date',
            'duration_days' => 'sometimes|nullable|integer|min:1|max:3650',
            'collection_mode' => 'sometimes|string|in:manual,per_incoming,balance_threshold',
            'auto_save_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'balance_threshold' => 'sometimes|nullable|numeric|min:0',
            'ledger_scope' => 'sometimes|string|in:personal,business,both',
        ]);

        return response()->json([
            'ok' => true,
            'data' => $this->savings->previewGoalPlan($validated),
        ]);
    }

    public function storeGoal(StoreSavingsGoalRequest $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $result = $this->savings->createGoal($wallet, $request->validated());
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'Could not create goal.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => ['goal' => $result['goal']],
        ], 201);
    }

    public function patchGoal(UpdateSavingsGoalRequest $request, int $goalId): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $result = $this->savings->updateGoal($wallet, $goalId, $request->validated());
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'Could not update goal.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'data' => ['goal' => $result['goal']],
        ]);
    }

    public function deposit(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'goal_id' => 'sometimes|nullable|integer|min:1',
            'lock_type' => 'sometimes|string|in:flexible,locked',
            'ledger_scope' => 'sometimes|string|in:personal,business',
        ]);

        $lockType = $validated['lock_type'] ?? WalletSavingsLock::LOCK_TYPE_LOCKED;
        $ledgerScope = $validated['ledger_scope'] ?? 'personal';

        $result = $this->savings->lockDeposit(
            $wallet,
            (float) $validated['amount'],
            WalletSavingsLock::SOURCE_MANUAL,
            isset($validated['goal_id']) ? (int) $validated['goal_id'] : null,
            null,
            $lockType,
            $ledgerScope,
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'Could not save.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => $result['message'] ?? 'Saved.',
            'data' => ['lock' => $result['lock']],
        ], 201);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'ledger_scope' => 'sometimes|string|in:personal,business',
        ]);

        $result = $this->savings->withdrawFlexible(
            $wallet,
            (float) $validated['amount'],
            $validated['ledger_scope'] ?? null,
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'Could not withdraw.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => $result['message'] ?? 'Withdrawn.',
            'data' => [
                'amount' => $result['amount'],
                'ledger_scope' => $result['ledger_scope'],
                'forfeited_bonus' => (bool) ($result['forfeited_bonus'] ?? false),
            ],
        ]);
    }

    public function locks(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $limit = min(50, max(1, (int) $request->query('limit', 30)));

        return response()->json([
            'ok' => true,
            'data' => [
                'locks' => $this->savings->listLocks($wallet, $limit),
            ],
        ]);
    }

    private function walletFor(Request $request): WhatsappWallet
    {
        $user = $request->user();
        abort_unless($user instanceof ConsumerWalletApiAccount, 401);

        $wallet = WhatsappWallet::query()
            ->where('id', $user->whatsapp_wallet_id)
            ->where('status', WhatsappWallet::STATUS_ACTIVE)
            ->firstOrFail();

        return $wallet;
    }
}
