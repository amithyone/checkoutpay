<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\SupportTicket;
use App\Services\Support\SupportConversationService;
use App\Services\Support\SupportIssueOptionsService;
use App\Services\Support\SupportWalletOnboardingService;
use App\Services\Support\WalletSupportStaffResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ConsumerSupportStaffController extends Controller
{
    public function __construct(
        private WalletSupportStaffResolver $staffResolver,
        private SupportConversationService $conversations,
    ) {}

    public function inbox(Request $request): JsonResponse
    {
        $admin = $this->requireStaff($request);
        if ($admin instanceof JsonResponse) {
            return $admin;
        }

        $tickets = $this->staffResolver->walletQueueTicketQuery()
            ->with(['whatsappWallet'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'tickets' => $tickets->map(fn (SupportTicket $ticket) => $this->formatInboxTicket($ticket))->all(),
                'unread_total' => $this->staffResolver->unreadWalletQueueTotal(),
            ],
        ]);
    }

    public function messages(Request $request, int $ticketId): JsonResponse
    {
        $admin = $this->requireStaff($request);
        if ($admin instanceof JsonResponse) {
            return $admin;
        }

        $ticket = $this->findStaffTicket($ticketId);
        if ($ticket === null) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        $afterId = $request->integer('after_id') ?: null;

        return response()->json([
            'success' => true,
            'data' => [
                'ticket' => $this->formatInboxTicket($ticket),
                'messages' => $this->conversations->listMessagesForAdmin($ticket, $afterId),
                'poll_after_ms' => (int) config('support.poll_interval_seconds', 5) * 1000,
            ],
        ]);
    }

    public function reply(Request $request, int $ticketId): JsonResponse
    {
        $admin = $this->requireStaff($request);
        if ($admin instanceof JsonResponse) {
            return $admin;
        }

        $ticket = $this->findStaffTicket($ticketId);
        if ($ticket === null) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:1|max:5000',
        ]);

        $result = $this->conversations->addAdminReplyForAdmin(
            $ticket,
            $validated['message'],
            $admin->id,
        );

        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Could not send reply.',
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

    public function updateStatus(Request $request, int $ticketId): JsonResponse
    {
        $admin = $this->requireStaff($request);
        if ($admin instanceof JsonResponse) {
            return $admin;
        }

        $ticket = $this->findStaffTicket($ticketId);
        if ($ticket === null) {
            return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                SupportTicket::STATUS_OPEN,
                SupportTicket::STATUS_IN_PROGRESS,
                SupportTicket::STATUS_RESOLVED,
                SupportTicket::STATUS_CLOSED,
            ])],
        ]);

        $updates = ['status' => $validated['status']];
        if (in_array($validated['status'], [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true)) {
            $updates['resolved_at'] = now();
        }

        $ticket->update($updates);

        return response()->json([
            'success' => true,
            'data' => [
                'ticket' => $this->formatInboxTicket($ticket->fresh()),
            ],
        ]);
    }

    private function requireStaff(Request $request): Admin|JsonResponse
    {
        /** @var ConsumerWalletApiAccount $account */
        $account = $request->user();
        $admin = $this->staffResolver->resolveForAccount($account);
        if ($admin === null) {
            return response()->json([
                'success' => false,
                'message' => 'Wallet support staff access required.',
                'mode' => 'customer',
            ], 403);
        }

        return $admin;
    }

    private function findStaffTicket(int $ticketId): ?SupportTicket
    {
        return $this->staffResolver->walletQueueTicketQuery()
            ->whereKey($ticketId)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInboxTicket(SupportTicket $ticket): array
    {
        $phone = (string) ($ticket->visitor_phone ?? '');
        $preview = (string) ($ticket->message ?? '');

        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'issue_type' => $ticket->issue_type,
            'issue_label' => $ticket->issueTypeLabel(),
            'status' => $ticket->status,
            'admin_unread_count' => (int) $ticket->admin_unread_count,
            'last_message_preview' => Str::limit($preview, 120),
            'last_message_at' => $ticket->last_message_at?->toIso8601String(),
            'visitor_phone_masked' => $phone !== '' ? SupportWalletOnboardingService::maskPhone($phone) : null,
            'visitor_name' => $ticket->visitor_name ?: $ticket->whatsappWallet?->sender_name,
        ];
    }
}
