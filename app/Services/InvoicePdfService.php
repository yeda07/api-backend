<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\QuotationItem;
use App\Support\SimplePdf;

class InvoicePdfService
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing([
            'quotation.items.product',
            'quotation.items.catalogProduct',
            'invoiceable',
            'payments',
        ]);

        $client = $invoice->invoiceable;
        $clientName = $client?->display_name ?? $client?->name ?? 'Cliente';
        $lines = [
            'Factura: '.($invoice->invoice_number ?: $invoice->uid),
            'Cliente: '.$clientName,
            'Estado: '.$invoice->status,
            'Fecha emision: '.($invoice->issued_at?->toDateString() ?? 'N/A'),
            'Fecha vencimiento: '.($invoice->due_date?->toDateString() ?? 'N/A'),
            'Moneda: '.$invoice->currency,
            '',
            'Detalle',
        ];

        $items = $invoice->quotation?->items ?? collect();

        if ($items->isEmpty()) {
            $lines[] = 'Sin items asociados.';
        }

        foreach ($items as $index => $item) {
            /** @var QuotationItem $item */
            $lines[] = sprintf(
                '%d. %s | SKU: %s | Cant: %s | Precio: %s | Desc: %s%% | Total: %s',
                $index + 1,
                $item->description,
                $item->sku ?: 'N/A',
                $this->number($item->quantity),
                $this->money($item->list_unit_price, $invoice->currency),
                $this->number($item->discount_percent),
                $this->money($item->line_total, $invoice->currency),
            );
        }

        $lines = array_merge($lines, [
            '',
            'Subtotal: '.$this->money($invoice->subtotal, $invoice->currency),
            'Descuento: '.$this->money($invoice->discount_total, $invoice->currency),
            'Total: '.$this->money($invoice->total, $invoice->currency),
            'Pagado: '.$this->money($invoice->paid_total, $invoice->currency),
            'Saldo pendiente: '.$this->money($invoice->outstanding_total, $invoice->currency),
        ]);

        return SimplePdf::document('Factura '.$invoice->invoice_number, $lines);
    }

    public function filename(Invoice $invoice): string
    {
        $invoiceNumber = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) ($invoice->invoice_number ?: $invoice->uid));

        return 'factura-'.trim((string) $invoiceNumber, '-').'.pdf';
    }

    private function money(mixed $amount, string $currency): string
    {
        return number_format((float) $amount, 2, '.', ',').' '.$currency;
    }

    private function number(mixed $amount): string
    {
        return rtrim(rtrim(number_format((float) $amount, 2, '.', ','), '0'), '.');
    }
}
