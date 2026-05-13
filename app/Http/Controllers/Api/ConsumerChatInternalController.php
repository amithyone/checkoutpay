<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Legacy server-to-server support reply hook (retired).
 */
class ConsumerChatInternalController extends Controller
{
    public function reply(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'The in-app support inbox is retired. Wallet chat uses POST /api/v1/consumer/wallet/conversation (WhatsApp session parity).',
        ], 410);
    }
}
