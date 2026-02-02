<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\PaymentService;
use App\Services\InvoiceService;
use App\Services\ChargeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class InvoicePaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected InvoiceService $invoiceService,
        protected ChargeService $chargeService
    ) {}

    /**
     * Show payment page for invoice
     */
    public function show(string $code): View|RedirectResponse
    {
        $invoice = Invoice::where('payment_link_code', $code)
            ->with(['business', 'items'])
            ->firstOrFail();

        // Check if invoice is already paid
        if ($invoice->isPaid()) {
            return view('invoices.paid', compact('invoice'));
        }

        // Check if invoice is cancelled
        if ($invoice->status === 'cancelled') {
            return view('invoices.cancelled', compact('invoice'));
        }

        // Track view
        $invoice->increment('view_count');
        if (!$invoice->viewed_at) {
            $invoice->update(['viewed_at' => now()]);
            if ($invoice->status === 'draft') {
                $invoice->update(['status' => 'viewed']);
            }
        }

        // Create payment if not exists
        if (!$invoice->payment_id) {
            try {
                $paymentData = [
                    'amount' => $invoice->total_amount,
                    'payer_name' => $invoice->client_name,
                    'webhook_url' => route('invoices.payment.webhook', ['code' => $code]),
                    'service' => 'invoice',
                    'business_website_id' => null, // Invoices don't require website
                ];

                $payment = $this->paymentService->createPayment(
                    $paymentData,
                    $invoice->business,
                    request(),
                    true // This is an invoice payment, use invoice pool
                );

                // Link payment to invoice
                $invoice->update(['payment_id' => $payment->id]);

                // Store invoice reference in payment email_data
                $emailData = $payment->email_data ?? [];
                $emailData['invoice_id'] = $invoice->id;
                $emailData['invoice_number'] = $invoice->invoice_number;
                $payment->update(['email_data' => $emailData]);

                $invoice->refresh();
            } catch (\Exception $e) {
                Log::error('Failed to create payment for invoice', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $invoice->load('payment');

        return view('invoices.payment', compact('invoice'));
    }

    /**
     * Handle payment webhook for invoice
     */
    public function webhook(Request $request, string $code)
    {
        $invoice = Invoice::where('payment_link_code', $code)
            ->with(['business', 'payment'])
            ->firstOrFail();

        // If payment is approved, mark invoice as paid
        if ($invoice->payment && $invoice->payment->status === 'approved' && !$invoice->isPaid()) {
            $this->invoiceService->markAsPaid(
                $invoice,
                $invoice->payment->id,
                $invoice->payment->amount
            );
        }

        return response()->json(['success' => true]);
    }
}
