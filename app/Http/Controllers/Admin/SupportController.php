<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Services\Support\SupportConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function __construct(
        private SupportConversationService $conversations,
    ) {}

    public function index(Request $request): View
    {
        $query = SupportTicket::with(['business', 'assignedAdmin', 'whatsappWallet', 'payment'])
            ->latest('last_message_at')
            ->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->has('assigned_to') && $request->assigned_to) {
            if ($request->assigned_to === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $request->assigned_to);
            }
        }

        $tickets = $query->paginate(20)->withQueryString();

        $stats = [
            'open' => SupportTicket::where('status', SupportTicket::STATUS_OPEN)->count(),
            'in_progress' => SupportTicket::where('status', SupportTicket::STATUS_IN_PROGRESS)->count(),
            'resolved' => SupportTicket::where('status', SupportTicket::STATUS_RESOLVED)->count(),
            'closed' => SupportTicket::where('status', SupportTicket::STATUS_CLOSED)->count(),
            'unread' => SupportTicket::where('admin_unread_count', '>', 0)->count(),
        ];

        return view('admin.support.index', compact('tickets', 'stats'));
    }

    public function inbox(Request $request): JsonResponse
    {
        $query = SupportTicket::query()
            ->with(['business', 'whatsappWallet'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->limit(50)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'tickets' => $tickets->map(fn (SupportTicket $t) => [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number,
                    'subject' => $t->subject,
                    'channel' => $t->channel,
                    'status' => $t->status,
                    'priority' => $t->priority,
                    'display_name' => $t->displayName(),
                    'visitor_phone' => $t->visitor_phone,
                    'admin_unread_count' => $t->admin_unread_count,
                    'last_message_at' => $t->last_message_at?->toIso8601String(),
                    'url' => route('admin.support.show', $t),
                ]),
                'unread_total' => SupportTicket::where('admin_unread_count', '>', 0)->count(),
            ],
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load([
            'business',
            'payment',
            'whatsappWallet',
            'assignedAdmin',
            'replies' => function ($q) {
                $q->orderBy('created_at', 'asc');
            },
        ]);

        if ($ticket->admin_unread_count > 0) {
            $ticket->update(['admin_unread_count' => 0]);
        }

        return view('admin.support.show', compact('ticket'));
    }

    public function messages(Request $request, SupportTicket $ticket): JsonResponse
    {
        $afterId = $request->integer('after_id') ?: null;

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $this->conversations->listMessagesForAdmin($ticket, $afterId, true),
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|min:1',
            'is_internal_note' => 'boolean',
        ]);

        $isInternal = (bool) ($validated['is_internal_note'] ?? false);

        if ($isInternal) {
            $reply = SupportTicketReply::create([
                'ticket_id' => $ticket->id,
                'user_id' => auth('admin')->id(),
                'user_type' => 'admin',
                'message' => $validated['message'],
                'is_internal_note' => true,
            ]);
        } else {
            $result = $this->conversations->addAdminReply($ticket, $validated['message'], false);
            if (! $result['ok']) {
                return response()->json(['success' => false, 'message' => $result['message']], 422);
            }
            $reply = $result['reply'];
        }

        if (in_array($ticket->status, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED], true)) {
            $ticket->update(['status' => SupportTicket::STATUS_IN_PROGRESS]);
        } elseif ($ticket->status === SupportTicket::STATUS_OPEN) {
            $ticket->update(['status' => SupportTicket::STATUS_IN_PROGRESS]);
        }

        return response()->json([
            'success' => true,
            'reply' => [
                'id' => $reply->id,
                'message' => $reply->message,
                'user_type' => $reply->user_type,
                'is_internal_note' => (bool) $reply->is_internal_note,
                'created_at' => $reply->created_at->format('M d, Y H:i'),
                'created_at_human' => $reply->created_at->diffForHumans(),
            ],
        ]);
    }

    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', [
                SupportTicket::STATUS_OPEN,
                SupportTicket::STATUS_IN_PROGRESS,
                SupportTicket::STATUS_RESOLVED,
                SupportTicket::STATUS_CLOSED,
            ]),
            'assigned_to' => 'nullable|exists:admins,id',
        ]);

        $ticket->update([
            'status' => $validated['status'],
            'assigned_to' => $validated['assigned_to'] ?? $ticket->assigned_to,
            'resolved_at' => $validated['status'] === SupportTicket::STATUS_RESOLVED ? now() : null,
        ]);

        return back()->with('success', 'Ticket status updated successfully.');
    }

    public function assign(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => 'required|exists:admins,id',
        ]);

        $ticket->update([
            'assigned_to' => $validated['assigned_to'],
            'status' => SupportTicket::STATUS_IN_PROGRESS,
        ]);

        return back()->with('success', 'Ticket assigned successfully.');
    }
}
