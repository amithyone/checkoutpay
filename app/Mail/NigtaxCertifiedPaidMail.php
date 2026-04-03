<?php

namespace App\Mail;

use App\Models\NigtaxCertifiedOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NigtaxCertifiedPaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NigtaxCertifiedOrder $order
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NigTax — Payment received, your certified report is being reviewed',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildBody(),
        );
    }

    protected function buildBody(): string
    {
        $name = htmlspecialchars($this->order->customer_name ?: 'there', ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars($this->order->report_type, ENT_QUOTES, 'UTF-8');

        return '<p>Hi '.$name.',</p>'
            .'<p>We have received your payment for a <strong>certified tax report</strong> ('.$type.').</p>'
            .'<p>Your report is being <strong>reviewed and signed</strong> by our certified tax consultant. '
            .'You will receive the final PDF by email when it is ready.</p>'
            .'<p>Thank you for using NigTax.</p>';
    }
}
