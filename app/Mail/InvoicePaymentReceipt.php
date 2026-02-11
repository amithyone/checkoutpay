<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaymentReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public Payment $payment,
        public float $amount,
        public bool $isForSender,
        public float $remaining = 0,
        public ?float $nextPaymentAmount = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->isForSender
            ? "Payment receipt – Invoice {$this->invoice->invoice_number} – " . $this->invoice->currency . ' ' . number_format($this->amount, 2)
            : "Payment receipt – Invoice {$this->invoice->invoice_number} – " . $this->invoice->currency . ' ' . number_format($this->amount, 2);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $view = $this->isForSender
            ? 'emails.invoice-payment-receipt-sender'
            : 'emails.invoice-payment-receipt-receiver';

        return new Content(view: $view);
    }
}
