<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Consumer\VirtualCardCashwyreWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CashwyreWebhookController extends Controller
{
    public function receive(Request $request): JsonResponse
    {
        $secret = trim((string) config('cashwyre.webhook_secret', ''));
        if ($secret !== '') {
            $token = (string) preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization', ''));
            if (! hash_equals($secret, $token)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }
        }

        $payload = $request->all();
        if ($payload === [] && $request->getContent() !== '') {
            $decoded = json_decode((string) $request->getContent(), true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $service = app(VirtualCardCashwyreWebhookService::class);
        $result = $service->handleWebhook($payload, [
            'raw_body' => (string) $request->getContent(),
        ]);

        Log::info('cashwyre.webhook.handled', [
            'result' => $result,
            'event' => $service->extractWebhookEvent($payload),
        ]);

        return match ($result) {
            VirtualCardCashwyreWebhookService::RESULT_ACTIVATED => response()->json(['success' => true, 'message' => 'Virtual card activated']),
            VirtualCardCashwyreWebhookService::RESULT_ALREADY_ACTIVE => response()->json(['success' => true, 'message' => 'Virtual card already active']),
            VirtualCardCashwyreWebhookService::RESULT_TOPUP_SUCCESS => response()->json(['success' => true, 'message' => 'Card topup processed']),
            VirtualCardCashwyreWebhookService::RESULT_WITHDRAW_SUCCESS => response()->json(['success' => true, 'message' => 'Card withdraw processed']),
            VirtualCardCashwyreWebhookService::RESULT_FAILED => response()->json(['success' => true, 'message' => 'Card creation failure processed']),
            VirtualCardCashwyreWebhookService::RESULT_NO_MATCH => response()->json([
                'success' => true,
                'message' => 'Card webhook received; no matching virtual card request found',
            ]),
            VirtualCardCashwyreWebhookService::RESULT_FEE_COLLECTION_FAILED => response()->json([
                'success' => false,
                'message' => 'Card webhook received but fee could not be collected from wallet',
            ], 422),
            default => response()->json(['success' => true, 'message' => 'Ignored']),
        };
    }
}
