<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Services\Consumer\ConsumerPaymentAuthService;
use App\Services\Consumer\ConsumerVirtualCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerVirtualCardController extends Controller
{
    public function __construct(
        private ConsumerVirtualCardService $cards,
        private ConsumerPaymentAuthService $paymentAuth,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $forceRefresh = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOLEAN);
        $result = $this->cards->status($wallet, $forceRefresh);

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
        $validated = $request->validate(array_merge([
            'amount_usd' => 'required|numeric|min:0.01|max:10000',
        ], $this->paymentAuth->validationRules()));

        $wallet = $this->walletFor($request)->fresh();
        $auth = $this->paymentAuth->authorize($wallet, $request->user(), $request);
        if (! $auth['ok']) {
            return $auth['response'];
        }

        $result = $this->cards->topupCard(
            $wallet,
            (string) ($request->input('pin') ?? ''),
            (float) $validated['amount_usd'],
            (bool) ($auth['via_payment_token'] ?? false),
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function setStatus(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'action' => 'required|string|in:freeze,unfreeze',
        ], $this->paymentAuth->validationRules()));

        $wallet = $this->walletFor($request)->fresh();
        $auth = $this->paymentAuth->authorize($wallet, $request->user(), $request);
        if (! $auth['ok']) {
            return $auth['response'];
        }

        $result = $this->cards->setCardFrozen(
            $wallet,
            (string) ($request->input('pin') ?? ''),
            (string) $validated['action'],
            (bool) ($auth['via_payment_token'] ?? false),
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function setAutoFreeze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $result = $this->cards->setAutoFreezeOnDecline($wallet, (bool) $validated['enabled']);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function details(Request $request): JsonResponse
    {
        $request->validate($this->paymentAuth->validationRules());

        $wallet = $this->walletFor($request)->fresh();
        $auth = $this->paymentAuth->authorize($wallet, $request->user(), $request);
        if (! $auth['ok']) {
            return $auth['response'];
        }

        $result = $this->cards->cardDetails(
            $wallet,
            (string) ($request->input('pin') ?? ''),
            (bool) ($auth['via_payment_token'] ?? false),
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function retrySync(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();
        $result = $this->cards->retryActivationSync($wallet);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'amount_usd' => 'required|numeric|min:0.01|max:10000',
            'reason' => 'nullable|string|max:120',
        ], $this->paymentAuth->validationRules()));

        $wallet = $this->walletFor($request)->fresh();
        $auth = $this->paymentAuth->authorize($wallet, $request->user(), $request);
        if (! $auth['ok']) {
            return $auth['response'];
        }

        $result = $this->cards->withdrawFromCard(
            $wallet,
            (string) ($request->input('pin') ?? ''),
            (float) $validated['amount_usd'],
            $validated['reason'] ?? null,
            (bool) ($auth['via_payment_token'] ?? false),
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
