<?php

namespace App\Services;

use App\Models\TicketOrder;
use App\Models\Ticket;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TicketEmailService
{
    /**
     * Send tickets to customer via email
     */
    public function sendTickets(TicketOrder $order): bool
    {
        try {
            $order->load(['event', 'tickets.ticketType']);

            Mail::send('emails.tickets', [
                'order' => $order,
                'event' => $order->event,
                'tickets' => $order->tickets,
            ], function ($message) use ($order) {
                $message->to($order->customer_email, $order->customer_name)
                    ->subject('Your Tickets for ' . $order->event->title);
            });

            Log::info('Ticket email sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_email' => $order->customer_email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send ticket email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
