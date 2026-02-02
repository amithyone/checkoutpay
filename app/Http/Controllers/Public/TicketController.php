<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseTicketRequest;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Services\TicketService;
use App\Services\TicketPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected TicketPdfService $pdfService
    ) {}

    /**
     * Display event ticket page
     */
    public function show(Event $event)
    {
        // Only show published events
        if (!$event->isPublished()) {
            abort(404);
        }

        // Track view count
        $event->incrementViews();

        $event->load(['ticketTypes' => function ($query) {
            $query->available()->orderBy('price');
        }, 'activeCoupons', 'speakers']);

        // Prepare data for JavaScript
        $ticketTypesData = $event->ticketTypes->map(function($t) {
            return ['id' => $t->id, 'price' => $t->price];
        })->values();
        
        $couponsData = $event->activeCoupons->map(function($c) {
            return [
                'id' => $c->id,
                'code' => $c->code,
                'discount_type' => $c->discount_type,
                'discount_value' => $c->discount_value
            ];
        })->values();

        return view('public.tickets.show', compact('event', 'ticketTypesData', 'couponsData'));
    }

    /**
     * Purchase tickets
     */
    public function purchase(PurchaseTicketRequest $request, Event $event)
    {
        // Verify event is published
        if (!$event->isPublished()) {
            return back()->with('error', 'Event is not available for ticket sales');
        }

        // Prepare ticket data
        $ticketData = [];
        foreach ($request->tickets as $ticketRequest) {
            if (($ticketRequest['quantity'] ?? 0) > 0) {
                $ticketData[$ticketRequest['ticket_type_id']] = $ticketRequest['quantity'];
            }
        }

        // Validate and get coupon if provided
        $coupon = null;
        if ($request->filled('applied_coupon_id')) {
            $coupon = \App\Models\EventCoupon::where('id', $request->applied_coupon_id)
                ->where('event_id', $event->id)
                ->first();
            
            if ($coupon && !$coupon->isValid()) {
                return back()->withInput()->with('error', 'Coupon code is not valid or has expired');
            }
        }

        try {
            // Create ticket order and payment
            $order = $this->ticketService->createOrder(
                $event,
                $ticketData,
                [
                    'name' => $request->customer_name,
                    'email' => $request->customer_email,
                    'phone' => $request->customer_phone,
                ],
                $event->business,
                $coupon
            );

            // Handle free tickets vs paid tickets
            if ($order->total_amount == 0) {
                // Free tickets - redirect to order confirmation
                return redirect()->route('tickets.order', ['orderNumber' => $order->order_number])
                    ->with('success', 'Free tickets confirmed! Your tickets are ready.');
            } else {
                // Paid tickets - redirect to payment page
                if ($order->payment && $order->payment->account_number) {
                    return redirect()->route('checkout.payment', ['transactionId' => $order->payment->transaction_id])
                        ->with('success', 'Ticket order created! Please complete payment.');
                }
                
                Log::error('Ticket payment creation failed', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_id' => $order->payment_id,
                ]);
                
                return back()->with('error', 'Payment creation failed. Please try again.')->withInput();
            }
        } catch (\Exception $e) {
            Log::error('Ticket purchase error', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'Failed to create ticket order: ' . $e->getMessage());
        }
    }

    /**
     * Display ticket order details
     */
    public function order(string $orderNumber)
    {
        $order = TicketOrder::where('order_number', $orderNumber)
            ->with(['event', 'tickets.ticketType', 'payment'])
            ->firstOrFail();

        return view('public.tickets.order', compact('order'));
    }

    /**
     * Download ticket PDF
     */
    public function download(string $orderNumber)
    {
        $order = TicketOrder::where('order_number', $orderNumber)
            ->with(['event', 'tickets.ticketType'])
            ->firstOrFail();

        // Only allow download if order is paid
        if (!$order->isPaid()) {
            abort(403, 'Tickets are only available after payment confirmation');
        }

        try {
            $pdfPath = $this->pdfService->generateOrderPdf($order);

            return response()->download($pdfPath, 'tickets-' . $order->order_number . '.pdf');
        } catch (\Exception $e) {
            Log::error('Ticket PDF download error', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to generate ticket PDF');
        }
    }

    /**
     * Handle payment webhook for ticket orders
     * This is called when payment is approved via the payment gateway
     */
    public function paymentWebhook(Request $request, string $orderNumber)
    {
        try {
            $order = TicketOrder::where('order_number', $orderNumber)
                ->with('payment')
                ->firstOrFail();

            // Verify payment is approved
            if ($order->payment && $order->payment->status === \App\Models\Payment::STATUS_APPROVED) {
                // Confirm the order (this will generate QR codes)
                $this->ticketService->confirmOrder($order);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Ticket order confirmed',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment not approved yet',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Ticket payment webhook error', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhook processing failed',
            ], 500);
        }
    }
}
