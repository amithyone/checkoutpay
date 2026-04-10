<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WhatsappWalletTransferOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $otpTtlMinutes,
        public int $linkTtlMinutes,
        public string $summaryLine,
        public string $securePinUrl,
        public string $brandName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Confirm your {$this->brandName} WhatsApp wallet transfer",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.whatsapp-wallet-transfer-otp',
        );
    }
}
