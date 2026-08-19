<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappWalletVtuPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SquadWebhookController extends Controller
{
    public function receive(Request $request, WhatsappWalletVtuPurchaseService $vtu): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            Log::warning('squad.webhook.unauthorized', ['ip' => $request->ip()]);
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $event = $payload['Event'] ?? $payload['event'] ?? '';
        
        // Placeholder for future payment events (e.g. charge.completed)
        if ($event === 'charge.completed' || $event === 'charge.successful') {
            Log::info('squad.webhook.payment_received', ['payload' => $payload]);
            // TODO: Implement payment logic here when ready
            return response()->json(['ok' => true, 'message' => 'Payment webhook received']);
        }

        // By default, try processing as a VTU status webhook
        if (config('vtu.enabled', false)) {
            $result = $vtu->processProviderStatusWebhook($payload);

            if (!$result['ok']) {
                Log::warning('squad.webhook.vtu_unhandled', [
                    'message' => $result['message'],
                    'payload' => $payload,
                ]);

                return response()->json(['ok' => false, 'message' => $result['message']], 404);
            }
            
            return response()->json(['ok' => true, 'message' => $result['message']]);
        }

        Log::info('squad.webhook.unhandled', ['payload' => $payload]);
        return response()->json(['ok' => true, 'message' => 'Webhook received']);
    }

    private function isAuthorized(Request $request): bool
    {
        $secret = trim((string) config('squad_vtu.secret_key', ''));
        if ($secret === '') {
            return false;
        }

        // Standard Squad webhook signature header (HMAC SHA512 of request body)
        $signature = (string) $request->header('x-squad-encrypted-body', '');
        if ($signature !== '') {
            $expected = hash_hmac('sha512', $request->getContent(), $secret);
            return hash_equals(strtolower($expected), strtolower($signature));
        }
        
        // Fallback: check Authorization Bearer token (some legacy/VTU webhooks might use this)
        $auth = (string) $request->header('Authorization', '');
        $provided = (string) preg_replace('/^Bearer\s+/i', '', $auth);
        if ($provided !== '') {
            return hash_equals($secret, $provided);
        }

        return false;
    }
}
