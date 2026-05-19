<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InvoiceDeliveryService
{
    public function __construct(private readonly InvoicePdfService $invoicePdfService)
    {
    }

    public function send(Invoice $invoice, array $data = []): array
    {
        $validated = Validator::make($data, [
            'recipient_email' => 'nullable|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:2000',
        ])->validate();

        $invoice->loadMissing(['quotation.items.product', 'quotation.items.catalogProduct', 'invoiceable', 'payments']);
        $recipientEmail = $validated['recipient_email'] ?? $this->resolveRecipientEmail($invoice);

        if (! $recipientEmail) {
            throw ValidationException::withMessages([
                'recipient_email' => ['No fue posible determinar un correo destino para la factura'],
            ]);
        }

        $pdfContent = $this->invoicePdfService->render($invoice);
        $filename = $this->invoicePdfService->filename($invoice);

        Mail::to($recipientEmail)->send(new InvoicePdfMail(
            invoice: $invoice,
            pdfContent: $pdfContent,
            filename: $filename,
            messageBody: $validated['message'] ?? null,
            subjectLine: $validated['subject'] ?? null,
        ));

        return [
            'invoice' => $invoice,
            'recipient_email' => $recipientEmail,
            'subject' => $validated['subject'] ?? ('Factura '.($invoice->invoice_number ?: $invoice->uid)),
            'filename' => $filename,
        ];
    }

    private function resolveRecipientEmail(Invoice $invoice): ?string
    {
        $invoiceable = $invoice->invoiceable;

        if (! $invoiceable) {
            return null;
        }

        $email = $invoiceable->email ?? null;

        if ($email) {
            return $email;
        }

        $profileData = $invoiceable->profile_data ?? null;

        return is_array($profileData) ? ($profileData['email'] ?? null) : null;
    }
}
