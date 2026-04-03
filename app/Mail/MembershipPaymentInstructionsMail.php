<?php

namespace App\Mail;

use App\Models\Membership;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when a membership payment is started (virtual account generated) — NigTax PRO modal or public checkout.
 */
class MembershipPaymentInstructionsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{member_name: string, member_email: string, member_phone: string}  $member
     */
    public function __construct(
        public Payment $payment,
        public Membership $membership,
        public array $member
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Complete your payment: '.$this->membership->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.membership-payment-instructions',
        );
    }
}
