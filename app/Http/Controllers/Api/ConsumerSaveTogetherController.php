<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WalletSaveTogetherPot;
use App\Services\Consumer\ConsumerDeviceTrustService;
use App\Services\Consumer\ConsumerWalletPinVerifier;
use App\Services\Consumer\SaveTogetherService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerSaveTogetherController extends Controller
{
    public function index(Request $request, SaveTogetherService $saveTogether): JsonResponse
    {
        $wallet = $this->walletFor($request);

        return response()->json([
            'success' => true,
            'data' => [
                'pots' => $saveTogether->listForWallet($wallet),
            ],
        ]);
    }

    public function show(Request $request, string $id, SaveTogetherService $saveTogether): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $pot = $saveTogether->findPotByPublicId($id);
        if ($pot === null) {
            return response()->json(['success' => false, 'message' => 'Pot not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $saveTogether->serializePot($pot->load('members'), $wallet),
        ]);
    }

    public function store(Request $request, SaveTogetherService $saveTogether): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|min:2|max:120',
            'target_amount' => 'required|numeric|min:1',
            'member_phones' => 'required|array|min:1|max:19',
            'member_phones.*' => 'required|string|min:10|max:20',
            'completion_mode' => 'required|in:full_contribution,time_deadline',
            'deadline_at' => 'nullable|date|after:now',
            'note' => 'nullable|string|max:140',
        ]);

        $wallet = $this->walletFor($request);
        $deadline = $request->input('deadline_at')
            ? Carbon::parse((string) $request->input('deadline_at'))
            : null;

        $result = $saveTogether->create(
            $wallet,
            (string) $request->input('title'),
            (float) $request->input('target_amount'),
            array_map('strval', $request->input('member_phones', [])),
            (string) $request->input('completion_mode'),
            $deadline,
            $request->input('note') ? (string) $request->input('note') : null,
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function contribute(
        Request $request,
        string $id,
        SaveTogetherService $saveTogether,
        ConsumerWalletPinVerifier $pinVerifier,
        ConsumerDeviceTrustService $deviceTrust,
    ): JsonResponse {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $user = $request->user();
        $amount = (float) $request->input('amount');
        if ($user instanceof ConsumerWalletApiAccount) {
            $lockResponse = $deviceTrust->transferLockJsonResponse($user, $amount);
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

        $result = $saveTogether->contribute($wallet, $id, $amount);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function withdraw(
        Request $request,
        string $id,
        SaveTogetherService $saveTogether,
        ConsumerWalletPinVerifier $pinVerifier,
    ): JsonResponse {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'amount' => 'nullable|numeric|min:1',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($wallet->isPinLocked()) {
            return response()->json(['success' => false, 'message' => 'PIN locked. Try later.'], 423);
        }
        if (! $pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $amount = $request->filled('amount') ? (float) $request->input('amount') : null;
        $result = $saveTogether->withdraw($wallet, $id, $amount);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function decline(Request $request, string $id, SaveTogetherService $saveTogether): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $result = $saveTogether->decline($wallet, $id);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
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
