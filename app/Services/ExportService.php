<?php

namespace App\Services;

use App\Support\SimplePdf;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class ExportService
{
    public function file(string $title, array|Collection $rows, array $options = []): Response
    {
        $format = strtolower((string) ($options['format'] ?? 'csv'));
        $rows = collect($rows)->map(fn ($row) => $this->normalizeRow($row));
        $fields = $this->validFields($rows, (array) ($options['fields'] ?? []));

        if ($fields !== []) {
            $rows = $rows->map(fn (array $row) => Arr::only($row, $fields));
        }

        if ($format === 'pdf') {
            return response(
                SimplePdf::document($title, $this->pdfLines($rows, $options)),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $this->filename($title, 'pdf') . '"',
                ]
            );
        }

        $extension = $format === 'excel' ? 'xlsx' : 'csv';
        $contentType = $format === 'excel'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        return response($this->csv($rows), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $this->filename($title, $extension) . '"',
        ]);
    }

    private function validFields(Collection $rows, array $requested): array
    {
        if ($requested === [] || $rows->isEmpty()) {
            return [];
        }

        $available = array_keys($rows->first());

        return collect($requested)
            ->filter(fn ($field) => in_array($field, $available, true))
            ->values()
            ->all();
    }

    private function csv(Collection $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        $headers = $rows->isNotEmpty() ? array_keys($rows->first()) : ['message'];
        fputcsv($stream, $headers);

        if ($rows->isEmpty()) {
            fputcsv($stream, ['Sin datos']);
        }

        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($header) => $row[$header] ?? null, $headers));
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv ?: '';
    }

    private function pdfLines(Collection $rows, array $options): array
    {
        $lines = [
            'Generado: ' . now()->toDateTimeString(),
            'Filtros: ' . json_encode($options['filters'] ?? [], JSON_UNESCAPED_UNICODE),
            'Filas: ' . $rows->count(),
            '',
        ];

        foreach ($rows->take(35) as $row) {
            $lines[] = collect($row)
                ->map(fn ($value, $key) => $key . ': ' . $this->scalar($value))
                ->implode(' | ');
        }

        return $lines;
    }

    private function normalizeRow(mixed $row): array
    {
        if (is_array($row)) {
            return collect($row)->map(fn ($value) => $this->scalar($value))->all();
        }

        if (is_object($row) && method_exists($row, 'toArray')) {
            return $this->normalizeRow($row->toArray());
        }

        return ['value' => $this->scalar($row)];
    }

    private function scalar(mixed $value): string|int|float|null|bool
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private function filename(string $title, string $extension): string
    {
        $slug = str((string) $title)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-');

        return ($slug ?: 'export') . '-' . now()->format('Ymd-His') . '.' . $extension;
    }
}
