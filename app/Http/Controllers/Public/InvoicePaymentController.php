<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\PaymentService;
use App\Services\InvoiceService;
use App\Services\ChargeService;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class InvoicePaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected InvoiceService $invoiceService,
        protected ChargeService $chargeService,
        protected InvoicePdfService $pdfService
    ) {}

    /**
     * Show payment page for invoice
     */
    public function show(Request $request, string $code): View|RedirectResponse
    {
        $invoice = Invoice::where('payment_link_code', $code)
            ->with(['business', 'items', 'invoicePayments.payment.accountNumberDetails'])
            ->firstOrFail();

        if ($invoice->isPaid()) {
            return view('invoices.paid', compact('invoice'));
        }

        if ($invoice->status === 'cancelled') {
            return view('invoices.cancelled', compact('invoice'));
        }

        $invoice->increment('view_count');
        if (!$invoice->viewed_at) {
            $invoice->update(['viewed_at' => now()]);
            if ($invoice->status === 'draft') {
                $invoice->update(['status' => 'viewed']);
            }
        }

        $allowSplit = (bool) $invoice->allow_split_payment;
        $paidSoFar = $allowSplit ? (float) $invoice->invoicePayments()->whereHas('payment', fn ($q) => $q->where('status', 'approved'))->sum('amount') : 0;
        $remaining = max(0, (float) $invoice->total_amount - $paidSoFar);

        if ($allowSplit) {
            // Split: don't create payment here; user creates slices via form
            $selectedPayment = null;
            $paymentId = $request->query('payment_id');
            if ($paymentId) {
                $selectedPayment = $invoice->invoicePayments()->with('payment.accountNumberDetails')->where('payment_id', $paymentId)->first()?->payment;
            }
            if (!$selectedPayment && $invoice->invoicePayments->isNotEmpty()) {
                $firstPending = $invoice->invoicePayments->first(fn ($ip) => $ip->payment && $ip->payment->status === 'pending');
                $selectedPayment = $firstPending?->payment;
            }
            $paymentSetupError = null;
            $allowedPaymentOptions = $invoice->getAllowedPaymentOptions($remaining);
            return view('invoices.payment', compact('invoice', 'allowSplit', 'remaining', 'paidSoFar', 'selectedPayment', 'paymentSetupError', 'allowedPaymentOptions'));
        }

        // Single payment: create one payment if not exists
        $paymentSetupError = null;
        if (!$invoice->payment_id) {
            try {
                $payment = $this->paymentService->createPayment([
                    'amount' => $invoice->total_amount,
                    'payer_name' => $invoice->client_name,
                    'webhook_url' => route('invoices.payment.webhook', ['code' => $code]),
                    'service' => 'invoice',
                    'business_website_id' => null,
                ], $invoice->business, request(), true);

                $invoice->update(['payment_id' => $payment->id]);
                $emailData = $payment->email_data ?? [];
                $emailData['invoice_id'] = $invoice->id;
                $emailData['invoice_number'] = $invoice->invoice_number;
                $payment->update(['email_data' => $emailData, 'expires_at' => null]);
                $invoice->refresh();
            } catch (\Exception $e) {
                Log::error('Failed to create payment for invoice', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $paymentSetupError = 'Payment could not be set up at the moment. Please try again in a few minutes or contact ' . e($invoice->business->name) . ' for support.';
            }
        }

        $invoice->load('payment');
        if ($invoice->payment && $invoice->payment->expires_at !== null) {
            $invoice->payment->update(['expires_at' => null]);
        }

        $allowSplit = false;
        $remaining = (float) $invoice->total_amount;
        $paidSoFar = 0;
        $selectedPayment = $invoice->payment;
        return view('invoices.payment', compact('invoice', 'allowSplit', 'remaining', 'paidSoFar', 'selectedPayment', 'paymentSetupError'));
    }

    /**
     * Create a split payment slice (when allow_split_payment is true)
     */
    public function createPaymentSlice(Request $request, string $code): RedirectResponse
    {
        $request->validate(['amount' => 'required|numeric|min:0.01']);

        $invoice = Invoice::where('payment_link_code', $code)->with(['business', 'invoicePayments.payment'])->firstOrFail();
        if ($invoice->isPaid() || $invoice->status === 'cancelled') {
            return redirect()->route('invoices.pay', $code)->with('error', 'Invoice is not open for payment.');
        }
        if (!(bool) $invoice->allow_split_payment) {
            return redirect()->route('invoices.pay', $code)->with('error', 'Split payment is not enabled for this invoice.');
        }

        $paidSoFar = (float) $invoice->invoicePayments()->whereHas('payment', fn ($q) => $q->where('status', 'approved'))->sum('amount');
        $remaining = max(0, (float) $invoice->total_amount - $paidSoFar);
        $amount = (float) $request->amount;
        if ($amount > $remaining || $amount < 0.01) {
            return redirect()->back()->with('error', 'Amount must be between 0.01 and the remaining balance.')->withInput();
        }
        if (!$invoice->isAllowedSplitAmount($amount, $remaining)) {
            return redirect()->back()->with('error', 'Please select one of the predefined payment options (pay in full or an installment amount).')->withInput();
        }

        try {
            $payment = $this->paymentService->createPayment([
                'amount' => $amount,
                'payer_name' => $invoice->client_name,
                'webhook_url' => route('invoices.payment.webhook', ['code' => $code]),
                'service' => 'invoice',
                'business_website_id' => null,
            ], $invoice->business, request(), true);

            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'amount' => $amount,
            ]);
            if (!$invoice->payment_id) {
                $invoice->update(['payment_id' => $payment->id]);
            }
            $emailData = $payment->email_data ?? [];
            $emailData['invoice_id'] = $invoice->id;
            $emailData['invoice_number'] = $invoice->invoice_number;
            $payment->update(['email_data' => $emailData, 'expires_at' => null]);
        } catch (\Exception $e) {
            Log::error('Failed to create invoice split payment', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Could not create payment. Please try again.')->withInput();
        }

        return redirect()->route('invoices.pay', ['code' => $code, 'payment_id' => $payment->id])
            ->with('success', 'Payment details generated. Transfer the amount below.');
    }

    /**
     * Public view invoice (read-only). Anyone with the link can view.
     */
    public function view(string $code): View
    {
        $invoice = Invoice::where('payment_link_code', $code)
            ->with(['business', 'items'])
            ->firstOrFail();

        return view('invoices.view', compact('invoice'));
    }

    /**
     * Public view/download invoice PDF. Anyone with the link can view.
     */
    public function viewPdf(string $code)
    {
        $invoice = Invoice::where('payment_link_code', $code)
            ->with(['business', 'items'])
            ->firstOrFail();

        return $this->pdfService->streamPdf($invoice);
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
