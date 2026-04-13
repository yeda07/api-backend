<?php

namespace App\Services;

use App\Models\Quotation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class QuotationDeliveryService
{
    public function __construct(private readonly QuotationPdfService $quotationPdfService)
    {
    }

    public function send(Quotation $quotation, array $data = []): array
    {
        $validated = Validator::make($data, [
            'recipient_email' => 'nullable|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:2000',
        ])->validate();

        $quotation->loadMissing(['items.product', 'items.warehouse', 'quoteable', 'priceBook']);
        $recipientEmail = $validated['recipient_email'] ?? $this->resolveRecipientEmail($quotation);

        if (!$recipientEmail) {
            throw ValidationException::withMessages([
                'recipient_email' => ['No fue posible determinar un correo destino para la cotizacion'],
            ]);
        }

        $pdfContent = $this->quotationPdfService->render($quotation);
        $filename = $this->quotationPdfService->filename($quotation);

        Mail::to($recipientEmail)->send(new QuotationPdfMail(
            quotation: $quotation,
            pdfContent: $pdfContent,
            filename: $filename,
            messageBody: $validated['message'] ?? null,
            subjectLine: $validated['subject'] ?? null,
        ));

        if ($quotation->status === 'draft') {
            $quotation->update(['status' => 'sent']);
            $quotation->refresh();
        }

        return [
            'quotation' => $quotation,
            'recipient_email' => $recipientEmail,
            'subject' => $validated['subject'] ?? ('Cotizacion ' . ($quotation->quote_number ?: $quotation->uid)),
            'filename' => $filename,
        ];
    }

    private function resolveRecipientEmail(Quotation $quotation): ?string
    {
        $quoteable = $quotation->quoteable;

        if (!$quoteable) {
            return null;
        }

        $email = $quoteable->email ?? null;

        if ($email) {
            return $email;
        }

        $profileData = $quoteable->profile_data ?? null;

        return is_array($profileData) ? ($profileData['email'] ?? null) : null;
    }
}
