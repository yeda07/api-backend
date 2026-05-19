<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePdfMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $pdfContent,
        public string $filename,
        public ?string $messageBody = null,
        public ?string $subjectLine = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine ?: 'Factura '.($this->invoice->invoice_number ?: $this->invoice->uid),
        );
    }

    public function build(): static
    {
        $body = $this->messageBody
            ?: 'Adjuntamos la factura '.($this->invoice->invoice_number ?: $this->invoice->uid).' para tu revision.';

        return $this->html(
            '<p>Hola,</p>'
            .'<p>'.e($body).'</p>'
            .'<p>Factura: '.e((string) $this->invoice->invoice_number).'</p>'
            .'<p>Total: '.e(number_format((float) $this->invoice->total, 2, '.', ',')).' '.e((string) $this->invoice->currency).'</p>'
            .'<p>Saldo pendiente: '.e(number_format((float) $this->invoice->outstanding_total, 2, '.', ',')).' '.e((string) $this->invoice->currency).'</p>'
            .'<p>Gracias.</p>'
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
