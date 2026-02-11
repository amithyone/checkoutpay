<?php

namespace App\Listeners;

use App\Events\PaymentApproved;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Log;

class MarkInvoicePaidOnPaymentApproved
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function handle(PaymentApproved $event): void
    {
        $payment = $event->payment;

        // Split payment: invoice linked via invoice_payments
        $invoicePayment = InvoicePayment::with('invoice')->where('payment_id', $payment->id)->first();
        if ($invoicePayment) {
            $invoice = $invoicePayment->invoice;
            if ($invoice && !$invoice->isPaid()) {
                try {
                    $this->invoiceService->recordSplitPaymentApproved($invoice, $payment, (float) $invoicePayment->amount);
                    Log::info('Invoice split payment recorded', [
                        'invoice_id' => $invoice->id,
                        'payment_id' => $payment->id,
                        'slice_amount' => $invoicePayment->amount,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to record invoice split payment', [
                        'invoice_id' => $invoice->id,
                        'payment_id' => $payment->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            return;
        }

        // Legacy: single payment linked via invoice.payment_id
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
