<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransaction;
use App\Services\Consumer\ConsumerWalletKycService;
use App\Services\Consumer\ConsumerWalletPinVerifier;
use App\Services\Consumer\ConsumerWalletTransferService;
use App\Services\MavonPayTransferService;
use App\Services\VtuNg\VtuNgApiClient;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use App\Services\Whatsapp\WhatsappWalletPartnerApiService;
use App\Services\Whatsapp\WhatsappWalletPendingP2pService;
use App\Services\Whatsapp\WhatsappWalletTier1TopupVaService;
use App\Services\Whatsapp\WhatsappWalletVtuPurchaseService;
use App\Services\WhatsappWalletBankPayoutService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ConsumerWalletApiController extends Controller
{
    public function __construct(
        private WhatsappWalletPartnerApiService $partnerApi,
        private WhatsappWalletTier1TopupVaService $tier1TopupVa,
        private WhatsappWalletCountryResolver $walletCountry,
        private ConsumerWalletPinVerifier $pinVerifier,
        private ConsumerWalletTransferService $transfers,
        private ConsumerWalletKycService $kyc,
        private WhatsappWalletBankPayoutService $bankPayout,
        private WhatsappWalletVtuPurchaseService $vtuPurchase,
        private VtuNgApiClient $vtuApi,
        private WhatsappWalletPendingP2pService $pendingP2p,
    ) {}

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

    public function showWallet(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();
        $summary = $this->partnerApi->getWalletSummary((string) $wallet->phone_e164);
        $cur = $this->walletCountry->currencyForPhoneE164((string) $wallet->phone_e164);

        $base = $summary['ok'] ? [
            'phone_e164' => $summary['phone_e164'],
            'wallet_id' => $summary['wallet_id'],
            'balance' => $summary['balance'],
            'has_pin' => $summary['has_pin'],
            'tier' => $summary['tier'],
            'status' => $summary['status'],
        ] : [
            'phone_e164' => $wallet->phone_e164,
            'wallet_id' => $wallet->id,
            'balance' => (float) $wallet->balance,
            'has_pin' => $wallet->hasPin(),
            'tier' => (int) $wallet->tier,
            'status' => $wallet->status,
        ];

        return response()->json([
            'success' => true,
            'data' => array_merge($base, [
                'currency' => $cur,
                'sender_name' => $wallet->normalizedSenderName(),
                'needs_quick_setup' => $wallet->needsQuickWalletSetup(),
                'is_pin_locked' => $wallet->isPinLocked(),
                'mevon_virtual_account_number' => $wallet->mevon_virtual_account_number,
                'mevon_bank_name' => $wallet->mevon_bank_name,
                'mevon_bank_code' => $wallet->mevon_bank_code,
                'rubies_account_type' => $wallet->rubies_account_type,
            ]),
        ]);
    }

    public function ensure(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $result = $this->partnerApi->ensureWallet((string) $wallet->phone_e164);
        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Ensure failed',
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $result['data']]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $perPage = max(1, min(50, (int) $request->input('per_page', 20)));

        $paginator = WhatsappWalletTransaction::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function issueTopupVirtualAccount(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();
        $e164 = (string) $wallet->phone_e164;

        if (! $this->walletCountry->isNigeriaPayInWallet($e164)) {
            return response()->json([
                'success' => false,
                'message' => 'Bank top-up virtual accounts are only available for Nigeria wallet numbers.',
            ], 422);
        }

        if (! $wallet->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'This wallet is not active.',
            ], 422);
        }

        if ($wallet->tier >= WhatsappWallet::TIER_RUBIES_VA) {
            $acct = trim((string) $wallet->mevon_virtual_account_number);
            if ($acct === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Tier 2 wallet has no dedicated account on file yet. Complete KYC first.',
                ], 422);
            }

            $displayName = trim(trim((string) $wallet->kyc_fname).' '.trim((string) $wallet->kyc_lname));
            if ($displayName === '' && (string) $wallet->rubies_account_type === 'business' && trim((string) $wallet->kyc_cac) !== '') {
                $displayName = 'Business · '.trim((string) $wallet->kyc_cac);
            }
            if ($displayName === '') {
                $displayName = trim((string) ($wallet->sender_name ?? ''));
            }
            if ($displayName === '') {
                $displayName = (string) ($wallet->mevon_reference ?? 'Wallet account');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'kind' => 'permanent',
                    'account_number' => $acct,
                    'account_name' => $displayName,
                    'bank_name' => $wallet->mevon_bank_name ?? 'Rubies MFB',
                    'bank_code' => $wallet->mevon_bank_code,
                    'expires_at' => null,
                    'phone_e164' => $wallet->phone_e164,
                ],
            ]);
        }

        if ((int) $wallet->tier !== WhatsappWallet::TIER_WHATSAPP_ONLY) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported wallet tier for bank top-up.',
            ], 422);
        }

        $issued = $this->tier1TopupVa->issueFreshVa($wallet->fresh());
        if (! ($issued['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => (string) ($issued['message'] ?? 'Could not create a top-up account.'),
            ], 502);
        }

        $expiresAt = isset($issued['expires_at']) ? (string) $issued['expires_at'] : null;
        $expiresDisplay = null;
        if ($expiresAt !== null && $expiresAt !== '') {
            try {
                $expiresDisplay = Carbon::parse($expiresAt)->timezone((string) config('app.timezone'))->toIso8601String();
            } catch (\Throwable) {
                $expiresDisplay = $expiresAt;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kind' => 'temporary',
                'account_number' => (string) ($issued['account_number'] ?? ''),
                'account_name' => (string) ($issued['account_name'] ?? ''),
                'bank_name' => (string) ($issued['bank_name'] ?? ''),
                'bank_code' => $issued['bank_code'] ?? null,
                'expires_at' => $expiresDisplay,
                'phone_e164' => $wallet->phone_e164,
            ],
        ], 201);
    }

    public function setPin(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();
        if ($wallet->hasPin()) {
            return response()->json([
                'success' => false,
                'message' => 'PIN already set. Use change PIN.',
            ], 422);
        }

        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        $wallet->pin_hash = Hash::make((string) $request->input('pin'));
        $wallet->pin_set_at = now();
        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

        $instance = \App\Services\Whatsapp\WhatsappEvolutionConfigResolver::defaultInstance();
        if ($instance !== '') {
            $this->pendingP2p->tryClaimForWallet($wallet->fresh(), $instance);
        }

        return response()->json(['success' => true, 'message' => 'PIN saved.']);
    }

    public function changePin(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();
        if (! $wallet->hasPin()) {
            return response()->json([
                'success' => false,
                'message' => 'Set a PIN first.',
            ], 422);
        }

        $request->validate([
            'current_pin' => ['required', 'regex:/^\d{4}$/'],
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        if (! $this->pinVerifier->verify($wallet, (string) $request->input('current_pin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Current PIN is incorrect.',
            ], 422);
        }

        $wallet->pin_hash = Hash::make((string) $request->input('pin'));
        $wallet->pin_set_at = now();
        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

        return response()->json(['success' => true, 'message' => 'PIN updated.']);
    }

    public function updateSenderName(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $request->validate([
            'sender_name' => 'required|string|min:2|max:120',
        ]);

        $wallet->sender_name = trim((string) $request->input('sender_name'));
        $wallet->save();

        return response()->json(['success' => true, 'message' => 'Display name updated.']);
    }

    public function transferP2p(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'to_phone' => 'required|string|min:10|max:20',
            'amount' => 'required|numeric|min:1',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($wallet->isPinLocked()) {
            return response()->json(['success' => false, 'message' => 'PIN locked. Try later.'], 423);
        }
        if (! $this->pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $result = $this->transfers->p2p(
            $wallet,
            (string) $request->input('to_phone'),
            (float) $request->input('amount')
        );

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function transferBank(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'amount' => 'required|numeric|min:1',
            'account_number' => 'required|string|size:10',
            'bank_code' => 'required|string|max:20',
            'bank_name' => 'required|string|max:120',
            'account_name' => 'required|string|max:120',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if ($wallet->isPinLocked()) {
            return response()->json(['success' => false, 'message' => 'PIN locked. Try later.'], 423);
        }
        if (! $this->pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $result = $this->transfers->bankTransfer(
            $wallet,
            (float) $request->input('amount'),
            (string) $request->input('account_number'),
            (string) $request->input('bank_code'),
            (string) $request->input('bank_name'),
            (string) $request->input('account_name'),
        );

        $code = $result['ok'] ? 200 : 422;
        if ($result['ok'] === false && ($result['data']['bucket'] ?? '') === MavonPayTransferService::BUCKET_FAILED) {
            $code = 502;
        }

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $code);
    }

    public function bankNameEnquiry(Request $request): JsonResponse
    {
        $this->walletFor($request);
        $request->validate([
            'bank_code' => 'required|string|max:20',
            'account_number' => 'required|string|size:10',
        ]);

        $ne = $this->bankPayout->nameEnquiry(
            (string) $request->input('bank_code'),
            (string) $request->input('account_number')
        );
        if ($ne === null) {
            return response()->json([
                'success' => false,
                'message' => 'Name enquiry failed.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'account_name' => $ne['account_name'],
                'bank_code' => $ne['bank_code'],
            ],
        ]);
    }

    public function vtuNetworks(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => config('vtu.networks', []),
        ]);
    }

    public function vtuDataPlans(Request $request): JsonResponse
    {
        $request->validate([
            'network_id' => 'required|string|max:40',
        ]);

        if (! $this->vtuApi->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'VTU not configured.'], 503);
        }

        $parsed = $this->vtuApi->fetchDataPlans((string) $request->input('network_id'));
        if (! ($parsed['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $parsed['message'] ?? 'Could not load plans.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => ['plans' => $parsed['plans'] ?? []],
        ]);
    }

    public function vtuAirtime(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'network_id' => 'required|string|max:40',
            'phone' => 'required|string|min:10|max:20',
            'amount' => 'required|numeric|min:1',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if (! $this->pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $e164 = PhoneNormalizer::canonicalNgE164Digits((string) $request->input('phone'));
        if ($e164 === null) {
            return response()->json(['success' => false, 'message' => 'Invalid phone.'], 422);
        }

        $out = $this->vtuPurchase->purchaseAirtime(
            $wallet,
            (string) $request->input('network_id'),
            $e164,
            (float) $request->input('amount')
        );

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => isset($out['balance_after']) ? ['balance_after' => $out['balance_after']] : null,
        ], $out['ok'] ? 200 : 422);
    }

    public function vtuData(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
            'network_id' => 'required|string|max:40',
            'phone' => 'required|string|min:10|max:20',
            'variation_id' => 'required|integer|min:1',
            'expected_price' => 'required|numeric|min:1',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        if (! $this->pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            return response()->json(['success' => false, 'message' => 'Invalid PIN.'], 422);
        }

        $e164 = PhoneNormalizer::canonicalNgE164Digits((string) $request->input('phone'));
        if ($e164 === null) {
            return response()->json(['success' => false, 'message' => 'Invalid phone.'], 422);
        }

        $out = $this->vtuPurchase->purchaseData(
            $wallet,
            (string) $request->input('network_id'),
            $e164,
            (int) $request->input('variation_id'),
            (float) $request->input('expected_price')
        );

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => isset($out['balance_after']) ? ['balance_after' => $out['balance_after']] : null,
        ], $out['ok'] ? 200 : 422);
    }

    public function kycTier2Status(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request)->fresh();
        $out = $this->kyc->tier2Status($wallet);

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => $out['data'] ?? null,
        ]);
    }

    public function kycTier2Personal(Request $request): JsonResponse
    {
        $request->validate([
            'fname' => 'required|string|max:128',
            'lname' => 'required|string|max:128',
            'dob' => 'required|date_format:Y-m-d',
            'gender' => 'nullable|string|in:male,female,M,F',
            'email' => 'required|email|max:255',
            'bvn' => 'required_without:nin|nullable|string|size:11',
            'nin' => 'required_without:bvn|nullable|string|size:11',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $g = strtolower((string) $request->input('gender', ''));
        if ($g === 'm') {
            $g = 'male';
        }
        if ($g === 'f') {
            $g = 'female';
        }

        $out = $this->kyc->submitPersonalTier2($wallet, array_merge($request->only([
            'fname', 'lname', 'dob', 'email', 'bvn', 'nin',
        ]), ['gender' => $g]));

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => $out['data'] ?? null,
        ], $out['ok'] ? 200 : 422);
    }

    public function kycTier2Business(Request $request): JsonResponse
    {
        $request->validate([
            'cac' => 'required|string|max:100',
            'dob' => 'required|date_format:Y-m-d',
            'email' => 'required|email|max:255',
        ]);

        $wallet = $this->walletFor($request)->fresh();
        $out = $this->kyc->submitBusinessTier2($wallet, $request->only(['cac', 'dob', 'email']));

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'data' => $out['data'] ?? null,
        ], $out['ok'] ? 200 : 422);
    }
}
