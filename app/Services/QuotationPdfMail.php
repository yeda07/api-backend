<?php

namespace App\Services;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationPdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public string $pdfContent,
        public string $filename,
        public ?string $messageBody = null,
        public ?string $subjectLine = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine ?: 'Cotizacion ' . ($this->quotation->quote_number ?: $this->quotation->uid),
        );
    }

    public function build(): static
    {
        $body = $this->messageBody
            ?: 'Adjuntamos la cotizacion ' . ($this->quotation->quote_number ?: $this->quotation->uid) . ' para tu revision.';

        return $this->html(
            '<p>Hola,</p>'
            . '<p>' . e($body) . '</p>'
            . '<p>Titulo: ' . e((string) $this->quotation->title) . '</p>'
            . '<p>Total: ' . e(number_format((float) $this->quotation->total, 2, '.', ',')) . ' ' . e((string) $this->quotation->currency) . '</p>'
            . '<p>Gracias.</p>'
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->filename)
                ->withMime('application/pdf'),
        ];
    }
}
