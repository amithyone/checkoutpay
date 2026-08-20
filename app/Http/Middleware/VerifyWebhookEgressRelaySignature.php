<?php

namespace App\Http\Middleware;

use App\Services\Webhook\WebhookEgressRelay;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookEgressRelaySignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! WebhookEgressRelay::receiverEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook egress relay receiver is disabled',
            ], 403);
        }

        $secret = (string) config('checkout.webhook_egress.relay_secret', '');
        $timestamp = (string) $request->header('X-Webhook-Egress-Timestamp', '');
        $nonce = (string) $request->header('X-Webhook-Egress-Nonce', '');
        $signature = (string) $request->header('X-Webhook-Egress-Signature', '');

        if ($timestamp === '' || $nonce === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return response()->json(['success' => false, 'message' => 'Missing egress auth headers'], 401);
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return response()->json(['success' => false, 'message' => 'Expired timestamp'], 401);
        }

        $nonceKey = 'webhook_egress_nonce:'.$nonce;
        if (! Cache::add($nonceKey, 1, now()->addMinutes(10))) {
            return response()->json(['success' => false, 'message' => 'Replay detected'], 409);
        }

        $raw = $request->getContent();
        $expected = WebhookEgressRelay::sign($timestamp, $nonce, $raw, $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
        }

        $allowedIps = (array) config('checkout.webhook_egress.allowed_ips', []);
        if ($allowedIps !== [] && ! in_array((string) $request->ip(), $allowedIps, true)) {
            return response()->json(['success' => false, 'message' => 'IP not allowed'], 403);
        }

        return $next($request);
    }
}
