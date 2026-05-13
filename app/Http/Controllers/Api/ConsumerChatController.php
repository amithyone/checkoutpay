<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\ConsumerWalletChatMessage;
use App\Models\WhatsappWallet;
use App\Services\Consumer\ConsumerWalletConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy support-style thread API kept for older CheckoutNow builds that still call
 * {@code GET|POST …/consumer/chat/messages}. New clients should use
 * {@see ConsumerWalletConversationController} only.
 *
 * {@see store}: persists the user line, runs the same wallet conversation brain, then appends assistant
 * lines to this table so polling {@see index} still surfaces bot replies for legacy UIs.
 */
class ConsumerChatController extends Controller
{
    public function __construct(
        private ConsumerWalletConversationService $conversation,
    ) {}

    private function walletFor(Request $request): WhatsappWallet
    {
        $user = $request->user();
        if (! $user instanceof ConsumerWalletApiAccount) {
            abort(401);
        }
        $user->loadMissing('wallet');
        $w = $user->wallet;
        if (! $w) {
            abort(403, 'Wallet not linked.');
        }

        return $w;
    }

    /**
     * List messages: without after_id, returns the latest `limit` in chronological order.
     * With after_id, returns messages with id > after_id (polling for new).
     */
    public function index(Request $request): JsonResponse
    {
        $wallet = $this->walletFor($request);
        $afterId = max(0, (int) $request->query('after_id', 0));
        $limit = max(1, min(100, (int) $request->query('limit', 50)));

        if ($afterId > 0) {
            $messages = ConsumerWalletChatMessage::query()
                ->where('whatsapp_wallet_id', $wallet->id)
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($limit)
                ->get(['id', 'sender', 'body', 'created_at']);
        } else {
            $messages = ConsumerWalletChatMessage::query()
                ->where('whatsapp_wallet_id', $wallet->id)
                ->orderByDesc('id')
                ->limit($limit)
                ->get(['id', 'sender', 'body', 'created_at'])
                ->sortBy('id')
                ->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $messages,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $max = (int) config('consumer_chat.max_body_chars', 4000);
        $request->validate([
            'body' => 'required|string|min:1|max:'.$max,
        ]);

        $wallet = $this->walletFor($request);
        $body = trim((string) $request->input('body'));
        if ($body === '') {
            return response()->json(['success' => false, 'message' => 'Message is empty.'], 422);
        }

        $msg = ConsumerWalletChatMessage::query()->create([
            'whatsapp_wallet_id' => $wallet->id,
            'sender' => ConsumerWalletChatMessage::SENDER_USER,
            'body' => $body,
        ]);

        $turn = $this->conversation->turn($wallet, $body);
        foreach ($turn['messages'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $line = trim((string) ($row['body'] ?? ''));
            if ($line === '') {
                continue;
            }
            ConsumerWalletChatMessage::query()->create([
                'whatsapp_wallet_id' => $wallet->id,
                'sender' => ConsumerWalletChatMessage::SENDER_SUPPORT,
                'body' => $line,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $msg->only(['id', 'sender', 'body', 'created_at']),
            ],
        ], 201);
    }
}
