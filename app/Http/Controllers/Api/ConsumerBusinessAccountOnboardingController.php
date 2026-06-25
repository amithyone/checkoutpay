<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerBusinessAccountOnboardingService;
use App\Services\Consumer\ConsumerWalletPinVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerBusinessAccountOnboardingController extends Controller
{
    public function __construct(
        private ConsumerBusinessAccountOnboardingService $onboarding,
        private ConsumerWalletPinVerifier $pinVerifier,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh(['linkedBusiness']);

        return response()->json([
            'success' => true,
            'data' => $this->onboarding->index($wallet),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->onboarding->isLive()) {
            return response()->json([
                'success' => false,
                'message' => (string) config(
                    'consumer_wallet.business_account_onboarding.coming_soon_message',
                    'Business account onboarding coming soon.'
                ),
            ], 403);
        }

        $validated = $request->validate([
            'account_plan' => 'required|string|in:payments_only,payments_and_web',
            'service_categories' => 'nullable|array',
            'service_categories.*' => 'string|in:payments,rentals,memberships,tickets,charity,invoices',
            'business_name' => 'required|string|min:3|max:200',
            'email' => 'required|email|max:160',
            'phone' => 'nullable|string|max:32',
            'address' => 'required|string|max:1000',
            'website_url' => 'nullable|url|max:500',
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'cac_document' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($wallet->isPinLocked()) {
            return response()->json(['success' => false, 'message' => 'PIN locked. Try later.'], 423);
        }
        if (! $this->pinVerifier->verify($wallet, (string) $validated['pin'])) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN'], 422);
        }

        $result = $this->onboarding->submit(
            $wallet,
            $validated,
            $request->file('cac_document'),
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], $result['http_status'] ?? ($result['ok'] ? 200 : 422));
    }

    public function setPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|max:128',
            'password_confirmation' => 'required|string|same:password',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $result = $this->onboarding->setPassword(
            $wallet,
            (string) $validated['password'],
            (string) $validated['password_confirmation'],
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null,
        ], $result['http_status'] ?? ($result['ok'] ? 200 : 422));
    }

    private function walletFor(Request $request): WhatsappWallet
    {
        $user = $request->user();
        if (! $user instanceof ConsumerWalletApiAccount) {
            abort(401);
        }
        $user->loadMissing('wallet');
        $w = $user->wallet;
        if (! $w) {
            abort(403, 'Wallet not linked.');
        }

        return $w;
    }
}
