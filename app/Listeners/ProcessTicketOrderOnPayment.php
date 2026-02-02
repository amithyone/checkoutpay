<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Models\TicketOrder;
use App\Services\TicketService;
use App\Services\TicketEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessTicketOrderOnPayment implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;

        // Check if this payment is for a ticket order
        // We identify ticket orders by checking if transaction_id starts with 'TKT-'
        if (!str_starts_with($payment->transaction_id ?? '', 'TKT-')) {
            return; // Not a ticket payment, skip
        }

        try {
            // Find ticket order by payment ID
            $order = TicketOrder::where('payment_id', $payment->id)->first();

            if (!$order) {
                Log::warning('Ticket order not found for payment', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                ]);
                return;
            }

            // Check if order is already confirmed
            if ($order->isConfirmed() && $order->isPaid()) {
                Log::info('Ticket order already confirmed', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
                return;
            }

            // Confirm the ticket order
            $ticketService = app(TicketService::class);
            $ticketService->confirmOrder($order);

            // Send confirmation email with tickets
            $emailService = app(TicketEmailService::class);
            $emailService->sendTicketConfirmation($order);

            Log::info('Ticket order processed successfully', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process ticket order on payment approval', [
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
