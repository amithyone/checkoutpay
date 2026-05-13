<?php

namespace App\Mail;

use App\Models\DeveloperProgramApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeveloperProgramApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DeveloperProgramApplication $application
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Developer Program application — '.$this->application->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.developer-program-application',
        );
    }
}
