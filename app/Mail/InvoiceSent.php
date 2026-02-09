<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class InvoiceSent extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;
    public bool $isForSender;
    public ?string $qrCodeBase64 = null;

    /**
     * Create a new message instance.
     */
    public function __construct(Invoice $invoice, bool $isForSender = false, ?string $qrCodeBase64 = null)
    {
        $this->invoice = $invoice;
        $this->isForSender = $isForSender;
        $this->qrCodeBase64 = $qrCodeBase64;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isForSender 
            ? "Invoice {$this->invoice->invoice_number} Sent to {$this->invoice->client_name}"
            : "Invoice {$this->invoice->invoice_number} from {$this->invoice->business->name}";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $view = $this->isForSender 
            ? 'emails.invoice-sent-sender'
            : 'emails.invoice-sent-receiver';

        return new Content(
            view: $view,
            with: [
                'invoice' => $this->invoice,
                'qrCodeBase64' => $this->qrCodeBase64 ?? null,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        // PDF will be attached by the service that sends this email
        return [];
    }
}
