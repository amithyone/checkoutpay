<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    /**
     * Show support tickets
     */
    public function index(Request $request)
    {
        $business = auth('business')->user();
        
        $query = $business->supportTickets()->with('replies')->latest();

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $tickets = $query->paginate(20);

        return view('business.support.index', compact('tickets'));
    }

    /**
     * Show create ticket form
     */
    public function create()
    {
        return view('business.support.create');
    }

    /**
     * Store new ticket
     */
    public function store(Request $request)
    {
        $business = auth('business')->user();

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
            'priority' => ['required', Rule::in([
                SupportTicket::PRIORITY_LOW,
                SupportTicket::PRIORITY_MEDIUM,
                SupportTicket::PRIORITY_HIGH,
                SupportTicket::PRIORITY_URGENT,
            ])],
        ]);

        $ticket = SupportTicket::create([
            'business_id' => $business->id,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'priority' => $validated['priority'],
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        return redirect()->route('business.support.show', $ticket)
            ->with('success', 'Support ticket created successfully. Our team will respond soon.');
    }

    /**
     * Show ticket details
     */
    public function show(SupportTicket $ticket)
    {
        $business = auth('business')->user();

        if ($ticket->business_id !== $business->id) {
            abort(403);
        }

        $ticket->load(['replies' => function ($query) {
            $query->latest();
        }]);

        return view('business.support.show', compact('ticket'));
    }

    /**
     * Reply to ticket
     */
    public function reply(Request $request, SupportTicket $ticket)
    {
        $business = auth('business')->user();

        if ($ticket->business_id !== $business->id) {
            abort(403);
        }

        $validated = $request->validate([
            'message' => 'required|string|min:10',
        ]);

        SupportTicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $business->id,
            'user_type' => 'business',
            'message' => $validated['message'],
        ]);

        // Update ticket status if it was resolved/closed
        if (in_array($ticket->status, [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])) {
            $ticket->update(['status' => SupportTicket::STATUS_OPEN]);
        }

        return back()->with('success', 'Reply sent successfully.');
    }
}
