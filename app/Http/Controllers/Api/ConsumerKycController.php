<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\WalletKycComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerKycController extends Controller
{
    public function __construct(
        private WalletKycComplianceService $kyc,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);

        return response()->json([
            'success' => true,
            'data' => $this->kyc->payload($wallet),
        ]);
    }

    public function requestEmail(Request $request): JsonResponse
    {
        $out = $this->kyc->requestEmailOtp($this->walletFor($request));

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'error_code' => $out['ok'] ? null : 'email_unverified',
        ], $out['ok'] ? 200 : 422);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|max:12']);
        $out = $this->kyc->verifyEmailOtp($this->walletFor($request), (string) $request->input('code'));

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'error_code' => $out['ok'] ? null : ($out['error_code'] ?? 'email_unverified'),
            'data' => $out['data'] ?? null,
        ], $out['ok'] ? 200 : 422);
    }

    public function identity(Request $request): JsonResponse
    {
        $request->validate([
            'fname' => 'nullable|string|max:128',
            'lname' => 'nullable|string|max:128',
            'dob' => 'nullable|date_format:Y-m-d',
            'gender' => 'nullable|string|in:male,female,M,F,m,f',
            'email' => 'nullable|email|max:255',
            'bvn' => 'required_without:nin|nullable|string|max:11',
            'nin' => 'required_without:bvn|nullable|string|max:11',
        ]);
        $out = $this->kyc->submitIdentity($this->walletFor($request), $request->only([
            'fname', 'lname', 'dob', 'gender', 'email', 'bvn', 'nin',
        ]));
        $status = 200;
        if (! ($out['ok'] ?? false)) {
            $status = (int) ($out['http_status'] ?? 422);
        }

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'error_code' => $out['error_code'] ?? null,
            'data' => $out['data'] ?? null,
        ], $status);
    }

    public function idDocument(Request $request): JsonResponse
    {
        $request->validate([
            'id_type' => 'required|string|in:passport,nin_slip,pvc,drivers_licence',
            'document' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
        ]);
        $out = $this->kyc->storeIdDocument(
            $this->walletFor($request),
            (string) $request->input('id_type'),
            $request->file('document'),
        );

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'error_code' => $out['error_code'] ?? null,
            'data' => $out['data'] ?? null,
        ], $out['ok'] ? 200 : 422);
    }

    public function address(Request $request): JsonResponse
    {
        $request->validate([
            'line1' => 'required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:128',
            'state' => 'required|string|max:128',
            'postal_code' => 'nullable|string|max:32',
            'country' => 'nullable|string|max:8',
            'occupation' => 'nullable|string|max:191',
            'employer' => 'nullable|string|max:191',
            'purpose_of_account' => 'nullable|string|max:255',
            'source_of_funds' => 'required|string|max:255',
            'expected_profile' => 'nullable|array',
            'evidence' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
        ]);
        $out = $this->kyc->storeAddress(
            $this->walletFor($request),
            $request->all(),
            $request->file('evidence'),
        );

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'],
            'error_code' => $out['error_code'] ?? null,
            'data' => $out['data'] ?? null,
        ], $out['ok'] ? 200 : 422);
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
