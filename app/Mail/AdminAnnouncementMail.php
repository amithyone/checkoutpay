<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminAnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $announcementTitle,
        public string $announcementBody,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->announcementTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-announcement',
        );
    }
}
