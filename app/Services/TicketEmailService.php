<?php

namespace App\Services;

use App\Models\TicketOrder;
use App\Services\TicketPdfService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class TicketEmailService
{
    public function __construct(
        protected TicketPdfService $pdfService
    ) {}

    /**
     * Send ticket confirmation email with PDF attachments
     *
     * @param TicketOrder $order
     * @return bool
     */
    public function sendTicketConfirmation(TicketOrder $order): bool
    {
        try {
            // Generate PDF for the order
            $pdfPath = $this->pdfService->generateOrderPdf($order);

            // Send email
            Mail::send('emails.tickets.confirmation', [
                'order' => $order,
                'event' => $order->event,
                'tickets' => $order->tickets,
            ], function ($message) use ($order, $pdfPath) {
                $message->to($order->customer_email, $order->customer_name)
                    ->subject('Your Tickets for ' . $order->event->title)
                    ->attach($pdfPath, [
                        'as' => 'tickets-' . $order->order_number . '.pdf',
                        'mime' => 'application/pdf',
                    ]);
            });

            Log::info('Ticket confirmation email sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_email' => $order->customer_email,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send ticket confirmation email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send ticket reminder email (24 hours before event)
     *
     * @param TicketOrder $order
     * @return bool
     */
    public function sendTicketReminder(TicketOrder $order): bool
    {
        try {
            Mail::send('emails.tickets.reminder', [
                'order' => $order,
                'event' => $order->event,
                'tickets' => $order->tickets,
            ], function ($message) use ($order) {
                $message->to($order->customer_email, $order->customer_name)
                    ->subject('Reminder: ' . $order->event->title . ' Tomorrow');
            });

            Log::info('Ticket reminder email sent', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send ticket reminder email', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
