<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Models\WhatsappWalletTransferBeneficiary;
use App\Services\Consumer\FaceMotionJsonScorer;
use App\Services\Consumer\WalletFaceCheckService;
use App\Services\Whatsapp\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerFaceController extends Controller
{
    public function __construct(
        private WalletFaceCheckService $face,
    ) {}

    public function status(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $data = $this->face->status($wallet);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function enroll(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|file|mimes:jpeg,jpg,png,webp|max:5120',
            'samples' => 'nullable|array|max:8',
            'samples.*' => 'file|mimes:jpeg,jpg,png,webp|max:5120',
            'motion_json' => 'nullable|string|max:512000',
        ]);

        $wallet = $this->walletFor($request);
        $samples = $request->file('samples', []);
        if (! is_array($samples)) {
            $samples = $samples ? [$samples] : [];
        }

        $motion = app(FaceMotionJsonScorer::class)->evaluate(
            $request->filled('motion_json') ? (string) $request->input('motion_json') : null,
        );
        if (! ($motion['ok'] ?? true)) {
            return response()->json([
                'success' => false,
                'message' => $motion['message'] ?? 'Phone motion check failed.',
                'data' => ['error_code' => $motion['error_code'] ?? 'motion_mismatch'],
                'error_code' => $motion['error_code'] ?? 'motion_mismatch',
            ], 422);
        }

        $result = $this->face->enroll($wallet, $request->file('photo'), array_values($samples));
        if ($result['ok'] && $motion['score'] !== null) {
            $result['data'] = array_merge($result['data'] ?? [], ['motion_score' => $motion['score']]);
        }

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : (int) ($result['http'] ?? 422));
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'selfie' => 'required|file|mimes:jpeg,jpg,png,webp|max:5120',
            'kind' => 'nullable|string|in:bank,p2p',
            'amount' => 'nullable|numeric|min:1',
            'bank_code' => 'nullable|string|max:20',
            'account_number' => 'nullable|regex:/^\d{10}$/',
            'to_phone' => 'nullable|string|min:10|max:20',
            'motion_json' => 'nullable|string|max:512000',
        ]);

        $wallet = $this->walletFor($request);
        $motion = app(FaceMotionJsonScorer::class)->evaluate(
            $request->filled('motion_json') ? (string) $request->input('motion_json') : null,
        );
        if (! ($motion['ok'] ?? true)) {
            return response()->json([
                'success' => false,
                'message' => $motion['message'] ?? 'Phone motion check failed.',
                'data' => ['error_code' => $motion['error_code'] ?? 'motion_mismatch'],
                'error_code' => $motion['error_code'] ?? 'motion_mismatch',
            ], 422);
        }

        $result = $this->face->verifySelfie(
            $wallet,
            $request->file('selfie'),
            (string) $request->input('kind', 'bank'),
            $request->filled('account_number') ? (string) $request->input('account_number') : null,
            $request->filled('bank_code') ? (string) $request->input('bank_code') : null,
            $request->filled('to_phone') ? (string) $request->input('to_phone') : null,
            $request->filled('amount') ? (float) $request->input('amount') : null,
        );
        if ($result['ok'] && $motion['score'] !== null) {
            $result['data'] = array_merge($result['data'] ?? [], ['motion_score' => $motion['score']]);
        }

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
            'error_code' => $result['data']['error_code'] ?? null,
        ], $result['ok'] ? 200 : (int) ($result['http'] ?? 422));
    }

    public function livenessSession(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $result = $this->face->startLiveness($wallet);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
            'error_code' => $result['data']['error_code'] ?? null,
        ], $result['ok'] ? 200 : (int) ($result['http'] ?? 422));
    }

    public function livenessVideo(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string|max:128',
            'clip' => 'required|file|mimetypes:video/mp4,video/webm,video/quicktime|max:8192',
            'motion_json' => 'nullable|string|max:512000',
            'kind' => 'nullable|string|in:bank,p2p',
            'amount' => 'nullable|numeric|min:1',
            'bank_code' => 'nullable|string|max:20',
            'account_number' => 'nullable|regex:/^\d{10}$/',
            'to_phone' => 'nullable|string|min:10|max:20',
        ]);

        $sessionId = (string) $request->input('session_id');
        $motion = app(FaceMotionJsonScorer::class)->evaluate(
            $request->filled('motion_json') ? (string) $request->input('motion_json') : null,
            $sessionId,
        );
        if (! ($motion['ok'] ?? true)) {
            return response()->json([
                'success' => false,
                'message' => $motion['message'] ?? 'Phone motion check failed.',
                'data' => ['error_code' => $motion['error_code'] ?? 'motion_mismatch'],
                'error_code' => $motion['error_code'] ?? 'motion_mismatch',
            ], 422);
        }

        $wallet = $this->walletFor($request);
        $result = $this->face->completeLiveness(
            $wallet,
            $sessionId,
            $request->file('clip'),
            (string) $request->input('kind', 'bank'),
            $request->filled('account_number') ? (string) $request->input('account_number') : null,
            $request->filled('bank_code') ? (string) $request->input('bank_code') : null,
            $request->filled('to_phone') ? (string) $request->input('to_phone') : null,
            $request->filled('amount') ? (float) $request->input('amount') : null,
        );
        if ($result['ok'] && $motion['score'] !== null) {
            $result['data'] = array_merge($result['data'] ?? [], ['motion_score' => $motion['score']]);
        }

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
            'error_code' => $result['data']['error_code'] ?? null,
        ], $result['ok'] ? 200 : (int) ($result['http'] ?? 422));
    }

    public function listBeneficiaries(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $rows = WhatsappWalletTransferBeneficiary::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (WhatsappWalletTransferBeneficiary $b) => $b->toApiArray())
            ->values()
            ->all();

        return response()->json(['success' => true, 'data' => ['beneficiaries' => $rows]]);
    }

    public function storeBeneficiary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => 'required|string|in:bank,p2p',
            'display_name' => 'nullable|string|max:120',
            'bank_code' => 'nullable|string|max:20',
            'account_number' => 'nullable|regex:/^\d{10}$/',
            'to_phone' => 'nullable|string|min:10|max:20',
        ]);

        $wallet = $this->walletFor($request);
        $kind = (string) $validated['kind'];

        if ($kind === WhatsappWalletTransferBeneficiary::KIND_BANK) {
            $request->validate([
                'bank_code' => 'required|string|max:20',
                'account_number' => 'required|regex:/^\d{10}$/',
            ]);
            $acct = (string) $request->input('account_number');
            $code = (string) $request->input('bank_code');
            $key = WhatsappWalletTransferBeneficiary::bankDestinationKey($code, $acct);
            $row = WhatsappWalletTransferBeneficiary::query()->updateOrCreate(
                [
                    'whatsapp_wallet_id' => $wallet->id,
                    'destination_key' => $key,
                ],
                [
                    'kind' => $kind,
                    'account_number' => $acct,
                    'bank_code' => $code,
                    'phone_e164' => null,
                    'display_name' => $validated['display_name'] ?? null,
                ]
            );
        } else {
            $request->validate(['to_phone' => 'required|string|min:10|max:20']);
            $e164 = PhoneNormalizer::canonicalAuthE164Digits((string) $request->input('to_phone'));
            if ($e164 === null) {
                return response()->json(['success' => false, 'message' => 'Invalid phone number.'], 422);
            }
            $key = WhatsappWalletTransferBeneficiary::p2pDestinationKey($e164);
            $row = WhatsappWalletTransferBeneficiary::query()->updateOrCreate(
                [
                    'whatsapp_wallet_id' => $wallet->id,
                    'destination_key' => $key,
                ],
                [
                    'kind' => $kind,
                    'account_number' => null,
                    'bank_code' => null,
                    'phone_e164' => $e164,
                    'display_name' => $validated['display_name'] ?? null,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Beneficiary saved.',
            'data' => $row->toApiArray(),
        ]);
    }

    public function destroyBeneficiary(Request $request, int $id): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $deleted = WhatsappWalletTransferBeneficiary::query()
            ->where('whatsapp_wallet_id', $wallet->id)
            ->where('id', $id)
            ->delete();

        if ($deleted < 1) {
            return response()->json(['success' => false, 'message' => 'Beneficiary not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Beneficiary removed.']);
    }

    private function walletFor(Request $request): WhatsappWallet
    {
        $user = $request->user();
        if (! $user instanceof ConsumerWalletApiAccount) {
            abort(401, 'Unauthorized.');
        }

        $wallet = $user->wallet;
        if (! $wallet instanceof WhatsappWallet) {
            abort(403, 'Wallet not linked.');
        }

        return $wallet;
    }
}
