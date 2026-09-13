<?php

namespace App\Http\Controllers\Api\Xtrapay;

use App\Http\Controllers\Controller;
use App\Models\Xtrapay\XtrapayBank;
use App\Models\Xtrapay\XtrapayBeneficiary;
use App\Models\Xtrapay\XtrapayTransaction;
use App\Models\Xtrapay\XtrapayTransfer;
use App\Models\Xtrapay\XtrapayUser;
use App\Models\Xtrapay\XtrapayWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AppController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        /** @var XtrapayUser $user */
        $user = $request->user();
        $wallets = $user->wallets()->get();
        $personal = $wallets->firstWhere('kind', 'personal') ?? $wallets->first();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->toProfileArray(),
                'wallets' => $wallets->map->toApiArray()->values(),
                'selectedWalletId' => $personal?->id,
                'limits' => [
                    'dailySpendCap' => (float) $user->daily_limit,
                    'dailySpent' => (float) $user->daily_spent,
                    'singleTxnCap' => (float) $user->single_txn_cap,
                    'transferCap' => (float) $user->transfer_cap,
                    'posFloatCap' => (float) $user->pos_float_cap,
                ],
                'savings' => [
                    'flexibleBalance' => (float) $user->flexible_savings,
                    'strictBalance' => (float) $user->strict_savings,
                    'strictAutoSave' => (bool) $user->strict_auto_save,
                ],
                'overdraftLimit' => (float) $user->overdraft_limit,
                'recentTransactions' => $user->transactions()
                    ->orderByDesc('occurred_at')
                    ->limit(20)
                    ->get()
                    ->map->toApiArray()
                    ->values(),
                'cardFrozen' => (bool) $user->card_frozen,
                'xpointsBalance' => (float) $user->xpoints_balance,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->toProfileArray(),
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        /** @var XtrapayUser $user */
        $user = $request->user();
        $data = $request->validate([
            'fullName' => 'sometimes|string|max:120',
            'email' => 'sometimes|email',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:255',
            'preferences' => 'sometimes|array',
        ]);

        $user->fill([
            'full_name' => $data['fullName'] ?? $user->full_name,
            'email' => $data['email'] ?? $user->email,
            'phone' => $data['phone'] ?? $user->phone,
            'address' => $data['address'] ?? $user->address,
            'preferences' => $data['preferences'] ?? $user->preferences,
        ])->save();

        return response()->json(['success' => true, 'data' => $user->toProfileArray()]);
    }

    public function wallets(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->wallets()->get()->map->toApiArray()->values(),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query('limit', 40)));
        $rows = $request->user()->transactions()
            ->when($request->query('category'), function ($q) use ($request) {
                $q->where('category', $request->query('category'));
            })
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function banks(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => XtrapayBank::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function nameEnquiry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'accountNumber' => 'required|string|min:10|max:10',
            'bankCode' => 'nullable|string',
            'bankName' => 'nullable|string',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'accountNumber' => $data['accountNumber'],
                'accountName' => 'ADEKUNLE OLUMIDE',
                'bankName' => $data['bankName'] ?? 'Access Bank Plc',
            ],
        ]);
    }

    public function beneficiaries(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->beneficiaries()->get()->map->toApiArray()->values(),
        ]);
    }

    public function limits(Request $request): JsonResponse
    {
        /** @var XtrapayUser $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'dailySpendCap' => (float) $user->daily_limit,
                'dailySpent' => (float) $user->daily_spent,
                'singleTxnCap' => (float) $user->single_txn_cap,
                'transferCap' => (float) $user->transfer_cap,
                'posFloatCap' => (float) $user->pos_float_cap,
            ],
        ]);
    }

    public function updateLimits(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dailySpendCap' => 'sometimes|numeric|min:1000',
            'singleTxnCap' => 'sometimes|numeric|min:1000',
            'transferCap' => 'sometimes|numeric|min:1000',
            'posFloatCap' => 'sometimes|numeric|min:1000',
            'pin' => 'required|string|size:4',
        ]);

        /** @var XtrapayUser $user */
        $user = $request->user();
        $demo = (string) env('XTRAPAY_DEMO_PIN', '1234');
        if ($data['pin'] !== $demo && ! ($user->pin_hash && \Hash::check($data['pin'], $user->pin_hash))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN'], 422);
        }

        $user->fill([
            'daily_limit' => $data['dailySpendCap'] ?? $user->daily_limit,
            'single_txn_cap' => $data['singleTxnCap'] ?? $user->single_txn_cap,
            'transfer_cap' => $data['transferCap'] ?? $user->transfer_cap,
            'pos_float_cap' => $data['posFloatCap'] ?? $user->pos_float_cap,
        ])->save();

        return $this->limits($request);
    }

    public function createTransfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'walletId' => 'nullable|string',
            'accountNumber' => 'required|string|min:10',
            'bankName' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'recipientName' => 'required|string',
            'narration' => 'nullable|string|max:255',
            'pin' => 'required|string|size:4',
        ]);

        /** @var XtrapayUser $user */
        $user = $request->user();
        $demo = (string) env('XTRAPAY_DEMO_PIN', '1234');
        if ($data['pin'] !== $demo && ! ($user->pin_hash && \Hash::check($data['pin'], $user->pin_hash))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN'], 422);
        }

        $wallet = $data['walletId']
            ? $user->wallets()->where('id', $data['walletId'])->first()
            : $user->wallets()->where('kind', 'personal')->first();

        if (! $wallet || $wallet->balance < $data['amount']) {
            return response()->json(['success' => false, 'message' => 'Insufficient funds'], 422);
        }

        $now = now();
        $ref = 'XTR-'.random_int(10000000, 99999999);
        $transferId = 'tr-'.Str::uuid();

        $wallet->balance = $wallet->balance - $data['amount'];
        $wallet->save();

        $user->daily_spent = $user->daily_spent + $data['amount'];
        $user->save();

        $transfer = XtrapayTransfer::create([
            'id' => $transferId,
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'step' => 3,
            'amount' => $data['amount'],
            'recipient_name' => $data['recipientName'],
            'bank_name' => $data['bankName'],
            'account_number' => $data['accountNumber'],
            'reference' => $ref,
            'narration' => $data['narration'] ?? null,
            'init_time' => $now->format('H:i:s'),
            'processed_time' => $now->copy()->addSeconds(1)->format('H:i:s'),
            'settled_time' => $now->copy()->addSeconds(2)->format('H:i:s'),
            'is_complete' => true,
        ]);

        XtrapayTransaction::create([
            'id' => 'tx-'.Str::uuid(),
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'title' => 'Transfer to '.$data['recipientName'],
            'subtitle' => $data['bankName'].' • '.$now->format('H:i'),
            'occurred_at' => $now,
            'amount' => $data['amount'],
            'type' => 'debit',
            'status' => 'Settled',
            'category' => 'transfer',
            'reference' => $ref,
            'bank' => $data['bankName'],
            'recipient' => $data['recipientName'],
            'note' => $data['narration'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $transfer->toApiArray(),
        ], 201);
    }

    public function showTransfer(string $id, Request $request): JsonResponse
    {
        $transfer = XtrapayTransfer::where('user_id', $request->user()->id)->where('id', $id)->firstOrFail();

        return response()->json(['success' => true, 'data' => $transfer->toApiArray()]);
    }

    public function networkRails(): JsonResponse
    {
        $rails = [
            ['id' => 'nip', 'name' => 'NIP Instant', 'backend' => 'NIBSS · interbank', 'status' => 'Active', 'successRate' => 99.2, 'uptime' => 99.95, 'latencyMs' => 820, 'volume24h' => 128],
            ['id' => 'ussd', 'name' => 'USSD gateway', 'backend' => '*737# · telco hubs', 'status' => 'Active', 'successRate' => 97.4, 'uptime' => 99.1, 'latencyMs' => 1400, 'volume24h' => 44],
            ['id' => 'card', 'name' => 'Card acquiring', 'backend' => 'Visa · Mastercard · Verve', 'status' => 'Active', 'successRate' => 98.6, 'uptime' => 99.95, 'latencyMs' => 1100, 'volume24h' => 61],
            ['id' => 'pos', 'name' => 'POS terminals', 'backend' => 'ISO8583 · float rails', 'status' => 'Active', 'successRate' => 98.1, 'uptime' => 99.95, 'latencyMs' => 950, 'volume24h' => 203],
        ];

        return response()->json([
            'success' => true,
            'data' => array_map(fn ($r) => $r + [
                'lastTrafficAt' => now()->subMinutes(random_int(2, 40))->toIso8601String(),
            ], $rails),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => 'Xtrapay API',
            'version' => 'v1',
            'poweredBy' => 'CheckoutNow',
            'time' => now()->toIso8601String(),
        ]);
    }
}
