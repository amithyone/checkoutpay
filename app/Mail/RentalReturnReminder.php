<?php

namespace App\Mail;

use App\Models\Rental;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RentalReturnReminder extends Mailable
{
    use Queueable, SerializesModels;

    public Rental $rental;

    /** @var string e.g. '5h_before', '1h_after' */
    public string $reminderType;

    public function __construct(Rental $rental, string $reminderType)
    {
        $this->rental = $rental;
        $this->reminderType = $reminderType;
    }

    public function envelope(): Envelope
    {
        $subject = $this->rental->isOverdue()
            ? 'Overdue: Please return rental ' . $this->rental->rental_number
            : 'Reminder: Return rental ' . $this->rental->rental_number . ' soon';
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.rental-return-reminder');
    }
}
