<?php

namespace App\Mail;

use App\Models\NigtaxCertifiedOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class NigtaxCertifiedReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NigtaxCertifiedOrder $order
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'NigTax — Your certified tax report is ready',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildBody(),
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $path = $this->order->signed_pdf_path;
        if (!$path) {
            return [];
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return [];
        }

        return [
            Attachment::fromPath($disk->path($path))
                ->as('certified-tax-report.pdf')
                ->withMime('application/pdf'),
        ];
    }

    protected function buildBody(): string
    {
        $name = htmlspecialchars($this->order->customer_name ?: 'there', ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars($this->order->report_type, ENT_QUOTES, 'UTF-8');

        return '<p>Hi '.$name.',</p>'
            .'<p>Your <strong>certified tax report</strong> ('.$type.') is attached to this email.</p>'
            .'<p>Thank you for using NigTax.</p>';
    }
}
