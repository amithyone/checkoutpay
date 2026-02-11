<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public bool $isOverdue,
        public bool $isForSender = false,
    ) {}

    public function envelope(): Envelope
    {
        if ($this->isForSender) {
            $subject = $this->isOverdue
                ? "Overdue: Invoice {$this->invoice->invoice_number} – {$this->invoice->client_name}"
                : "Reminder: Invoice {$this->invoice->invoice_number} due soon";
        } else {
            $subject = $this->isOverdue
                ? "Reminder: Invoice {$this->invoice->invoice_number} is overdue"
                : "Reminder: Invoice {$this->invoice->invoice_number} is due soon";
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $view = $this->isForSender
            ? 'emails.invoice-reminder-sender'
            : 'emails.invoice-reminder-receiver';

        return new Content(view: $view);
    }
}
