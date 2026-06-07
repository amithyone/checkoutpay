<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VirtualCardReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $brandName,
        public string $cardName,
        public ?float $balanceUsd,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your {$this->brandName} Dollar Virtual Card is ready",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.virtual-card-ready',
        );
    }
}
