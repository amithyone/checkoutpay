<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappWalletVtuPurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VtuWebhookController extends Controller
{
    public function receive(Request $request, WhatsappWalletVtuPurchaseService $vtu): JsonResponse
    {
        if (!config('vtu.enabled', false)) {
            return response()->json(['ok' => false, 'message' => 'VTU is disabled'], 403);
        }

        if (!$this->isAuthorized($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $result = $vtu->processProviderStatusWebhook($payload);

        if (!$result['ok']) {
            Log::warning('vtu.webhook.unhandled', [
                'message' => $result['message'],
                'payload' => $payload,
            ]);

            return response()->json(['ok' => false, 'message' => $result['message']], 404);
        }

        return response()->json(['ok' => true, 'message' => $result['message']]);
    }

    private function isAuthorized(Request $request): bool
    {
        $allowedIps = (array) config('vtu.webhook_allowed_ips', []);
        if (!empty($allowedIps) && !in_array((string) $request->ip(), $allowedIps, true)) {
            return false;
        }

        $secret = trim((string) config('vtu.webhook_secret', ''));
        if ($secret === '') {
            return false;
        }

        $provided = (string) $request->header('X-VTU-Webhook-Secret', '');
        if ($provided === '') {
            $auth = (string) $request->header('Authorization', '');
            $provided = (string) preg_replace('/^Bearer\s+/i', '', $auth);
        }

        return $provided !== '' && hash_equals($secret, $provided);
    }
}
