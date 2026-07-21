<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Whatsapp\WhatsappCloudConfigResolver;
use App\Services\Whatsapp\WhatsappInboundHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookController extends Controller
{
    public function receive(Request $request, WhatsappInboundHandler $handler): JsonResponse
    {
        if (WhatsappCloudConfigResolver::isEnabled()) {
            if (! $this->validMetaSignature($request)) {
                return response()->json(['message' => 'Invalid signature'], 401);
            }
        } else {
            $secret = $this->resolvedWebhookSecret();
            if ($secret !== '') {
                $provided = $request->header('X-Checkout-WhatsApp-Secret', $request->query('secret', ''));
                if (! is_string($provided) || ! hash_equals($secret, $provided)) {
                    return response()->json(['message' => 'Unauthorized'], 401);
                }
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
     * GET: Meta webhook verification (hub.verify_token + hub.challenge) or Evolution health JSON.
     */
    public function health(Request $request): JsonResponse|Response
    {
        if ($this->isMetaVerificationRequest($request)) {
            return $this->verifyMetaWebhook($request);
        }

        return response()->json([
            'status' => 'ok',
            'service' => 'checkout-whatsapp-webhook',
            'provider' => WhatsappCloudConfigResolver::isEnabled() ? 'cloud' : 'evolution',
            'auth' => WhatsappCloudConfigResolver::isEnabled()
                ? 'meta_signature'
                : ($this->resolvedWebhookSecret() !== '' ? 'secret_required' : 'open'),
        ]);
    }

    private function isMetaVerificationRequest(Request $request): bool
    {
        return $request->query('hub_mode') === 'subscribe'
            && $request->query('hub_verify_token') !== null
            && $request->query('hub_challenge') !== null;
    }

    private function verifyMetaWebhook(Request $request): Response
    {
        $mode = (string) $request->query('hub_mode', '');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');
        $expected = WhatsappCloudConfigResolver::verifyToken();

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('whatsapp.webhook: meta verify failed', [
            'mode' => $mode,
            'token_provided' => $token !== '',
        ]);

        return response('Forbidden', 403);
    }

    private function validMetaSignature(Request $request): bool
    {
        $secret = WhatsappCloudConfigResolver::appSecret();
        if ($secret === '') {
            Log::warning('whatsapp.webhook: WHATSAPP_CLOUD_APP_SECRET not set — skipping signature check');

            return true;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        if ($header === '' || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    private function resolvedWebhookSecret(): string
    {
        $db = Setting::get('whatsapp_webhook_secret');
        if (is_string($db) && trim($db) !== '') {
            return trim($db);
        }

        return (string) config('whatsapp.webhook_secret', '');
    }
}
