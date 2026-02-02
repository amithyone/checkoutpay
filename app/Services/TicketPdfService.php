<?php

namespace App\Services;

use App\Models\TicketOrder;
use App\Models\Ticket;
use App\Models\Event;
use App\Services\QRCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class TicketPdfService
{
    public function __construct(
        protected QRCodeService $qrCodeService
    ) {}

    /**
     * Generate PDF for a ticket order
     *
     * @param TicketOrder $order
     * @return string Full path to PDF file
     */
    public function generateOrderPdf(TicketOrder $order): string
    {
        $event = $order->event;
        $tickets = $order->tickets()->with('ticketType')->get();

        // Get ticket template (custom or default)
        $template = $this->getTicketTemplate($event);

        // Generate QR codes for all tickets
        $ticketsWithQr = [];
        foreach ($tickets as $ticket) {
            $qrCodeBase64 = $this->qrCodeService->generateBase64($ticket);
            $ticketsWithQr[] = [
                'ticket' => $ticket,
                'qr_code' => $qrCodeBase64,
            ];
        }

        // Generate PDF
        $pdf = Pdf::loadView($template, [
            'order' => $order,
            'event' => $event,
            'tickets' => $ticketsWithQr,
            'design_settings' => $event->ticket_design_settings ?? [],
        ]);

        // Save PDF to storage
        $pdfPath = 'tickets/pdfs/' . $order->order_number . '.pdf';
        Storage::disk('public')->makeDirectory('tickets/pdfs');
        Storage::disk('public')->put($pdfPath, $pdf->output());

        return Storage::disk('public')->path($pdfPath);
    }

    /**
     * Generate PDF for a single ticket
     *
     * @param Ticket $ticket
     * @return string Full path to PDF file
     */
    public function generateTicketPdf(Ticket $ticket): string
    {
        $order = $ticket->ticketOrder;
        $event = $order->event;

        // Get ticket template
        $template = $this->getTicketTemplate($event);

        // Generate QR code
        $qrCodeBase64 = $this->qrCodeService->generateBase64($ticket);

        // Generate PDF
        $pdf = Pdf::loadView($template, [
            'order' => $order,
            'event' => $event,
            'tickets' => [[
                'ticket' => $ticket,
                'qr_code' => $qrCodeBase64,
            ]],
            'design_settings' => $event->ticket_design_settings ?? [],
        ]);

        // Save PDF to storage
        $pdfPath = 'tickets/pdfs/' . $ticket->ticket_number . '.pdf';
        Storage::disk('public')->makeDirectory('tickets/pdfs');
        Storage::disk('public')->put($pdfPath, $pdf->output());

        return Storage::disk('public')->path($pdfPath);
    }

    /**
     * Get ticket template view name
     *
     * @param Event $event
     * @return string
     */
    protected function getTicketTemplate(Event $event): string
    {
        // If custom template is set, use it
        if ($event->ticket_template) {
            $customTemplate = 'tickets.templates.' . $event->ticket_template;
            if (view()->exists($customTemplate)) {
                return $customTemplate;
            }
        }

        // Default template
        return 'tickets.templates.default';
    }

    /**
     * Get PDF download URL
     *
     * @param TicketOrder $order
     * @return string
     */
    public function getPdfUrl(TicketOrder $order): string
    {
        $pdfPath = 'tickets/pdfs/' . $order->order_number . '.pdf';
        
        if (!Storage::disk('public')->exists($pdfPath)) {
            // Generate PDF if it doesn't exist
            $this->generateOrderPdf($order);
        }

        return Storage::disk('public')->url($pdfPath);
    }
}
