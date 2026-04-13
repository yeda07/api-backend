<?php

namespace App\Services;

use App\Models\Quotation;

class QuotationPdfService
{
    public function render(Quotation $quotation): string
    {
        $quotation->loadMissing(['items.product', 'items.warehouse', 'quoteable', 'priceBook']);

        $lines = $this->buildLines($quotation);
        $content = $this->buildContentStream($lines);

        return $this->buildPdfDocument($content);
    }

    public function filename(Quotation $quotation): string
    {
        $quoteNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '-', $quotation->quote_number ?: $quotation->uid);

        return 'cotizacion-' . trim((string) $quoteNumber, '-') . '.pdf';
    }

    private function buildLines(Quotation $quotation): array
    {
        $customerName = $quotation->quoteable?->display_name
            ?? $quotation->quoteable?->name
            ?? 'Cliente';

        $lines = [
            'Cotizacion ' . ($quotation->quote_number ?: $quotation->uid),
            'Titulo: ' . ($quotation->title ?: 'Sin titulo'),
            'Cliente: ' . $customerName,
            'Estado: ' . strtoupper((string) $quotation->status),
            'Moneda: ' . ($quotation->currency ?: 'N/A'),
            'Valida hasta: ' . ($quotation->valid_until?->toDateString() ?: 'N/A'),
            'Lista de precios: ' . ($quotation->priceBook?->name ?: 'N/A'),
            '',
            'Items',
        ];

        foreach ($quotation->items as $index => $item) {
            $lines[] = sprintf(
                '%d. %s | Cant: %s | Unit: %s | Desc: %s | Total: %s',
                $index + 1,
                $item->description ?: ($item->product?->name ?: 'Item'),
                $this->formatAmount((float) $item->quantity, false),
                $this->formatAmount((float) $item->net_unit_price),
                $this->formatAmount((float) $item->discount_amount),
                $this->formatAmount((float) $item->line_total)
            );
        }

        $lines[] = '';
        $lines[] = 'Subtotal: ' . $this->formatAmount((float) $quotation->subtotal);
        $lines[] = 'Descuento: ' . $this->formatAmount((float) $quotation->discount_total);
        $lines[] = 'Total: ' . $this->formatAmount((float) $quotation->total);

        if ($quotation->notes) {
            $lines[] = '';
            $lines[] = 'Notas: ' . $quotation->notes;
        }

        return $lines;
    }

    private function buildContentStream(array $lines): string
    {
        $stream = "BT\n/F1 12 Tf\n14 TL\n50 790 Td\n";

        foreach ($this->wrapLines($lines) as $index => $line) {
            $escaped = $this->escapePdfText($line);
            $stream .= ($index === 0 ? '' : "T*\n") . '(' . $escaped . ") Tj\n";
        }

        return $stream . "ET";
    }

    private function buildPdfDocument(string $content): string
    {
        $objects = [];
        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj";
        $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj";
        $objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset) . "\n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function wrapLines(array $lines): array
    {
        $wrapped = [];

        foreach ($lines as $line) {
            $cleanLine = preg_replace('/\s+/', ' ', trim((string) $line));

            if ($cleanLine === '') {
                $wrapped[] = ' ';
                continue;
            }

            $segments = wordwrap($cleanLine, 90, "\n", true);

            foreach (explode("\n", $segments) as $segment) {
                $wrapped[] = $segment;
            }
        }

        return array_slice($wrapped, 0, 45);
    }

    private function escapePdfText(string $text): string
    {
        $text = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text) ?: $text;

        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $text
        );
    }

    private function formatAmount(float $value, bool $money = true): string
    {
        $formatted = number_format($value, 2, '.', ',');

        return $money ? '$' . $formatted : $formatted;
    }
}
