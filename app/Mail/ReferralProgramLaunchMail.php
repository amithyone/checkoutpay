<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferralProgramLaunchMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $payCode,
        public string $brandName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Refer friends & earn on '.$this->brandName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.referral-program-launch',
        );
    }
}
