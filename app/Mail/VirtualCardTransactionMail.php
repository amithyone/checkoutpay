<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VirtualCardTransactionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $brandName,
        public string $headline,
        public string $summaryLine,
        public string $whenLine,
        public ?string $statusLine,
        public ?string $referenceLine,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->brandName} card: {$this->headline}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.virtual-card-transaction',
        );
    }
}
