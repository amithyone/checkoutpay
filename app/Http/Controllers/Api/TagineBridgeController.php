<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Renter;
use App\Models\WhatsappWallet;
use App\Services\Whatsapp\EvolutionWhatsAppClient;
use App\Services\Whatsapp\PhoneNormalizer;
use App\Services\Whatsapp\WhatsappEvolutionConfigResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Tagine ↔ Checkout integration: WhatsApp delivery only + wallet rows (no OTP generation here).
 */
class TagineBridgeController extends Controller
{
    public function __construct(
        private EvolutionWhatsAppClient $whatsapp
    ) {}

    /**
     * Deliver plain text to a Nigerian WhatsApp number via Evolution (message composed by Tagine).
     */
    public function sendWhatsAppText(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:10',
            'message' => 'required|string|max:4000',
        ]);

        $e164 = PhoneNormalizer::canonicalNgE164Digits($request->input('phone'));
        if ($e164 === null) {
            return response()->json([
                'message' => 'Invalid Nigerian mobile number.',
            ], 422);
        }

        $instance = WhatsappEvolutionConfigResolver::defaultInstance();
        $text = (string) $request->input('message');
        $sent = $this->whatsapp->sendText($instance, $e164, $text);
        if (! $sent) {
            Log::warning('tagine.bridge: sendWhatsAppText failed', ['phone_e164' => $e164]);

            return response()->json([
                'message' => 'Could not send WhatsApp. Check Evolution settings.',
            ], 502);
        }

        return response()->json(['sent' => true]);
    }

    /**
     * Ensure a WhatsApp wallet exists for this phone (same as in-wallet firstOrCreate).
     * Optionally attach Checkout renter_id from TAGINE_WALLET_RENTER_ID for merchant settlement flows.
     */
    public function ensureWallet(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:10',
        ]);

        $e164 = PhoneNormalizer::canonicalNgE164Digits($request->input('phone'));
        if ($e164 === null) {
            return response()->json(['message' => 'Invalid Nigerian mobile number.'], 422);
        }

        $renterId = config('tagine.wallet_renter_id');

        $wallet = WhatsappWallet::query()->firstOrCreate(
            ['phone_e164' => $e164],
            [
                'tier' => WhatsappWallet::TIER_WHATSAPP_ONLY,
                'balance' => 0,
                'status' => WhatsappWallet::STATUS_ACTIVE,
            ]
        );

        if ($renterId !== null && $renterId > 0 && $wallet->renter_id === null) {
            if (Renter::query()->whereKey($renterId)->exists()) {
                $wallet->renter_id = $renterId;
                $wallet->save();
            } else {
                Log::warning('tagine.bridge: TAGINE_WALLET_RENTER_ID not found', ['renter_id' => $renterId]);
            }
        }

        return response()->json([
            'wallet_id' => $wallet->id,
            'phone_e164' => $wallet->phone_e164,
            'renter_id' => $wallet->renter_id,
        ]);
    }
}
