<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use App\Services\Whatsapp\WhatsappWalletCountryResolver;
use App\Services\Whatsapp\WhatsappWalletPartnerApiService;
use App\Services\Whatsapp\WhatsappWalletPartnerPayIntentService;
use App\Services\Whatsapp\WhatsappWalletTier1TopupVaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWalletApiController extends Controller
{
    public function __construct(
        private WhatsappWalletPartnerApiService $partnerApi,
        private WhatsappWalletPartnerPayIntentService $partnerPayIntent,
        private EvolutionWhatsAppClient $whatsapp,
        private WhatsappWalletTier1TopupVaService $tier1TopupVa,
        private WhatsappWalletCountryResolver $walletCountry
    ) {}

    public function lookup(Request $request): JsonResponse
    {
        $business = $request->user();
        if (! $business instanceof Business) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if ($gate = $this->gateWalletApi($business)) {
            return $gate;
        }

        $request->validate([
            'phone' => 'required|string|min:10',
        ]);

        $result = $this->partnerApi->getWalletSummary((string) $request->input('phone'));
        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Lookup failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'phone_e164' => $result['phone_e164'],
                'wallet_id' => $result['wallet_id'],
                'balance' => $result['balance'],
                'has_pin' => $result['has_pin'],
                'tier' => $result['tier'],
                'status' => $result['status'],
            ],
        ]);
    }

    /**
     * Deliver a merchant-composed plain-text WhatsApp (e.g. login OTP body) via Evolution.
     * Authenticated by the same X-API-Key + whatsapp_wallet_api_enabled gate as lookup / ensure / pay/start.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $business = $request->user();
        if (! $business instanceof Business) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if ($gate = $this->gateWalletApi($business)) {
            return $gate;
        }

        $request->validate([
            'phone' => 'required|string|min:10',
            'message' => 'required|string|max:4000',
        ]);

        $e164 = PhoneNormalizer::canonicalNgE164Digits((string) $request->input('phone'));
        if ($e164 === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Nigerian mobile number.',
            ], 422);
        }

        $instance = WhatsappEvolutionConfigResolver::defaultInstance();
        $text = (string) $request->input('message');
        $sent = $this->whatsapp->sendText($instance, $e164, $text);
        if (! $sent) {
            Log::warning('whatsapp_wallet.merchant_send_message failed', [
                'business_id' => $business->id,
                'phone_e164' => $e164,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not send WhatsApp. Check Evolution settings.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'data' => ['sent' => true],
        ]);
    }

    /**
     * Web / partner: same bank VA as WhatsApp wallet “Receive / Top up” — Tier 1 issues a fresh temp MevonPay VA;
     * Tier 2+ returns the existing dedicated account.
     */
    public function issueTopupVirtualAccount(Request $request): JsonResponse
    {
        $business = $request->user();
        if (! $business instanceof Business) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if ($gate = $this->gateWalletApi($business)) {
            return $gate;
        }

        $request->validate([
            'phone' => 'required|string|min:10',
        ]);

        $e164 = PhoneNormalizer::canonicalNgE164Digits((string) $request->input('phone'));
        if ($e164 === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Nigerian mobile number.',
            ], 422);
        }

        if (! $this->walletCountry->isNigeriaPayInWallet($e164)) {
            return response()->json([
                'success' => false,
                'message' => 'Bank top-up virtual accounts are only available for Nigeria wallet numbers.',
            ], 422);
        }

        $wallet = WhatsappWallet::query()->where('phone_e164', $e164)->first();
        if (! $wallet) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet not found for this number. Call ensure first, then try again.',
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
                    'message' => 'Tier 2 wallet has no dedicated account on file yet. Complete upgrade on WhatsApp (*UPGRADE*).',
                ], 422);
            }

            $displayName = trim(trim((string) $wallet->kyc_fname).' '.trim((string) $wallet->kyc_lname));
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
            ], 200);
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
                'message' => (string) ($issued['message'] ?? 'Could not create a top-up account. Try again later or upgrade to Tier 2 on WhatsApp.'),
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

    public function ensure(Request $request): JsonResponse
    {
        $business = $request->user();
        if (! $business instanceof Business) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if ($gate = $this->gateWalletApi($business)) {
            return $gate;
        }

        $request->validate([
            'phone' => 'required|string|min:10',
        ]);

        $result = $this->partnerApi->ensureWallet((string) $request->input('phone'));
        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Ensure failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    public function startPartnerPay(Request $request): JsonResponse
    {
        $business = $request->user();
        if (! $business instanceof Business) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if ($gate = $this->gateWalletApi($business)) {
            return $gate;
        }

        $validated = $request->validate([
            'phone' => 'required|string|min:10',
            'amount' => 'required|numeric|min:0.01',
            'order_reference' => 'required|string|max:120',
            'order_summary' => 'required|string|max:8000',
            'payer_name' => 'required|string|max:120',
            'webhook_url' => 'required|string|max:500',
            'idempotency_key' => 'required|string|min:8|max:80',
        ]);

        $result = $this->partnerPayIntent->start(
            $business,
            (string) $validated['phone'],
            (float) $validated['amount'],
            (string) $validated['order_reference'],
            (string) $validated['order_summary'],
            (string) $validated['payer_name'],
            (string) $validated['webhook_url'],
            (string) $validated['idempotency_key']
        );

        if (! $result['ok']) {
            $status = (int) ($result['http_status'] ?? 400);

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not start wallet pay',
            ], $status >= 400 && $status < 600 ? $status : 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ], 201);
    }

    private function gateWalletApi(Business $business): ?JsonResponse
    {
        if (! $business->whatsapp_wallet_api_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp wallet API is not enabled for this merchant. Ask Checkout support to enable it on your business account.',
            ], 403);
        }

        return null;
    }
}
