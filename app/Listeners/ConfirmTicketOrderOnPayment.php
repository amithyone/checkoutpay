<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Models\TicketOrder;
use App\Services\TicketService;
use App\Services\TicketEmailService;
use Illuminate\Support\Facades\Log;

class ConfirmTicketOrderOnPayment
{
    protected $ticketService;
    protected $emailService;

    public function __construct(TicketService $ticketService, TicketEmailService $emailService)
    {
        $this->ticketService = $ticketService;
        $this->emailService = $emailService;
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;

        // Check if payment is for a ticket order
        $emailData = $payment->email_data ?? [];
        if (($emailData['order_type'] ?? null) !== 'ticket') {
            return; // Not a ticket payment, skip
        }

        $ticketOrderId = $emailData['ticket_order_id'] ?? null;
        if (!$ticketOrderId) {
            return;
        }

        try {
            $order = TicketOrder::find($ticketOrderId);
            if (!$order) {
                Log::warning('Ticket order not found for payment', [
                    'payment_id' => $payment->id,
                    'ticket_order_id' => $ticketOrderId,
                ]);
                return;
            }

            // Ensure payment is linked
            if ($order->payment_id !== $payment->id) {
                $order->update(['payment_id' => $payment->id]);
            }

            // Confirm order and generate tickets
            $this->ticketService->confirmOrder($order);

            // Send tickets via email
            $this->emailService->sendTickets($order);

            Log::info('Ticket order confirmed after payment', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to confirm ticket order after payment', [
                'payment_id' => $payment->id,
                'ticket_order_id' => $ticketOrderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
