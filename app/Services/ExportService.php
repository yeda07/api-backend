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
                SimplePdf::tableReport($title, $rows->all(), [
                    'filters' => $this->activeFilters((array) ($options['filters'] ?? [])),
                    'total_rows' => $rows->count(),
                ]),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$this->filename($title, 'pdf').'"',
                ]
            );
        }

        $extension = $format === 'excel' ? 'xlsx' : 'csv';
        $contentType = $format === 'excel'
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv; charset=UTF-8';

        $content = $format === 'excel'
            ? $this->xlsx($rows)
            : $this->csv($rows);

        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$this->filename($title, $extension).'"',
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

    private function xlsx(Collection $rows): string
    {
        $headers = $rows->isNotEmpty() ? array_keys($rows->first()) : ['message'];
        $dataRows = $rows->isEmpty() ? collect([['message' => 'Sin datos']]) : $rows;

        return $this->zip([
            '[Content_Types].xml' => $this->xlsxContentTypes(),
            '_rels/.rels' => $this->xlsxRootRels(),
            'docProps/app.xml' => $this->xlsxAppProps(),
            'docProps/core.xml' => $this->xlsxCoreProps(),
            'xl/workbook.xml' => $this->xlsxWorkbook(),
            'xl/_rels/workbook.xml.rels' => $this->xlsxWorkbookRels(),
            'xl/styles.xml' => $this->xlsxStyles(),
            'xl/worksheets/sheet1.xml' => $this->xlsxWorksheet($headers, $dataRows),
        ]);
    }

    private function xlsxWorksheet(array $headers, Collection $rows): string
    {
        $sheetRows = [
            $this->xlsxRow(1, $headers),
        ];

        foreach ($rows->values() as $index => $row) {
            $sheetRows[] = $this->xlsxRow($index + 2, array_map(fn ($header) => $row[$header] ?? null, $headers));
        }

        $lastColumn = $this->xlsxColumnName(max(1, count($headers)));
        $lastRow = max(1, count($sheetRows));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<dimension ref="A1:'.$lastColumn.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<sheetData>'.implode('', $sheetRows).'</sheetData>'
            .'<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            .'</worksheet>';
    }

    private function xlsxRow(int $rowNumber, array $values): string
    {
        $cells = [];

        foreach (array_values($values) as $index => $value) {
            $cells[] = $this->xlsxCell($this->xlsxColumnName($index + 1).$rowNumber, $value);
        }

        return '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>';
    }

    private function xlsxCell(string $reference, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '<c r="'.$reference.'" t="inlineStr"><is><t></t></is></c>';
        }

        if (is_bool($value)) {
            return '<c r="'.$reference.'" t="b"><v>'.($value ? '1' : '0').'</v></c>';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'"><v>'.$value.'</v></c>';
        }

        return '<c r="'.$reference.'" t="inlineStr"><is><t>'.$this->xml((string) $value).'</t></is></c>';
    }

    private function xlsxColumnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function xlsxRootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function xlsxWorkbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function xlsxAppProps(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
            .'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>api-backend</Application></Properties>';
    }

    private function xlsxCoreProps(): string
    {
        $created = now()->toIso8601String();

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            .'xmlns:dc="http://purl.org/dc/elements/1.1/" '
            .'xmlns:dcterms="http://purl.org/dc/terms/" '
            .'xmlns:dcmitype="http://purl.org/dc/dcmitype/" '
            .'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>api-backend</dc:creator>'
            .'<cp:lastModifiedBy>api-backend</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$created.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function zip(array $files): string
    {
        $data = '';
        $centralDirectory = '';
        $fileCount = 0;

        foreach ($files as $name => $content) {
            $name = str_replace('\\', '/', (string) $name);
            $content = (string) $content;
            $offset = strlen($data);
            $crc = (int) sprintf('%u', crc32($content));
            $size = strlen($content);
            [$time, $date] = $this->zipDosDateTime();

            $data .= pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, $time, $date, $crc, $size, $size, strlen($name), 0)
                .$name
                .$content;

            $centralDirectory .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, 20, 20, 0, 0, $time, $date, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset)
                .$name;

            $fileCount++;
        }

        $centralDirectoryOffset = strlen($data);
        $centralDirectorySize = strlen($centralDirectory);

        return $data
            .$centralDirectory
            .pack('VvvvvVVv', 0x06054B50, 0, 0, $fileCount, $fileCount, $centralDirectorySize, $centralDirectoryOffset, 0);
    }

    private function zipDosDateTime(): array
    {
        $now = getdate();
        $time = (($now['hours'] & 0x1F) << 11) | (($now['minutes'] & 0x3F) << 5) | ((int) ($now['seconds'] / 2) & 0x1F);
        $date = ((($now['year'] - 1980) & 0x7F) << 9) | (($now['mon'] & 0x0F) << 5) | ($now['mday'] & 0x1F);

        return [$time, $date];
    }

    private function pdfLines(Collection $rows, array $options): array
    {
        $lines = [
            'Generado: '.now()->toDateTimeString(),
            'Filtros: '.json_encode($this->activeFilters((array) ($options['filters'] ?? [])), JSON_UNESCAPED_UNICODE),
            'Filas: '.$rows->count(),
            '',
        ];

        foreach ($rows->take(35) as $row) {
            $lines[] = collect($row)
                ->map(fn ($value, $key) => $key.': '.$this->scalar($value))
                ->implode(' | ');
        }

        return $lines;
    }

    private function activeFilters(array $filters): array
    {
        return collect($filters)
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
            ->all();
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

        return ($slug ?: 'export').'-'.now()->format('Ymd-His').'.'.$extension;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XML1, 'UTF-8');
    }
}
