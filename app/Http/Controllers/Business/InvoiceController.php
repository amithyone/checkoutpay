<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected InvoicePdfService $pdfService
    ) {}

    /**
     * Authorize access to the invoice: allow admin for any invoice, or the business that owns it.
     * Returns the Business to use for the request (owner or invoice's business when admin).
     */
    protected function authorizeInvoiceForBusiness(Invoice $invoice, string $action): Business
    {
        if (Auth::guard('admin')->check()) {
            $invoice->loadMissing('business');
            return $invoice->business;
        }
        $business = Auth::guard('business')->user();
        if (!$business || (int) $invoice->business_id !== (int) $business->id) {
            abort(403, 'You do not have permission to ' . $action . ' this invoice.');
        }
        return $business;
    }

    /**
     * Display a listing of invoices
     */
    public function index(Request $request): View
    {
        $business = Auth::guard('business')->user();

        $query = Invoice::where('business_id', $business->id)
            ->with('items')
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->date_to);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('client_email', 'like', "%{$search}%");
            });
        }

        $invoices = $query->paginate(20);

        // Calculate statistics for this business
        $stats = [
            'total' => Invoice::where('business_id', $business->id)->count(),
            'draft' => Invoice::where('business_id', $business->id)->where('status', 'draft')->count(),
            'sent' => Invoice::where('business_id', $business->id)->where('status', 'sent')->count(),
            'viewed' => Invoice::where('business_id', $business->id)->where('status', 'viewed')->count(),
            'paid' => Invoice::where('business_id', $business->id)->where('status', 'paid')->count(),
            'overdue' => Invoice::where('business_id', $business->id)->where('status', 'overdue')->count(),
            'cancelled' => Invoice::where('business_id', $business->id)->where('status', 'cancelled')->count(),
            'total_amount' => Invoice::where('business_id', $business->id)->sum('total_amount'),
            'paid_amount' => Invoice::where('business_id', $business->id)
                ->where('status', 'paid')
                ->sum('paid_amount') ?: Invoice::where('business_id', $business->id)
                ->where('status', 'paid')
                ->sum('total_amount'),
            'pending_amount' => Invoice::where('business_id', $business->id)
                ->whereNotIn('status', ['paid', 'cancelled'])
                ->sum('total_amount'),
            'this_month_total' => Invoice::where('business_id', $business->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'this_month_amount' => Invoice::where('business_id', $business->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total_amount'),
            'this_month_paid' => Invoice::where('business_id', $business->id)
                ->where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('paid_amount') ?: Invoice::where('business_id', $business->id)
                ->where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('total_amount'),
        ];

        return view('business.invoices.index', compact('invoices', 'stats'));
    }

    /**
     * Show the form for creating a new invoice
     */
    public function create(): View
    {
        $business = Auth::guard('business')->user();
        return view('business.invoices.create', compact('business'));
    }

    /**
     * Store a newly created invoice
     */
    public function store(Request $request): RedirectResponse
    {
        $business = Auth::guard('business')->user();

        $validated = $request->validate([
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'nullable|string|max:50',
            'client_address' => 'nullable|string',
            'client_company' => 'nullable|string|max:255',
            'client_tax_id' => 'nullable|string|max:100',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'currency' => 'required|string|size:3',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
            'send_email' => 'nullable|boolean',
            'allow_split_payment' => 'nullable|boolean',
            'split_installments' => 'nullable|integer|min:2|max:12',
            'split_percentages' => 'nullable|array',
            'split_percentages.*' => 'nullable|numeric|min:0|max:100',
        ]);

        if (!empty($validated['allow_split_payment']) && !empty($validated['split_percentages'])) {
            $sum = array_sum(array_map('floatval', $validated['split_percentages']));
            if (abs($sum - 100) > 0.01) {
                return back()->withInput()->with('error', 'Split percentages must add up to 100% (current total: ' . round($sum, 2) . '%).');
            }
        }

        try {
            $invoice = $this->invoiceService->createInvoice($validated, $business);

            // Send email if requested
            if ($request->boolean('send_email')) {
                $this->invoiceService->sendInvoice($invoice, true);
            }

            return redirect()->route('business.invoices.show', $invoice)
                ->with('success', 'Invoice created successfully!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to create invoice: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified invoice
     */
    public function show(Invoice $invoice): View
    {
        // Allow admin to view any invoice; otherwise require the business that owns the invoice
        if (Auth::guard('admin')->check()) {
            $invoice->load(['business', 'items', 'payment']);
            return view('business.invoices.show', compact('invoice'));
        }
        $business = Auth::guard('business')->user();
        if (!$business || (int) $invoice->business_id !== (int) $business->id) {
            abort(403, 'You do not have permission to view this invoice.');
        }

        $invoice->load(['business', 'items', 'payment']);

        return view('business.invoices.show', compact('invoice'));
    }

    /**
     * Show the form for editing the specified invoice
     */
    public function edit(Invoice $invoice): View
    {
        $business = $this->authorizeInvoiceForBusiness($invoice, 'edit');

        // Don't allow editing paid invoices
        if ($invoice->isPaid()) {
            return redirect()->route('business.invoices.show', $invoice)
                ->with('error', 'Cannot edit paid invoices.');
        }

        $invoice->load('items');

        return view('business.invoices.edit', compact('invoice', 'business'));
    }

    /**
     * Update the specified invoice
     */
    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $business = $this->authorizeInvoiceForBusiness($invoice, 'update');

        // Don't allow editing paid invoices
        if ($invoice->isPaid()) {
            return redirect()->route('business.invoices.show', $invoice)
                ->with('error', 'Cannot edit paid invoices.');
        }

        $validated = $request->validate([
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'nullable|string|max:50',
            'client_address' => 'nullable|string',
            'client_company' => 'nullable|string|max:255',
            'client_tax_id' => 'nullable|string|max:100',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'currency' => 'required|string|size:3',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'notes' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'reference_number' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
            'allow_split_payment' => 'nullable|boolean',
            'split_installments' => 'nullable|integer|min:2|max:12',
            'split_percentages' => 'nullable|array',
            'split_percentages.*' => 'nullable|numeric|min:0|max:100',
        ]);

        if (!empty($validated['allow_split_payment']) && isset($validated['split_percentages'])) {
            $sum = array_sum(array_map('floatval', $validated['split_percentages']));
            if (abs($sum - 100) > 0.01) {
                return back()->withInput()->with('error', 'Split percentages must add up to 100% (current total: ' . round($sum, 2) . '%).');
            }
            $validated['split_installments'] = $validated['split_installments'] ?? count($validated['split_percentages']);
        } elseif (empty($validated['allow_split_payment'])) {
            $validated['split_installments'] = null;
            $validated['split_percentages'] = null;
        }

        try {
            $this->invoiceService->updateInvoice($invoice, $validated);

            return redirect()->route('business.invoices.show', $invoice)
                ->with('success', 'Invoice updated successfully!');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update invoice: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified invoice
     */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoiceForBusiness($invoice, 'delete');

        // Don't allow deleting sent or paid invoices
        if (in_array($invoice->status, ['sent', 'paid'])) {
            return redirect()->route('business.invoices.show', $invoice)
                ->with('error', 'Cannot delete sent or paid invoices.');
        }

        $invoice->delete();

        return redirect()->route('business.invoices.index')
            ->with('success', 'Invoice deleted successfully!');
    }

    /**
     * Send invoice via email
     */
    public function send(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoiceForBusiness($invoice, 'send');

        try {
            $this->invoiceService->sendInvoice($invoice, true);

            return redirect()->route('business.invoices.show', $invoice)
                ->with('success', 'Invoice sent successfully to both parties!');
        } catch (\Exception $e) {
            return redirect()->route('business.invoices.show', $invoice)
                ->with('error', 'Failed to send invoice: ' . $e->getMessage());
        }
    }

    /**
     * Download invoice PDF
     */
    public function downloadPdf(Invoice $invoice)
    {
        $this->authorizeInvoiceForBusiness($invoice, 'download');

        return $this->pdfService->downloadPdf($invoice);
    }

    /**
     * View invoice PDF
     */
    public function viewPdf(Invoice $invoice)
    {
        $this->authorizeInvoiceForBusiness($invoice, 'viewPdf');

        return $this->pdfService->streamPdf($invoice);
    }

    /**
     * Mark invoice as paid (client paid business directly). Sends receipt to both parties.
     */
    public function markPaid(Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoiceForBusiness($invoice, 'mark as paid');

        if ($invoice->isPaid()) {
            return redirect()->route('business.invoices.show', $invoice)
                ->with('info', 'This invoice is already marked as paid.');
        }

        if (! in_array($invoice->status, ['sent', 'viewed', 'overdue'], true)) {
            return redirect()->route('business.invoices.show', $invoice)
                ->with('error', 'Only sent, viewed, or overdue invoices can be marked as paid.');
        }

        $ok = $this->invoiceService->markAsPaid($invoice, null, (float) $invoice->total_amount);

        if (! $ok) {
            return redirect()->route('business.invoices.show', $invoice)
                ->with('error', 'Failed to mark invoice as paid. Please try again.');
        }

        return redirect()->route('business.invoices.show', $invoice)
            ->with('success', 'Invoice marked as paid. Receipt sent to both parties.');
    }
}
