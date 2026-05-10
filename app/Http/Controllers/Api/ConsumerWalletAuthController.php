<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerWalletAuthController extends Controller
{
    public function requestOtp(Request $request, ConsumerWalletOtpService $otp): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:20',
        ]);

        $result = $otp->requestOtp((string) $request->input('phone'));

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }

    public function verifyOtp(Request $request, ConsumerWalletOtpService $otp): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:20',
            'code' => 'required|string|max:12',
        ]);

        $verified = $otp->verifyOtp((string) $request->input('phone'), (string) $request->input('code'));
        if (! $verified['ok']) {
            return response()->json([
                'success' => false,
                'message' => $verified['message'],
            ], 422);
        }

        $e164 = (string) $verified['phone_e164'];

        $wallet = WhatsappWallet::query()->firstOrCreate(
            ['phone_e164' => $e164],
            [
                'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                'balance' => 0,
                'status' => WhatsappWallet::STATUS_ACTIVE,
            ]
        );

        $account = ConsumerWalletApiAccount::query()->firstOrNew(['phone_e164' => $e164]);
        $account->whatsapp_wallet_id = $wallet->id;
        $account->phone_e164 = $e164;
        $account->save();

        $account->tokens()->delete();
        $tokenName = (string) config('consumer_wallet.token_name', 'consumer_mobile');
        $plain = $account->createToken($tokenName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Signed in.',
            'data' => [
                'token' => $plain,
                'token_type' => 'Bearer',
                'phone_e164' => $e164,
                'wallet_id' => $wallet->id,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user && method_exists($user, 'currentAccessToken')) {
            $token = $user->currentAccessToken();
            if ($token) {
                $token->delete();
            }
        }

        return response()->json(['success' => true, 'message' => 'Logged out.']);
    }
}
