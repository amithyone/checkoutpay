<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    protected $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    /**
     * Create ticket order
     */
    public function createOrder(Request $request, Event $event)
    {
        if ($event->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Event is not available',
            ], 404);
        }

        if (!$event->isAvailableForRegistration()) {
            return response()->json([
                'success' => false,
                'message' => 'Event registration is closed',
            ], 400);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.ticket_type_id' => 'required|exists:ticket_types,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.attendees' => 'required|array',
            'items.*.attendees.*.name' => 'required|string|max:255',
            'items.*.attendees.*.email' => 'required|email|max:255',
            'customer.name' => 'required|string|max:255',
            'customer.email' => 'required|email|max:255',
            'customer.phone' => 'nullable|string|max:50',
        ]);

        // Verify ticket types belong to event
        foreach ($validated['items'] as $item) {
            $ticketType = \App\Models\TicketType::findOrFail($item['ticket_type_id']);
            if ($ticketType->event_id !== $event->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid ticket type for this event',
                ], 400);
            }
        }

        try {
            $business = $event->business;
            
            // Create order
            $order = $this->ticketService->createOrder($event, $business, $validated);

            // Create payment request
            $paymentData = $this->ticketService->createPaymentRequest($order);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'order_number' => $order->order_number,
                    'transaction_id' => $paymentData['payment']->transaction_id,
                    'account_number' => $paymentData['account_number'],
                    'account_name' => $paymentData['account_name'],
                    'bank_name' => $paymentData['bank_name'],
                    'amount' => $order->total_amount,
                    'payment_url' => route('public.tickets.payment', $order),
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Show payment page for ticket order
     */
    public function payment(TicketOrder $order)
    {
        $order->load(['event', 'items.ticketType', 'payment']);

        if ($order->status === 'confirmed') {
            return redirect()->route('public.tickets.show', $order)
                ->with('info', 'Order already confirmed');
        }

        return view('public.tickets.payment', compact('order'));
    }

    /**
     * Show ticket order details
     */
    public function show(TicketOrder $order)
    {
        $order->load(['event', 'items.ticketType', 'tickets', 'payment']);

        return view('public.tickets.show', compact('order'));
    }

    /**
     * Verify ticket by ticket number
     */
    public function verify(Request $request)
    {
        $request->validate([
            'ticket_number' => 'required|string',
        ]);

        $ticket = Ticket::where('ticket_number', $request->ticket_number)
            ->with(['event', 'ticketType', 'order'])
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        if ($ticket->order->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Ticket order not confirmed',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ticket_number' => $ticket->ticket_number,
                'attendee_name' => $ticket->attendee_name,
                'attendee_email' => $ticket->attendee_email,
                'event_title' => $ticket->event->title,
                'event_date' => $ticket->event->start_date->toDateTimeString(),
                'venue' => $ticket->event->venue_name,
                'ticket_type' => $ticket->ticketType->name,
                'check_in_status' => $ticket->check_in_status,
            ],
        ]);
    }

    /**
     * View my tickets (by email lookup)
     */
    public function myTickets(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $orders = TicketOrder::where('customer_email', $request->email)
            ->where('status', 'confirmed')
            ->with(['event', 'tickets.ticketType'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('public.tickets.my-tickets', compact('orders'));
    }
}
