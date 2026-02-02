<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;

class InvoicePdfService
{
    /**
     * Generate PDF for invoice
     */
    public function generatePdf(Invoice $invoice): ?string
    {
        try {
            $invoice->load(['business', 'items']);

            // Generate PDF using DomPDF
            $pdf = PdfFacade::loadView('invoices.pdf', [
                'invoice' => $invoice,
            ]);

            // Set paper size and orientation
            $pdf->setPaper('a4', 'portrait');

            // Save to storage
            $filename = "invoices/invoice-{$invoice->invoice_number}.pdf";
            $path = storage_path('app/public/' . $filename);
            
            // Ensure directory exists
            $directory = dirname($path);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Save PDF
            $pdf->save($path);

            return $path;
        } catch (\Exception $e) {
            \Log::error('Failed to generate invoice PDF', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get PDF download response
     */
    public function downloadPdf(Invoice $invoice)
    {
        $path = $this->generatePdf($invoice);
        
        if (!$path || !file_exists($path)) {
            abort(404, 'PDF not found');
        }

        return response()->download($path, "Invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Get PDF stream response
     */
    public function streamPdf(Invoice $invoice)
    {
        $invoice->load(['business', 'items']);

        $pdf = PdfFacade::loadView('invoices.pdf', [
            'invoice' => $invoice,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream("Invoice-{$invoice->invoice_number}.pdf");
    }
}
