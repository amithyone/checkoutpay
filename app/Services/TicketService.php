<?php

namespace App\Services;

use App\Models\TicketOrder;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\Event;
use App\Models\Business;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketService
{
    protected $paymentService;
    protected $qrCodeService;

    public function __construct(PaymentService $paymentService, QRCodeService $qrCodeService)
    {
        $this->paymentService = $paymentService;
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Create a ticket order
     */
    public function createOrder(Event $event, Business $business, array $orderData): TicketOrder
    {
        return DB::transaction(function () use ($event, $business, $orderData) {
            $items = $orderData['items']; // [['ticket_type_id' => 1, 'quantity' => 2, 'attendees' => [...]]]
            $customerData = $orderData['customer']; // ['name', 'email', 'phone']

            // Validate ticket availability
            $this->validateTicketAvailability($event, $items);

            // Calculate total
            $totalAmount = $this->calculateTotal($items);

            // Create order
            $order = TicketOrder::create([
                'event_id' => $event->id,
                'business_id' => $business->id,
                'customer_name' => $customerData['name'],
                'customer_email' => $customerData['email'],
                'customer_phone' => $customerData['phone'] ?? null,
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_status' => 'pending',
            ]);

            // Create order items
            foreach ($items as $item) {
                $ticketType = TicketType::findOrFail($item['ticket_type_id']);
                
                TicketOrderItem::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $ticketType->price,
                    'total_price' => $ticketType->price * $item['quantity'],
                ]);
            }

            return $order;
        });
    }

    /**
     * Create payment request for ticket order
     * Integrates with existing payment system via AccountNumberService
     */
    public function createPaymentRequest(TicketOrder $order): array
    {
        $event = $order->event;
        $business = $order->business;

        // Use existing AccountNumberService to assign account number
        $accountService = app(\App\Services\AccountNumberService::class);
        $accountNumber = $accountService->assignAccountNumber($order->customer_name, $business->id);

        // Generate transaction ID
        $transactionId = 'TXN-' . time() . '-' . strtoupper(\Illuminate\Support\Str::random(8));

        // Create payment record (using existing Payment model structure)
        // Note: Payment model needs to exist - will be created if missing
        $payment = \App\Models\Payment::create([
            'transaction_id' => $transactionId,
            'amount' => $order->total_amount,
            'payer_name' => $order->customer_name,
            'webhook_url' => $business->webhook_url ?? '',
            'account_number' => $accountNumber->account_number ?? null,
            'business_id' => $business->id,
            'status' => 'pending',
            'email_data' => [
                'order_type' => 'ticket',
                'ticket_order_id' => $order->id,
                'event_id' => $event->id,
                'event_title' => $event->title,
            ],
        ]);

        // Link payment to order
        $order->update([
            'payment_id' => $payment->id,
            'payment_method' => 'bank_transfer',
        ]);

        return [
            'order' => $order,
            'payment' => $payment,
            'account_number' => $accountNumber->account_number ?? null,
            'account_name' => $accountNumber->account_name ?? null,
            'bank_name' => $accountNumber->bank_name ?? null,
        ];
    }

    /**
     * Confirm order after payment is approved
     */
    public function confirmOrder(TicketOrder $order): TicketOrder
    {
        return DB::transaction(function () use ($order) {
            // Update order status
            $order->update([
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ]);

            // Generate tickets
            $this->generateTickets($order);

            // Update ticket type sold quantities
            $this->updateTicketTypeQuantities($order);

            // Update event attendee count
            $this->updateEventAttendeeCount($order->event);

            return $order->fresh();
        });
    }

    /**
     * Generate tickets for an order
     */
    protected function generateTickets(TicketOrder $order): void
    {
        $sequence = 1;
        
        foreach ($order->items as $item) {
            $attendees = $item->metadata['attendees'] ?? [];
            
            for ($i = 0; $i < $item->quantity; $i++) {
                $attendee = $attendees[$i] ?? [
                    'name' => $order->customer_name,
                    'email' => $order->customer_email,
                ];

                // Generate QR code
                $qrCode = $this->qrCodeService->generate($order->order_number . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT));

                Ticket::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $item->ticket_type_id,
                    'event_id' => $order->event_id,
                    'attendee_name' => $attendee['name'],
                    'attendee_email' => $attendee['email'],
                    'qr_code' => $qrCode,
                ]);

                $sequence++;
            }
        }
    }

    /**
     * Validate ticket availability
     */
    protected function validateTicketAvailability(Event $event, array $items): void
    {
        foreach ($items as $item) {
            $ticketType = TicketType::findOrFail($item['ticket_type_id']);
            
            if ($ticketType->event_id !== $event->id) {
                throw new \Exception("Ticket type does not belong to this event");
            }

            if (!$ticketType->isAvailable()) {
                throw new \Exception("Ticket type '{$ticketType->name}' is not available");
            }

            $available = $ticketType->available_quantity;
            if ($item['quantity'] > $available) {
                throw new \Exception("Only {$available} tickets available for '{$ticketType->name}'");
            }

            if ($item['quantity'] < $ticketType->min_per_order) {
                throw new \Exception("Minimum {$ticketType->min_per_order} tickets required for '{$ticketType->name}'");
            }

            if ($item['quantity'] > $ticketType->max_per_order) {
                throw new \Exception("Maximum {$ticketType->max_per_order} tickets allowed for '{$ticketType->name}'");
            }
        }
    }

    /**
     * Calculate total amount
     */
    protected function calculateTotal(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            $ticketType = TicketType::findOrFail($item['ticket_type_id']);
            $total += $ticketType->price * $item['quantity'];
        }
        return $total;
    }

    /**
     * Update ticket type sold quantities
     */
    protected function updateTicketTypeQuantities(TicketOrder $order): void
    {
        foreach ($order->items as $item) {
            $ticketType = $item->ticketType;
            $ticketType->increment('sold_quantity', $item->quantity);
        }
    }

    /**
     * Update event attendee count
     */
    protected function updateEventAttendeeCount(Event $event): void
    {
        $totalTickets = Ticket::where('event_id', $event->id)
            ->whereHas('order', function ($query) {
                $query->where('status', 'confirmed');
            })
            ->count();

        $event->update(['current_attendees' => $totalTickets]);
    }
}
