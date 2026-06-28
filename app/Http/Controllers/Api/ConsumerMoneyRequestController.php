<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWalletMoneyRequest;
use App\Services\Consumer\ConsumerWalletPinVerifier;
use App\Services\Consumer\ConsumerDeviceTrustService;
use App\Services\Whatsapp\WhatsappWalletMoneyRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerMoneyRequestController extends Controller
{
    public function index(Request $request, WhatsappWalletMoneyRequestService $moneyRequests): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $direction = (string) $request->input('direction', 'incoming');

        return response()->json([
            'success' => true,
            'data' => [
                'requests' => $moneyRequests->listForWallet($wallet, $direction),
            ],
        ]);
    }

    public function store(Request $request, WhatsappWalletMoneyRequestService $moneyRequests): JsonResponse
    {
        $request->validate([
            'to_phone' => 'required|string|min:10|max:20',
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string|max:140',
        ]);

        $wallet = $this->walletFor($request);
        $result = $moneyRequests->create(
            $wallet,
            (string) $request->input('to_phone'),
            (float) $request->input('amount'),
            $request->input('note') ? (string) $request->input('note') : null,
            WhatsappWalletMoneyRequest::CHANNEL_CONSUMER_API,
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function accept(
        Request $request,
        string $id,
        WhatsappWalletMoneyRequestService $moneyRequests,
        ConsumerWalletPinVerifier $pinVerifier,
        ConsumerDeviceTrustService $deviceTrust,
    ): JsonResponse {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $user = $request->user();
        $pending = $moneyRequests->findByPublicId($id);
        if ($pending !== null && $user instanceof ConsumerWalletApiAccount) {
            $lockResponse = $deviceTrust->transferLockJsonResponse($user, (float) $pending->amount);
            if ($lockResponse !== null) {
                return $lockResponse;
            }
        }

        $wallet = $this->walletFor($request)->fresh();
        if ($wallet->isPinLocked()) {
            return response()->json(['success' => false, 'message' => 'PIN locked. Try later.'], 423);
        }
        if (! $pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $result = $moneyRequests->accept($wallet, $id);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function decline(Request $request, string $id, WhatsappWalletMoneyRequestService $moneyRequests): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $result = $moneyRequests->decline($wallet, $id);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function destroy(Request $request, string $id, WhatsappWalletMoneyRequestService $moneyRequests): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $result = $moneyRequests->cancel($wallet, $id);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'money_request_balance_hint_enabled' => 'required|boolean',
        ]);

        $wallet = $this->walletFor($request);
        $wallet->money_request_balance_hint_enabled = (bool) $request->boolean('money_request_balance_hint_enabled');
        $wallet->save();

        return response()->json([
            'success' => true,
            'data' => [
                'money_request_balance_hint_enabled' => (bool) $wallet->money_request_balance_hint_enabled,
            ],
        ]);
    }

    private function walletFor(Request $request): \App\Models\WhatsappWallet
    {
        $user = $request->user();
        if (! $user instanceof ConsumerWalletApiAccount) {
            abort(401);
        }

        $wallet = $user->wallet;
        if ($wallet === null) {
            abort(404, 'Wallet not linked.');
        }

        return $wallet;
    }
}
