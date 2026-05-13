<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Services\Consumer\ConsumerWalletConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumerWalletConversationController extends Controller
{
    public function store(Request $request, ConsumerWalletConversationService $conversation): JsonResponse
    {
        $max = (int) config('consumer_wallet.conversation_max_chars', 2000);
        $request->validate([
            'text' => 'nullable|string|max:'.$max,
        ]);

        $user = $request->user();
        if (! $user instanceof ConsumerWalletApiAccount) {
            abort(401);
        }
        $user->loadMissing('wallet');
        $wallet = $user->wallet;
        if (! $wallet) {
            abort(403, 'Wallet not linked.');
        }

        $text = (string) $request->input('text', '');
        $payload = $conversation->turn($wallet, $text);

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }
}
