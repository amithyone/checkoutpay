<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WalletStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $ledgerLabel,
        public string $periodLabel,
        public string $from,
        public string $to,
        public string $format,
        public string $fileName,
        public string $fileContent,
        public string $mimeType,
    ) {}

    public function envelope(): Envelope
    {
        $kind = strtoupper($this->format);

        return new Envelope(
            subject: "Your CheckoutNow {$kind} account statement",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.wallet-statement',
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->fileContent, $this->fileName)
                ->withMime($this->mimeType),
        ];
    }
}
