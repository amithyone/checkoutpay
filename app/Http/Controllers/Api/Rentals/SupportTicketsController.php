<?php

namespace App\Http\Controllers\Api\Rentals;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Renter;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportTicketsController extends Controller
{
    /**
     * GET /api/v1/rentals/support/tickets
     */
    public function index(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $businessId = Business::query()
            ->whereRaw('LOWER(email) = LOWER(?)', [$renter->email])
            ->value('id');

        $q = SupportTicket::query()->latest();

        if ($businessId) {
            $q->where('business_id', $businessId);
        } else {
            $q->where('visitor_email', $renter->email);
        }

        $tickets = $q->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * POST /api/v1/rentals/support/tickets
     */
    public function store(Request $request)
    {
        /** @var Renter $renter */
        $renter = $request->user();

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $businessId = Business::query()
            ->whereRaw('LOWER(email) = LOWER(?)', [$renter->email])
            ->value('id');

        $ticket = SupportTicket::query()->create([
            'channel' => 'rentals_app',
            'ticket_number' => 'RNT-'.strtoupper(Str::random(8)),
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'visitor_name' => $renter->name,
            'visitor_email' => $renter->email,
            'visitor_phone' => $renter->phone,
            'business_id' => $businessId,
            'public_token' => Str::random(48),
            'status' => SupportTicket::STATUS_OPEN,
            'priority' => SupportTicket::PRIORITY_MEDIUM,
            'last_message_at' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $ticket], 201);
    }

    /**
     * GET /api/v1/rentals/support/tickets/{ticket}/messages
     */
    public function messages(Request $request, SupportTicket $ticket)
    {
        if (! $this->canAccessTicket($request, $ticket)) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $rows = SupportTicketReply::query()
            ->where('ticket_id', $ticket->id)
            ->where('is_internal_note', false)
            ->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * POST /api/v1/rentals/support/tickets/{ticket}/messages
     */
    public function postMessage(Request $request, SupportTicket $ticket)
    {
        if (! $this->canAccessTicket($request, $ticket)) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $reply = SupportTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_type' => 'renter',
            'message' => $validated['message'],
            'is_internal_note' => false,
        ]);

        $ticket->update(['last_message_at' => now(), 'admin_unread_count' => ($ticket->admin_unread_count ?? 0) + 1]);

        return response()->json(['success' => true, 'data' => $reply], 201);
    }

    protected function canAccessTicket(Request $request, SupportTicket $ticket): bool
    {
        /** @var Renter $renter */
        $renter = $request->user();

        if (strcasecmp((string) $ticket->visitor_email, (string) $renter->email) === 0) {
            return true;
        }

        $businessId = Business::query()
            ->whereRaw('LOWER(email) = LOWER(?)', [$renter->email])
            ->value('id');

        return $businessId && (int) $ticket->business_id === (int) $businessId;
    }
}
