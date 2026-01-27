<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    /**
     * Display all events
     */
    public function events(Request $request)
    {
        $query = Event::with(['business', 'ticketTypes'])
            ->withCount(['ticketOrders as total_orders', 'tickets as total_tickets_sold']);

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by business
        if ($request->business_id) {
            $query->where('business_id', $request->business_id);
        }

        // Search
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('venue', 'like', '%' . $request->search . '%');
            });
        }

        $events = $query->latest()->paginate(20);

        return view('admin.tickets.events.index', compact('events'));
    }

    /**
     * Display all ticket orders
     */
    public function orders(Request $request)
    {
        $query = TicketOrder::with(['event', 'business', 'tickets.ticketType']);

        // Filter by event
        if ($request->event_id) {
            $query->where('event_id', $request->event_id);
        }

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        // Search
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', '%' . $request->search . '%')
                  ->orWhere('customer_name', 'like', '%' . $request->search . '%')
                  ->orWhere('customer_email', 'like', '%' . $request->search . '%');
            });
        }

        $orders = $query->latest()->paginate(20);

        $events = Event::orderBy('title')->get();

        return view('admin.tickets.orders.index', compact('orders', 'events'));
    }

    /**
     * Show ticket order details
     */
    public function showOrder(TicketOrder $order)
    {
        $order->load(['event', 'business', 'tickets.ticketType', 'payment']);

        return view('admin.tickets.orders.show', compact('order'));
    }

    /**
     * Process refund for ticket order
     */
    public function refund(Request $request, TicketOrder $order)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (!$order->canBeRefunded()) {
            return back()->with('error', 'Order cannot be refunded');
        }

        try {
            $ticketService = app(\App\Services\TicketService::class);
            $admin = auth('admin')->user();
            
            $ticketService->processRefund($order, $admin, $request->reason);

            return back()->with('success', 'Refund processed successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to process refund: ' . $e->getMessage());
        }
    }

    /**
     * Update max tickets per customer for event
     */
    public function updateMaxTickets(Request $request, Event $event)
    {
        $request->validate([
            'max_tickets_per_customer' => 'nullable|integer|min:1',
        ]);

        $event->update([
            'max_tickets_per_customer' => $request->max_tickets_per_customer,
        ]);

        return back()->with('success', 'Max tickets per customer updated');
    }
}
