<?php

namespace App\Mail;

use App\Models\MembershipSubscription;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Payment receipt after membership funding is approved.
 */
class MembershipPaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public MembershipSubscription $subscription,
        public Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment received — '.$this->subscription->membership->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.membership-payment-receipt',
        );
    }
}
