<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaid extends Mailable
{
    use Queueable, SerializesModels;

    public Invoice $invoice;
    public bool $isForSender;

    /**
     * Create a new message instance.
     */
    public function __construct(Invoice $invoice, bool $isForSender = false)
    {
        $this->invoice = $invoice;
        $this->isForSender = $isForSender;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isForSender 
            ? "Invoice {$this->invoice->invoice_number} Paid - {$this->invoice->client_name}"
            : "Invoice {$this->invoice->invoice_number} Payment Confirmation";

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
            ? 'emails.invoice-paid-sender'
            : 'emails.invoice-paid-receiver';

        return new Content(
            view: $view,
            with: [
                'invoice' => $this->invoice,
            ],
        );
    }
}
