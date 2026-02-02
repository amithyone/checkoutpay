<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Log;

class MarkInvoicePaidOnPaymentApproved
{
    /**
     * Create the event listener.
     */
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;

        // Check if this payment is linked to an invoice
        $invoice = Invoice::where('payment_id', $payment->id)->first();

        if ($invoice && !$invoice->isPaid()) {
            try {
                $this->invoiceService->markAsPaid(
                    $invoice,
                    $payment->id,
                    $payment->amount
                );

                Log::info('Invoice marked as paid via payment approval', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'payment_id' => $payment->id,
                    'transaction_id' => $payment->transaction_id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to mark invoice as paid on payment approval', [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
