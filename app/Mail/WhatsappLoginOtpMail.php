<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WhatsappLoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $ttlMinutes,
        public string $recipientName,
        public ?string $magicLinkUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your CheckoutNow WhatsApp login code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.whatsapp-login-otp',
        );
    }
}
