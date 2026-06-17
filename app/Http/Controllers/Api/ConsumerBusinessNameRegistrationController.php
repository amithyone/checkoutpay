<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerBusinessNameRegistrationService;
use App\Services\Consumer\ConsumerWalletPinVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerBusinessNameRegistrationController extends Controller
{
    public function __construct(
        private ConsumerBusinessNameRegistrationService $registrations,
        private ConsumerWalletPinVerifier $pinVerifier,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);

        return response()->json([
            'success' => true,
            'data' => $this->registrations->index($wallet),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->registrations->isLive()) {
            return response()->json([
                'success' => false,
                'message' => (string) config(
                    'consumer_wallet.business_name_registration.coming_soon_message',
                    'Business name registration coming soon.',
                ),
            ], 403);
        }

        $validated = $request->validate([
            'proposed_name' => 'required|string|min:3|max:200',
            'alternate_name' => 'nullable|string|max:200',
            'owner_full_name' => 'required|string|max:160',
            'owner_phone' => 'required|string|max:32',
            'owner_email' => 'required|email|max:160',
            'business_address' => 'required|string|max:1000',
            'nature_of_business' => 'required|string|max:500',
            'id_type' => 'required|string|in:nin,passport,drivers_license',
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'id_document' => 'required|file|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($wallet->isPinLocked()) {
            return response()->json(['success' => false, 'message' => 'PIN locked. Try later.'], 423);
        }
        if (! $this->pinVerifier->verify($wallet, (string) $validated['pin'])) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN'], 422);
        }

        $result = $this->registrations->submit(
            $wallet,
            $validated,
            $request->file('id_document'),
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
