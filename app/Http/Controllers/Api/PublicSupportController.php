<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\Support\SupportConversationService;
use App\Services\Support\SupportCountryOptionsService;
use App\Services\Support\SupportIssueOptionsService;
use App\Services\Support\SupportPaymentLookupService;
use App\Services\Support\SupportWalletOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicSupportController extends Controller
{
    public function __construct(
        private SupportConversationService $conversations,
        private SupportCountryOptionsService $countryOptions,
        private SupportPaymentLookupService $paymentLookup,
        private SupportIssueOptionsService $issues,
    ) {}

    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->countryOptions->optionsForRequest($request),
        ]);
    }

    public function lookupPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string|min:4|max:64',
        ]);

        $lookup = $this->paymentLookup->lookup($validated['transaction_id']);
        if (! $lookup['ok']) {
            return response()->json([
                'success' => false,
                'message' => $lookup['message'] ?? 'Payment not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $lookup['summary'],
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $linkWallet = $request->boolean('link_whatsapp_wallet');
        $issueType = $request->input('issue_type');

        $rules = [
            'link_whatsapp_wallet' => 'required|boolean',
            'issue_type' => ['nullable', 'string', 'max:64'],
            'name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:255',
            'channel' => ['nullable', Rule::in(SupportTicket::publicChannels())],
            'consent_accepted' => 'required|accepted',
            'first_message' => 'nullable|string|max:5000',
            'payment_transaction_id' => 'nullable|string|min:4|max:64',
            'transaction_id' => 'nullable|string|min:4|max:64',
            'payment_amount_reported' => 'nullable|numeric|min:0.01|max:999999999',
            'amount_paid' => 'nullable|numeric|min:0.01|max:999999999',
        ];

        if ($linkWallet) {
            $rules['phone'] = 'required|string|min:8|max:20';
            $rules['country_iso'] = 'required|string|size:2';
            $rules['wallet_consent_accepted'] = 'required|accepted';
        }

        if ($issueType && $this->issues->requiresPayment((string) $issueType)) {
            $rules['payment_transaction_id'] = 'required_without:transaction_id|string|min:4|max:64';
            $rules['transaction_id'] = 'required_without:payment_transaction_id|string|min:4|max:64';
            $rules['payment_amount_reported'] = 'required_without:amount_paid|numeric|min:0.01|max:999999999';
            $rules['amount_paid'] = 'required_without:payment_amount_reported|numeric|min:0.01|max:999999999';
        }

        $validated = $request->validate($rules);

        $result = $this->conversations->startConversation([
            'link_whatsapp_wallet' => $linkWallet,
            'issue_type' => $validated['issue_type'] ?? null,
            'payment_transaction_id' => $validated['payment_transaction_id'] ?? $validated['transaction_id'] ?? null,
            'payment_amount_reported' => $validated['payment_amount_reported'] ?? $validated['amount_paid'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'country_iso' => $validated['country_iso'] ?? null,
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'] ?? null,
            'channel' => $validated['channel'] ?? SupportTicket::CHANNEL_CHECKOUT_WEB,
            'consent_accepted' => true,
            'wallet_consent_accepted' => $linkWallet ? true : null,
            'first_message' => $validated['first_message'] ?? null,
        ], $request);

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not start support chat.',
            ], 422);
        }

        $ticket = $result['ticket'];

        return response()->json([
            'success' => true,
            'data' => $this->conversationPayload($ticket, (string) $result['public_token']),
        ]);
    }

    public function messages(Request $request, string $token): JsonResponse
    {
        $ticket = $this->conversations->findByPublicToken($token);
        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
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
        $ticket = $this->conversations->findByPublicToken($token);
        if (! $ticket) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
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

        return response()->json([
            'success' => true,
            'data' => [
                'message' => $this->formatSingleReply($result['reply']),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationPayload(SupportTicket $ticket, string $publicToken): array
    {
        $phone = $ticket->visitor_phone ?? $ticket->whatsappWallet?->phone_e164 ?? '';

        return [
            'public_token' => $publicToken,
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'wallet_linked' => (bool) $ticket->wallet_linked,
            'wallet_id' => $ticket->whatsapp_wallet_id,
            'visitor_country' => $ticket->visitor_country,
            'phone_masked' => $phone !== '' ? SupportWalletOnboardingService::maskPhone($phone) : null,
            'status' => $ticket->status,
            'issue_type' => $ticket->issue_type,
            'payment_transaction_id' => $ticket->payment_transaction_id,
            'payment_id' => $ticket->payment_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSingleReply(\App\Models\SupportTicketReply $reply): array
    {
        return [
            'id' => $reply->id,
            'user_type' => $reply->user_type,
            'message' => $reply->message,
            'created_at' => $reply->created_at?->toIso8601String(),
            'created_at_human' => $reply->created_at?->diffForHumans(),
        ];
    }
}
