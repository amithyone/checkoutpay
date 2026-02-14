<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Business;
use App\Services\InvoiceService;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
        protected InvoicePdfService $pdfService
    ) {}

    /**
     * Display a listing of invoices
     */
    public function index(Request $request): View
    {
        $query = Invoice::with(['business', 'items'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by business
        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
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
                  ->orWhere('client_email', 'like', "%{$search}%")
                  ->orWhereHas('business', function ($bq) use ($search) {
                      $bq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->paginate(30);
        $businesses = Business::orderBy('name')->get();

        // Calculate statistics
        $stats = [
            'total' => Invoice::count(),
            'draft' => Invoice::where('status', 'draft')->count(),
            'sent' => Invoice::where('status', 'sent')->count(),
            'viewed' => Invoice::where('status', 'viewed')->count(),
            'paid' => Invoice::where('status', 'paid')->count(),
            'overdue' => Invoice::where('status', 'overdue')->count(),
            'cancelled' => Invoice::where('status', 'cancelled')->count(),
            'total_amount' => Invoice::sum('total_amount'),
            'paid_amount' => Invoice::where('status', 'paid')->sum('paid_amount') ?: Invoice::where('status', 'paid')->sum('total_amount'),
            'pending_amount' => Invoice::whereNotIn('status', ['paid', 'cancelled'])->sum('total_amount'),
            'this_month_total' => Invoice::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'this_month_amount' => Invoice::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total_amount'),
            'this_month_paid' => Invoice::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('paid_amount') ?: Invoice::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('total_amount'),
        ];

        return view('admin.invoices.index', compact('invoices', 'businesses', 'stats'));
    }

    /**
     * Display the specified invoice
     */
    public function show(Invoice $invoice): View
    {
        $invoice->load(['business', 'items', 'payment']);
        return view('admin.invoices.show', compact('invoice'));
    }

    /**
     * Show the form for editing the specified invoice
     */
    public function edit(Invoice $invoice): View
    {
        $invoice->load(['business', 'items']);
        $businesses = Business::orderBy('name')->get();
        return view('admin.invoices.edit', compact('invoice', 'businesses'));
    }

    /**
     * Update the specified invoice
     */
    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
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
            'status' => 'required|in:draft,sent,viewed,paid,overdue,cancelled',
            'paid_confirmation_notes' => 'nullable|string|max:500',
            'allow_split_payment' => 'nullable|boolean',
            'split_installments' => 'nullable|integer|min:2|max:12',
            'split_percentages' => 'nullable|array',
            'split_percentages.*' => 'nullable|numeric|min:0|max:100',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        // When split payment is enabled, percentages must sum to 100
        $allowSplit = filter_var($request->input('allow_split_payment'), FILTER_VALIDATE_BOOLEAN);
        if ($allowSplit && is_array($request->input('split_percentages'))) {
            $sum = array_sum(array_map('floatval', $request->input('split_percentages')));
            if (abs($sum - 100) > 0.01) {
                return back()->withInput()->withErrors(['split_percentages' => 'Split percentages must add up to 100%.']);
            }
        }

        try {
            // Handle logo upload
            $logoPath = null;
            if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
                // Delete old logo if exists
                if ($invoice->logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($invoice->logo)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($invoice->logo);
                }
                // Store new logo
                $logoPath = $request->file('logo')->store('invoices/logos', 'public');
            }

            $updateData = [
                'business_id' => $validated['business_id'],
                'client_name' => $validated['client_name'],
                'client_email' => $validated['client_email'],
                'client_phone' => $validated['client_phone'] ?? null,
                'client_address' => $validated['client_address'] ?? null,
                'client_company' => $validated['client_company'] ?? null,
                'client_tax_id' => $validated['client_tax_id'] ?? null,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'] ?? null,
                'currency' => $validated['currency'],
                'tax_rate' => $validated['tax_rate'] ?? 0,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'terms_and_conditions' => $validated['terms_and_conditions'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'status' => $validated['status'],
                'paid_confirmation_notes' => $validated['paid_confirmation_notes'] ?? null,
                'allow_split_payment' => $allowSplit,
                'split_installments' => $allowSplit ? (int) ($validated['split_installments'] ?? 2) : null,
                'split_percentages' => $allowSplit && ! empty($validated['split_percentages'] ?? [])
                    ? array_map('floatval', array_values($validated['split_percentages']))
                    : null,
            ];

            if ($logoPath) {
                $updateData['logo'] = $logoPath;
            }

            $wasAlreadyPaid = $invoice->status === 'paid' && $invoice->paid_at !== null;

            $invoice->update($updateData);

            // Update items
            $invoice->items()->delete();
            foreach ($validated['items'] as $index => $item) {
                $invoice->items()->create([
                    'sort_order' => $index,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? 'unit',
                    'unit_price' => $item['unit_price'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $invoice->calculateTotals();

            // If admin just marked invoice as paid (was not paid before), set paid_at/paid_amount, credit business, and send receipt to both parties
            if ($validated['status'] === 'paid' && !$wasAlreadyPaid) {
                $invoice->refresh();
                $this->invoiceService->markAsPaid($invoice, null, (float) $invoice->total_amount);
            }

            return redirect()->route('admin.invoices.show', $invoice)
                ->with('success', 'Invoice updated successfully!' . ($validated['status'] === 'paid' && !$wasAlreadyPaid ? ' Receipt sent to both parties.' : ''));
        } catch (\Exception $e) {
            return back()->withInput()->with('error', 'Failed to update invoice: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified invoice
     */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        $invoice->delete();

        return redirect()->route('admin.invoices.index')
            ->with('success', 'Invoice deleted successfully!');
    }

    /**
     * Download invoice PDF
     */
    public function downloadPdf(Invoice $invoice)
    {
        return $this->pdfService->downloadPdf($invoice);
    }

    /**
     * View invoice PDF
     */
    public function viewPdf(Invoice $invoice)
    {
        return $this->pdfService->streamPdf($invoice);
    }

    /**
     * Send invoice via email
     */
    public function send(Invoice $invoice): RedirectResponse
    {
        try {
            $this->invoiceService->sendInvoice($invoice, true);

            return redirect()->route('admin.invoices.show', $invoice)
                ->with('success', 'Invoice sent successfully to both parties!');
        } catch (\Exception $e) {
            return redirect()->route('admin.invoices.show', $invoice)
                ->with('error', 'Failed to send invoice: ' . $e->getMessage());
        }
    }

    /**
     * Mark invoice as paid from admin (e.g. after business confirmed via email). Sends receipt to both parties.
     */
    public function markPaid(Request $request, Invoice $invoice): RedirectResponse
    {
        $request->validate([
            'paid_confirmation_notes' => 'nullable|string|max:500',
        ]);

        if ($invoice->isPaid()) {
            return redirect()->route('admin.invoices.index')
                ->with('info', 'Invoice ' . $invoice->invoice_number . ' is already marked as paid.');
        }

        try {
            if ($request->filled('paid_confirmation_notes')) {
                $invoice->update(['paid_confirmation_notes' => $request->paid_confirmation_notes]);
            }
            $invoice->refresh();
            $this->invoiceService->markAsPaid($invoice, null, (float) $invoice->total_amount);

            return redirect()->route('admin.invoices.index')
                ->with('success', 'Invoice ' . $invoice->invoice_number . ' marked as paid. Receipt sent to both parties.');
        } catch (\Exception $e) {
            return redirect()->route('admin.invoices.index')
                ->with('error', 'Failed to mark invoice as paid: ' . $e->getMessage());
        }
    }
}
