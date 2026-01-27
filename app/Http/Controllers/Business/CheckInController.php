<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\EventCheckIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckInController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:business');
    }

    /**
     * Show check-in interface
     */
    public function index(Event $event)
    {
        $business = Auth::guard('business')->user();
        
        if ($event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $checkedInCount = Ticket::where('event_id', $event->id)
            ->where('check_in_status', 'checked_in')
            ->count();

        $totalTickets = Ticket::where('event_id', $event->id)
            ->whereHas('order', function ($query) {
                $query->where('status', 'confirmed');
            })
            ->count();

        return view('business.events.check-in', compact('event', 'checkedInCount', 'totalTickets'));
    }

    /**
     * Check in a ticket by QR code or ticket number
     */
    public function checkIn(Request $request, Event $event)
    {
        $business = Auth::guard('business')->user();
        
        if ($event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'ticket_number' => 'required|string',
        ]);

        $ticket = Ticket::where('ticket_number', $request->ticket_number)
            ->where('event_id', $event->id)
            ->whereHas('order', function ($query) {
                $query->where('status', 'confirmed');
            })
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found or invalid',
            ], 404);
        }

        if ($ticket->isCheckedIn()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket already checked in',
                'ticket' => [
                    'ticket_number' => $ticket->ticket_number,
                    'attendee_name' => $ticket->attendee_name,
                    'checked_in_at' => $ticket->checked_in_at->toDateTimeString(),
                ],
            ]);
        }

        DB::transaction(function () use ($ticket, $business) {
            $ticket->update([
                'check_in_status' => 'checked_in',
                'checked_in_at' => now(),
                'checked_in_by' => $business->id,
            ]);

            EventCheckIn::create([
                'ticket_id' => $ticket->id,
                'event_id' => $ticket->event_id,
                'checked_in_by' => $business->id,
                'check_in_method' => 'qr_scan',
                'check_in_time' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Ticket checked in successfully',
            'ticket' => [
                'ticket_number' => $ticket->ticket_number,
                'attendee_name' => $ticket->attendee_name,
                'attendee_email' => $ticket->attendee_email,
                'ticket_type' => $ticket->ticketType->name,
            ],
        ]);
    }

    /**
     * Get check-in statistics
     */
    public function statistics(Event $event)
    {
        $business = Auth::guard('business')->user();
        
        if ($event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $checkedIn = Ticket::where('event_id', $event->id)
            ->where('check_in_status', 'checked_in')
            ->count();

        $totalTickets = Ticket::where('event_id', $event->id)
            ->whereHas('order', function ($query) {
                $query->where('status', 'confirmed');
            })
            ->count();

        $recentCheckIns = EventCheckIn::where('event_id', $event->id)
            ->with('ticket.ticketType')
            ->orderBy('check_in_time', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'checked_in' => $checkedIn,
            'total_tickets' => $totalTickets,
            'percentage' => $totalTickets > 0 ? round(($checkedIn / $totalTickets) * 100, 2) : 0,
            'recent_check_ins' => $recentCheckIns->map(function ($checkIn) {
                return [
                    'ticket_number' => $checkIn->ticket->ticket_number,
                    'attendee_name' => $checkIn->ticket->attendee_name,
                    'ticket_type' => $checkIn->ticket->ticketType->name,
                    'check_in_time' => $checkIn->check_in_time->toDateTimeString(),
                ];
            }),
        ]);
    }
}
