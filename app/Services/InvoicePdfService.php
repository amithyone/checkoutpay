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
     * 
     * @param Invoice $invoice
     * @param string|null $qrCodeBase64 QR code as base64 string (optional)
     * @param bool $isPaid Whether to show PAID status (optional)
     * @return string|null Path to generated PDF
     */
    public function generatePdf(Invoice $invoice, ?string $qrCodeBase64 = null, bool $isPaid = false): ?string
    {
        try {
            $invoice->load(['business', 'items']);

            // Generate PDF using DomPDF
            $pdf = PdfFacade::loadView('invoices.pdf', [
                'invoice' => $invoice,
                'qrCodeBase64' => $qrCodeBase64,
                'isPaid' => $isPaid || $invoice->isPaid(),
            ]);

            // Set paper size and orientation
            $pdf->setPaper('a4', 'portrait');

            // Save to storage
            $filename = $isPaid || $invoice->isPaid() 
                ? "invoices/invoice-{$invoice->invoice_number}-PAID.pdf"
                : "invoices/invoice-{$invoice->invoice_number}.pdf";
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
        // Generate QR code if not paid
        $qrCodeBase64 = null;
        if (!$invoice->isPaid()) {
            $invoiceService = app(\App\Services\InvoiceService::class);
            $qrCodeBase64 = $invoiceService->generateQrCode($invoice);
        }
        
        $path = $this->generatePdf($invoice, $qrCodeBase64, $invoice->isPaid());
        
        if (!$path || !file_exists($path)) {
            abort(404, 'PDF not found');
        }

        $filename = $invoice->isPaid() 
            ? "Invoice-{$invoice->invoice_number}-PAID.pdf"
            : "Invoice-{$invoice->invoice_number}.pdf";

        return response()->download($path, $filename);
    }

    /**
     * Get PDF stream response
     */
    public function streamPdf(Invoice $invoice)
    {
        $invoice->load(['business', 'items']);

        // Generate QR code if not paid
        $qrCodeBase64 = null;
        if (!$invoice->isPaid()) {
            $invoiceService = app(\App\Services\InvoiceService::class);
            $qrCodeBase64 = $invoiceService->generateQrCode($invoice);
        }

        $pdf = PdfFacade::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'qrCodeBase64' => $qrCodeBase64,
            'isPaid' => $invoice->isPaid(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        $filename = $invoice->isPaid() 
            ? "Invoice-{$invoice->invoice_number}-PAID.pdf"
            : "Invoice-{$invoice->invoice_number}.pdf";

        return $pdf->stream($filename);
    }
}
