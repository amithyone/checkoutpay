<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletOtpService;
use App\Services\Consumer\ConsumerWalletPinVerifier;
use App\Services\Whatsapp\PhoneNormalizer;
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

    public function verifyPin(Request $request, ConsumerWalletPinVerifier $pinVerifier): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:20',
            'pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        $e164 = PhoneNormalizer::canonicalNgE164Digits((string) $request->input('phone'));
        if ($e164 === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Nigerian mobile number.',
            ], 422);
        }

        $wallet = WhatsappWallet::query()->where('phone_e164', $e164)->first();
        if (! $wallet) {
            return response()->json([
                'success' => false,
                'message' => 'No wallet for this number. Sign in with WhatsApp OTP first.',
            ], 422);
        }

        if ($wallet->isPinLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet PIN is locked. Try again later or use WhatsApp OTP.',
            ], 423);
        }

        if (! $wallet->hasPin()) {
            return response()->json([
                'success' => false,
                'message' => 'PIN is not set yet. Sign in with OTP, then set your wallet PIN in the app.',
            ], 422);
        }

        if (! $pinVerifier->verify($wallet, (string) $request->input('pin'))) {
            $wallet->increment('pin_failed_attempts');
            $wallet->refresh();
            if ((int) $wallet->pin_failed_attempts >= 5) {
                $wallet->pin_locked_until = now()->addMinutes(15);
                $wallet->save();

                return response()->json([
                    'success' => false,
                    'message' => 'Too many wrong PIN attempts. Wallet PIN locked for 15 minutes.',
                ], 423);
            }

            return response()->json([
                'success' => false,
                'message' => 'Incorrect wallet PIN.',
            ], 422);
        }

        $wallet->pin_failed_attempts = 0;
        $wallet->pin_locked_until = null;
        $wallet->save();

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
