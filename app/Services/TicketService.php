<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Payment;
use App\Models\Business;
use App\Services\PaymentService;
use App\Services\QRCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketService
{
    public function __construct(
        protected PaymentService $paymentService,
        protected QRCodeService $qrCodeService
    ) {}

    /**
     * Create a ticket order and payment
     *
     * @param Event $event
     * @param array $ticketData Array of ['ticket_type_id' => quantity]
     * @param array $customerData ['name', 'email', 'phone']
     * @param Business $business
     * @return TicketOrder
     */
    public function createOrder(Event $event, array $ticketData, array $customerData, Business $business, ?\App\Models\EventCoupon $coupon = null): TicketOrder
    {
        return DB::transaction(function () use ($event, $ticketData, $customerData, $business, $coupon) {
            // Validate ticket types and calculate total
            $totalAmount = 0;
            $ticketItems = [];

            foreach ($ticketData as $ticketTypeId => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $ticketType = TicketType::where('event_id', $event->id)
                    ->where('id', $ticketTypeId)
                    ->firstOrFail();

                // Check availability
                if (!$ticketType->isAvailable()) {
                    throw new \Exception("Ticket type '{$ticketType->name}' is not available");
                }

                // Check quantity
                if ($ticketType->remaining_quantity < $quantity) {
                    throw new \Exception("Only {$ticketType->remaining_quantity} tickets available for '{$ticketType->name}'");
                }

                $subtotal = $ticketType->price * $quantity;
                $totalAmount += $subtotal;

                $ticketItems[] = [
                    'ticket_type' => $ticketType,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                ];
            }

            // Allow free tickets (totalAmount can be 0)
            if ($totalAmount < 0) {
                throw new \Exception('Invalid ticket selection');
            }

            // Check max tickets per customer
            $totalTickets = array_sum(array_column($ticketItems, 'quantity'));
            $maxTickets = $event->max_tickets_per_customer;
            if ($maxTickets && $totalTickets > $maxTickets) {
                throw new \Exception("Maximum {$maxTickets} tickets per customer");
            }

            // Apply coupon discount if provided and valid
            $discountAmount = 0;
            $couponId = null;
            if ($coupon && $coupon->isValid() && $coupon->event_id === $event->id) {
                $discountAmount = $coupon->calculateDiscount($totalAmount);
                $totalAmount = max(0, $totalAmount - $discountAmount);
                $couponId = $coupon->id;
                $coupon->incrementUsage();
            }

            // Calculate commission (on original amount before discount)
            $originalAmount = $totalAmount + $discountAmount;
            $commissionPercentage = $event->commission_percentage ?? 0;
            $commissionAmount = ($originalAmount * $commissionPercentage) / 100;

            // Create ticket order
            $order = TicketOrder::create([
                'event_id' => $event->id,
                'business_id' => $business->id,
                'customer_name' => $customerData['name'],
                'customer_email' => $customerData['email'],
                'customer_phone' => $customerData['phone'] ?? null,
                'total_amount' => $totalAmount,
                'commission_amount' => $commissionAmount,
                'payment_status' => TicketOrder::PAYMENT_STATUS_PENDING,
                'status' => TicketOrder::STATUS_PENDING,
                'coupon_id' => $couponId,
                'discount_amount' => $discountAmount,
            ]);

            $payment = null;
            
            // Only create payment if total amount is greater than 0
            if ($totalAmount > 0) {
                // Generate webhook URL for ticket payment confirmation
                $webhookUrl = route('tickets.payment.webhook', ['orderNumber' => $order->order_number]);
                $returnUrl = route('tickets.order', ['orderNumber' => $order->order_number]);
                
                // Create payment using existing PaymentService
                $payment = $this->paymentService->createPayment([
                    'amount' => $totalAmount,
                    'payer_name' => $customerData['name'],
                    'service' => 'ticket_sale',
                    'transaction_id' => 'TKT-' . $order->order_number,
                    'webhook_url' => $webhookUrl,
                    'return_url' => $returnUrl,
                    'business_website_id' => null,
                ], $business);

                // Link payment to order
                $order->update(['payment_id' => $payment->id]);
            } else {
                // Free tickets - auto-confirm
                $order->update([
                    'payment_status' => TicketOrder::PAYMENT_STATUS_PAID,
                    'status' => TicketOrder::STATUS_CONFIRMED,
                    'purchased_at' => now(),
                ]);
            }

            // Create ticket records (but don't generate QR codes until payment is confirmed)
            foreach ($ticketItems as $item) {
                for ($i = 0; $i < $item['quantity']; $i++) {
                    Ticket::create([
                        'ticket_order_id' => $order->id,
                        'ticket_type_id' => $item['ticket_type']->id,
                        'status' => Ticket::STATUS_VALID,
                    ]);
                }

                // Update ticket type sold count
                $item['ticket_type']->increment('quantity_sold', $item['quantity']);
            }

            return $order->fresh();
        });
    }

    /**
     * Confirm ticket order after payment approval
     *
     * @param TicketOrder $order
     * @return TicketOrder
     */
    public function confirmOrder(TicketOrder $order): TicketOrder
    {
        return DB::transaction(function () use ($order) {
            // Update order status
            $order->update([
                'payment_status' => TicketOrder::PAYMENT_STATUS_PAID,
                'status' => TicketOrder::STATUS_CONFIRMED,
                'purchased_at' => now(),
            ]);

            // Generate QR codes for all tickets
            foreach ($order->tickets as $ticket) {
                $this->qrCodeService->generateForTicket($ticket);
            }

            return $order->fresh();
        });
    }

    /**
     * Process refund for ticket order
     *
     * @param TicketOrder $order
     * @param Admin $admin
     * @param string $reason
     * @return TicketOrder
     */
    public function processRefund(TicketOrder $order, $admin, string $reason): TicketOrder
    {
        if (!$order->canBeRefunded()) {
            throw new \Exception('Order cannot be refunded');
        }

        return DB::transaction(function () use ($order, $admin, $reason) {
            // Update order status
            $order->update([
                'payment_status' => TicketOrder::PAYMENT_STATUS_REFUNDED,
                'status' => TicketOrder::STATUS_CANCELLED,
                'refund_reason' => $reason,
                'refunded_by' => $admin->id,
                'refunded_at' => now(),
            ]);

            // Update ticket statuses
            $order->tickets()->update([
                'status' => Ticket::STATUS_REFUNDED,
            ]);

            // Update ticket type sold counts
            foreach ($order->tickets as $ticket) {
                $ticket->ticketType->decrement('quantity_sold');
            }

            // TODO: Process actual refund payment (integrate with payment gateway refund)

            return $order->fresh();
        });
    }

    /**
     * Check in a ticket
     *
     * @param Ticket $ticket
     * @param Admin $admin
     * @param string $method
     * @param string|null $location
     * @param string|null $notes
     * @return Ticket
     */
    public function checkInTicket(Ticket $ticket, $admin, string $method = 'qr_scan', ?string $location = null, ?string $notes = null): Ticket
    {
        if (!$ticket->canBeCheckedIn()) {
            throw new \Exception('Ticket cannot be checked in');
        }

        return DB::transaction(function () use ($ticket, $admin, $method, $location, $notes) {
            // Update ticket status
            $ticket->update([
                'status' => Ticket::STATUS_USED,
                'checked_in_at' => now(),
                'checked_in_by' => $admin->id,
            ]);

            // Create check-in record
            \App\Models\TicketCheckIn::create([
                'ticket_id' => $ticket->id,
                'checked_in_by' => $admin->id,
                'check_in_method' => $method,
                'location' => $location,
                'notes' => $notes,
            ]);

            return $ticket->fresh();
        });
    }
}
