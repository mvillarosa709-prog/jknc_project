<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class ActivitiesXlsxExportService
{
    public function build(array $dataset): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The ZIP extension is required for XLSX exports.');
        }

        $path = tempnam(sys_get_temp_dir(), 'activities-xlsx-');

        if ($path === false) {
            throw new RuntimeException('Unable to create the XLSX export file.');
        }

        $archive = new ZipArchive();

        try {
            if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open the XLSX export file.');
            }

            $archive->addFromString('[Content_Types].xml', $this->contentTypes());
            $archive->addFromString('_rels/.rels', $this->rootRelationships());
            $archive->addFromString('xl/workbook.xml', $this->workbook());
            $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
            $archive->addFromString('xl/styles.xml', $this->styles());
            $archive->addFromString('xl/worksheets/sheet1.xml', $this->worksheet($dataset));
            $archive->close();

            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException('Unable to read the XLSX export file.');
            }

            return $contents;
        } finally {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    private function worksheet(array $dataset): string
    {
        $coverage = $dataset['duration_coverage'];
        $period = $dataset['report_period'];
        $rows = [
            ['Activities Report'],
            ['Selection Mode', $dataset['selection_mode']],
            ['Total Activities', $coverage['total_activities']],
            ['Activities with Duration', $coverage['activities_with_duration']],
            ['Total Duration Minutes', $coverage['total_duration_minutes']],
            ['Total Duration Hours', $coverage['total_duration_hours']],
            ['Report From', $period['from']],
            ['Report To', $period['to']],
            [],
            [
                'Activity ID', 'Type', 'Title', 'Date/Time', 'Duration Minutes', 'Duration Hours', 'Status',
                'Prepared By', 'Assigned To', 'Company', 'Contact', 'Project', 'Deal',
                'Client Report Description', 'Has Documentation',
            ],
        ];

        if ($dataset['finance_authorized'] ?? false) {
            array_push($rows[9], 'O.B. Number', 'Trip Ticket Number', 'Travel Credit Units', 'Travel Credit Value', 'Additional Cost Total', 'Billability');
        }

        foreach ($dataset['rows'] as $row) {
            $cells = [
                $row['id'],
                $row['source_activity_type'],
                $row['title'],
                $row['occurred_at'],
                $row['duration_minutes'],
                $row['duration_hours'],
                $row['status'],
                $row['prepared_by'],
                $row['assigned_to'],
                $row['company'],
                $row['contact'],
                $row['project'],
                $row['deal'],
                $row['report_description'],
                $row['has_documentation'] ? 'Yes' : 'No',
            ];
            if ($dataset['finance_authorized'] ?? false) {
                array_push($cells,
                    $row['ob_reference'] ?? null, $row['ob_ticket_reference'] ?? null,
                    $row['ob_travel_credit_units'] ?? null, $row['ob_travel_credit_value'] ?? null,
                    $row['ob_additional_cost_total'] ?? null, $row['ob_billability'] ?? null
                );
            }
            $rows[] = $cells;
        }

        $xmlRows = collect($rows)->map(function (array $cells, int $rowIndex): string {
            $number = $rowIndex + 1;
            $xmlCells = collect($cells)->map(
                fn (mixed $value, int $columnIndex): string => $this->cell($columnIndex + 1, $number, $value)
            )->implode('');

            return '<row r="'.$number.'">'.$xmlCells.'</row>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols><col min="1" max="15" width="22" customWidth="1"/></cols>'
            .'<sheetData>'.$xmlRows.'</sheetData>'
            .'</worksheet>';
    }

    private function cell(int $column, int $row, mixed $value): string
    {
        $reference = $this->columnName($column).$row;

        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'"><v>'.$value.'</v></c>';
        }

        $safe = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$safe.'</t></is></c>';
    }

    private function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Activities" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
