<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Webhook\WebhookEgressRelay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookEgressRelayController extends Controller
{
    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_url' => 'required|url|max:2000',
            'payload' => 'required|array',
        ]);

        $result = WebhookEgressRelay::forwardAsReceiver(
            (string) $validated['target_url'],
            (array) $validated['payload']
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
