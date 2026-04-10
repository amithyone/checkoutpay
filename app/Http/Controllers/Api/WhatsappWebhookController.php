<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappInboundHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function receive(Request $request, WhatsappInboundHandler $handler): JsonResponse
    {
        $secret = (string) config('whatsapp.webhook_secret', '');
        if ($secret !== '') {
            $provided = $request->header('X-Checkout-WhatsApp-Secret', $request->query('secret', ''));
            if (! is_string($provided) || ! hash_equals($secret, $provided)) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
        }

        try {
            $handler->handleRequest($request);
        } catch (\Throwable $e) {
            Log::error('whatsapp.webhook: handler failed', ['error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'message' => 'Server error'], 500);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * GET for uptime checks / Evolution "test webhook".
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'checkout-whatsapp-webhook',
            'auth' => config('whatsapp.webhook_secret') ? 'secret_required' : 'open',
        ]);
    }
}
