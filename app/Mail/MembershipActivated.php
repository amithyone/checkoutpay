<?php

namespace App\Mail;

use App\Models\MembershipSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class MembershipActivated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public MembershipSubscription $subscription
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Membership Has Been Activated!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.membership-activated',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];
        
        // Attach membership card PDF if it exists
        if ($this->subscription->card_pdf_path) {
            $path = storage_path('app/public/' . $this->subscription->card_pdf_path);
            if (file_exists($path)) {
                $attachments[] = Attachment::fromPath($path)
                    ->as("Membership-Card-{$this->subscription->subscription_number}.pdf")
                    ->withMime('application/pdf');
            }
        }
        
        return $attachments;
    }
}
