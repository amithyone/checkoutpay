<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerWalletApiAccount;
use App\Models\SupportTicket;
use App\Services\Support\SupportConversationService;
use App\Services\Support\SupportCountryOptionsService;
use App\Services\Support\SupportIssueOptionsService;
use App\Services\Support\SupportWalletOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsumerSupportController extends Controller
{
    public function __construct(
        private SupportConversationService $conversations,
        private SupportCountryOptionsService $countryOptions,
        private SupportIssueOptionsService $issues,
    ) {}

    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->countryOptions->optionsForRequest($request),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        /** @var ConsumerWalletApiAccount|null $account */
        $account = $request->user();
        $wallet = $this->conversations->resolveWalletForAccount($account);
        $hasLinkedWallet = $wallet !== null;
        $linkWallet = $request->has('link_whatsapp_wallet')
            ? $request->boolean('link_whatsapp_wallet')
            : $hasLinkedWallet;
        $issueType = $request->input('issue_type');

        $rules = [
            'link_whatsapp_wallet' => 'required|boolean',
            'issue_type' => ['nullable', 'string', 'max:64'],
            'name' => 'nullable|string|max:120',
            'first_message' => 'nullable|string|max:5000',
            'consent_accepted' => 'required|accepted',
            'payment_transaction_id' => 'nullable|string|min:4|max:64',
            'transaction_id' => 'nullable|string|min:4|max:64',
            'payment_amount_reported' => 'nullable|numeric|min:0.01|max:999999999',
            'amount_paid' => 'nullable|numeric|min:0.01|max:999999999',
        ];

        if ($issueType && $this->issues->requiresPayment((string) $issueType)) {
            $rules['payment_transaction_id'] = 'required_without:transaction_id|string|min:4|max:64';
            $rules['transaction_id'] = 'required_without:payment_transaction_id|string|min:4|max:64';
            $rules['payment_amount_reported'] = 'required_without:amount_paid|numeric|min:0.01|max:999999999';
            $rules['amount_paid'] = 'required_without:payment_amount_reported|numeric|min:0.01|max:999999999';
        }

        if ($linkWallet && ! $hasLinkedWallet) {
            $rules['phone'] = 'required|string|min:8|max:20';
            $rules['country_iso'] = 'required|string|size:2';
            $rules['wallet_consent_accepted'] = 'required|accepted';
        }

        $validated = $request->validate($rules);

        $payload = [
            'channel' => SupportTicket::CHANNEL_CHECKOUTNOW_APP,
            'link_whatsapp_wallet' => $linkWallet,
            'issue_type' => $validated['issue_type'] ?? null,
            'payment_transaction_id' => $validated['payment_transaction_id'] ?? $validated['transaction_id'] ?? null,
            'payment_amount_reported' => $validated['payment_amount_reported'] ?? $validated['amount_paid'] ?? null,
            'consent_accepted' => true,
            'wallet_consent_accepted' => $linkWallet ? true : null,
            'name' => null,
            'first_message' => $validated['first_message'] ?? null,
        ];

        if ($linkWallet && $wallet) {
            $payload['wallet'] = $wallet;
        } elseif ($linkWallet) {
            $payload['phone'] = $validated['phone'] ?? null;
            $payload['country_iso'] = $validated['country_iso'] ?? null;
        }

        $result = $this->conversations->startConversation($payload, $request);

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not start support chat.',
            ], 422);
        }

        $ticket = $result['ticket'];
        $phone = $ticket->visitor_phone ?? '';

        return response()->json([
            'success' => true,
            'data' => [
                'public_token' => $result['public_token'],
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'wallet_linked' => (bool) $ticket->wallet_linked,
                'wallet_id' => $ticket->whatsapp_wallet_id,
                'visitor_country' => $ticket->visitor_country,
                'phone_masked' => $phone !== '' ? SupportWalletOnboardingService::maskPhone($phone) : null,
                'status' => $ticket->status,
            ],
        ]);
    }

    public function messages(Request $request, string $token): JsonResponse
    {
        $ticket = $this->authorizeTicket($request, $token);
        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        $afterId = $request->integer('after_id') ?: null;

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $this->conversations->listMessagesForVisitor($ticket, $afterId),
                'poll_after_ms' => (int) config('support.poll_interval_seconds', 4) * 1000,
            ],
        ]);
    }

    public function sendMessage(Request $request, string $token): JsonResponse
    {
        $ticket = $this->authorizeTicket($request, $token);
        if ($ticket instanceof JsonResponse) {
            return $ticket;
        }

        $validated = $request->validate([
            'message' => 'required|string|min:1|max:5000',
        ]);

        $result = $this->conversations->addVisitorMessage($ticket, $validated['message']);
        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not send message.',
            ], 422);
        }

        $reply = $result['reply'];

        return response()->json([
            'success' => true,
            'data' => [
                'message' => [
                    'id' => $reply->id,
                    'user_type' => $reply->user_type,
                    'message' => $reply->message,
                    'created_at' => $reply->created_at?->toIso8601String(),
                    'created_at_human' => $reply->created_at?->diffForHumans(),
                ],
            ],
        ]);
    }

    private function authorizeTicket(Request $request, string $token): SupportTicket|JsonResponse
    {
        $ticket = $this->conversations->findByPublicToken($token);
        if (! $ticket || $ticket->channel !== SupportTicket::CHANNEL_CHECKOUTNOW_APP) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        /** @var ConsumerWalletApiAccount|null $account */
        $account = $request->user();
        if ($account && $account->whatsapp_wallet_id && $ticket->wallet_linked) {
            if ((int) $ticket->whatsapp_wallet_id !== (int) $account->whatsapp_wallet_id) {
                return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
            }
        }

        return $ticket;
    }
}
