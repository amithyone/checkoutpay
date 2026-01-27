<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketOrderController extends Controller
{
    /**
     * Display a listing of ticket orders
     */
    public function index(Request $request)
    {
        $business = Auth::guard('business')->user();

        $query = TicketOrder::where('business_id', $business->id)
            ->with(['event', 'tickets.ticketType']);

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

        $orders = $query->latest()->paginate(20);

        $events = Event::where('business_id', $business->id)
            ->orderBy('title')
            ->get();

        return view('business.tickets.orders.index', compact('orders', 'events'));
    }

    /**
     * Display the specified ticket order
     */
    public function show(TicketOrder $order)
    {
        $business = Auth::guard('business')->user();

        if ($order->business_id !== $business->id) {
            abort(403);
        }

        $order->load(['event', 'tickets.ticketType', 'payment']);

        return view('business.tickets.orders.show', compact('order'));
    }
}
