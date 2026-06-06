<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Services\Consumer\ConsumerVirtualCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerVirtualCardController extends Controller
{
    public function __construct(
        private ConsumerVirtualCardService $cards,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $result = $this->cards->status($wallet);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $perPage = max(1, min(50, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));
        $result = $this->cards->cardTransactions($wallet, $perPage, $page);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? [],
            'meta' => $result['meta'] ?? null,
        ]);
    }

    public function prefill(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $result = $this->cards->prefill($wallet);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount_usd' => 'required|numeric|min:0.01|max:10000',
            'action' => 'required|string|in:topup,withdraw,sell,buy',
        ]);

        $wallet = $this->walletFor($request);
        $result = $this->cards->quote(
            $wallet,
            (float) $validated['amount_usd'],
            (string) $validated['action'],
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'terms_accepted' => 'required|accepted',
            'card_name' => 'nullable|string|max:120',
            'home_number' => 'nullable|string|max:32',
            'home_address' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:80',
            'last_name' => 'nullable|string|max:80',
            'email' => 'nullable|email|max:120',
            'dob' => 'nullable|date_format:Y-m-d',
            'phone_number' => 'nullable|string|max:20',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $result = $this->cards->requestCard($wallet, $request->all(), (string) $request->input('pin'));

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function topup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'amount_usd' => 'required|numeric|min:0.01|max:10000',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $result = $this->cards->topupCard(
            $wallet,
            (string) $validated['pin'],
            (float) $validated['amount_usd'],
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function setStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'action' => 'required|string|in:freeze,unfreeze',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $result = $this->cards->setCardFrozen(
            $wallet,
            (string) $validated['pin'],
            (string) $validated['action'],
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function details(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $result = $this->cards->cardDetails($wallet, (string) $validated['pin']);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'amount_usd' => 'required|numeric|min:0.01|max:10000',
            'reason' => 'nullable|string|max:120',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $result = $this->cards->withdrawFromCard(
            $wallet,
            (string) $validated['pin'],
            (float) $validated['amount_usd'],
            $validated['reason'] ?? null,
        );

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
        $user->loadMissing('wallet');
        $wallet = $user->wallet;
        if (! $wallet) {
            abort(404, 'Wallet not found.');
        }

        return $wallet;
    }
}
