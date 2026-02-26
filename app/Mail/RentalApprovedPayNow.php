<?php

namespace App\Mail;

use App\Models\Rental;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RentalApprovedPayNow extends Mailable
{
    use Queueable, SerializesModels;

    public Rental $rental;

    public string $payUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Rental $rental)
    {
        $this->rental = $rental;
        $this->rental->load(['business', 'items']);
        $this->payUrl = route('rentals.pay', ['code' => $rental->payment_link_code]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rental Approved – Please Pay - ' . $this->rental->rental_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rental-approved-pay-now',
        );
    }
}
