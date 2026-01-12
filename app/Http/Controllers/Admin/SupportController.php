<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $query = SupportTicket::with(['business', 'assignedAdmin', 'replies'])
            ->latest();

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority') && $request->priority) {
            $query->where('priority', $request->priority);
        }

        // Filter by assigned admin
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
        ];

        return view('admin.support.index', compact('tickets', 'stats'));
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load([
            'business',
            'assignedAdmin',
            'replies' => function($q) {
                $q->orderBy('created_at', 'asc');
            }
        ]);

        return view('admin.support.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|min:1',
            'is_internal_note' => 'boolean',
        ]);

        $reply = SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth('admin')->id(),
            'user_type' => 'admin',
            'message' => $validated['message'],
            'is_internal_note' => $validated['is_internal_note'] ?? false,
        ]);

        // Update ticket status if it was closed/resolved
        if (in_array($ticket->status, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])) {
            $ticket->update(['status' => SupportTicket::STATUS_IN_PROGRESS]);
        } else if ($ticket->status === SupportTicket::STATUS_OPEN) {
            $ticket->update(['status' => SupportTicket::STATUS_IN_PROGRESS]);
        }

        return response()->json([
            'success' => true,
            'reply' => [
                'id' => $reply->id,
                'message' => $reply->message,
                'user_type' => $reply->user_type,
                'created_at' => $reply->created_at->format('M d, Y H:i'),
                'created_at_human' => $reply->created_at->diffForHumans(),
            ],
        ]);
    }

    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', [
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
