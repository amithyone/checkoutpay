<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\TicketOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TicketOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:business');
    }

    /**
     * Display orders for an event
     */
    public function index(Event $event)
    {
        $business = Auth::guard('business')->user();
        
        if ($event->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $orders = TicketOrder::where('event_id', $event->id)
            ->with(['items.ticketType', 'tickets'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('business.events.orders', compact('event', 'orders'));
    }

    /**
     * Show order details
     */
    public function show(TicketOrder $order)
    {
        $business = Auth::guard('business')->user();
        
        if ($order->business_id !== $business->id) {
            abort(403, 'Unauthorized');
        }

        $order->load(['event', 'items.ticketType', 'tickets', 'payment']);

        return view('business.orders.show', compact('order'));
    }
}
