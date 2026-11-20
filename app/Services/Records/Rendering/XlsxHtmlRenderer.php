<?php

namespace App\Services\Records\Rendering;

use App\Models\RecordFile;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Renders an .xlsx as HTML tables.
 *
 * The workbook is a ZIP of XML with a shape that takes a moment to get used to:
 * cell *values* live in xl/worksheets/sheet1.xml, but the text almost never
 * does. A cell carries a number and an index into a shared string table held
 * separately in xl/sharedStrings.xml, and says which kind of cell it is with a
 * `t` attribute. So reading a sheet means resolving every cell back through that
 * table before there is anything to show.
 *
 * What this deliberately does not do is reproduce the workbook: no colours, no
 * fonts, no column widths, and no formula recalculation — only the value Excel
 * last cached. A reader wants to see what the sheet says, not how it was styled.
 */
class XlsxHtmlRenderer extends OfficeHtmlRenderer
{
    /**
     * Caps, because a sheet with a million rows will take the browser down with
     * it. They are generous enough for any register or ledger a person reads.
     */
    private const MAX_ROWS = 300;

    private const MAX_COLUMNS = 40;

    private const MAX_SHEETS = 4;

    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public function supports(RecordFile $file): bool
    {
        return strtolower((string) $file->extension) === 'xlsx';
    }

    protected function extension(): string
    {
        return 'xlsx';
    }

    public function body(RecordFile $file): ?string
    {
        $workbook = $this->dom($this->part($file, 'xl/workbook.xml'));

        if (! $workbook instanceof DOMDocument) {
            return null;
        }

        $shared = $this->sharedStrings($file);
        $sheets = $this->sheetNames($workbook);

        $out = '';
        $rendered = 0;
        $truncated = false;

        foreach ($sheets as $index => $name) {
            if ($rendered >= self::MAX_SHEETS) {
                $truncated = true;

                break;
            }

            // Sheet part names are positional in every writer worth supporting,
            // but read from the relationships when they are there.
            $sheet = $this->dom($this->part($file, sprintf('xl/worksheets/sheet%d.xml', $index + 1)));

            if (! $sheet instanceof DOMDocument) {
                continue;
            }

            $table = $this->table($sheet, $shared);

            if ($table === '') {
                continue;
            }

            $rendered++;
            $out .= '<p class="sheet-title">'.$this->html($name).'</p>'.$table;

            if ($this->rowsOmitted($sheet) > 0) {
                $truncated = true;
            }
        }

        if ($out === '') {
            return '<p class="empty">This workbook has no readable sheets.</p>';
        }

        if ($truncated) {
            $out .= $this->truncatedNotice(self::MAX_ROWS.' rows and '.self::MAX_SHEETS.' sheets');
        }

        return $out;
    }

    /**
     * The workbook's shared string table, which is where nearly all the text is.
     *
     * @return list<string>
     */
    private function sharedStrings(RecordFile $file): array
    {
        $document = $this->dom($this->part($file, 'xl/sharedStrings.xml'));

        if (! $document instanceof DOMDocument) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', self::NS_MAIN);

        $strings = [];

        foreach ($xpath->query('//x:si') ?: [] as $item) {
            // A shared string is a <si> holding one or more <t>, and the runs
            // inside a rich-text string can each carry their own <t>.
            $strings[] = $this->text(implode('', iterator_to_array(
                $this->texts($xpath, $item)
            )));
        }

        return $strings;
    }

    /**
     * The text nodes inside a shared string or inline string, flattened.
     *
     * Yields the text, not the element: a rich-text string is a run of them and
     * they have to be joined before they are worth anything.
     *
     * @return \Generator<int, string>
     */
    private function texts(DOMXPath $xpath, DOMNode $context): \Generator
    {
        foreach ($xpath->query('.//x:t', $context) ?: [] as $node) {
            yield (string) $node->textContent;
        }
    }

    /**
     * Each sheet's display name, keyed by position.
     *
     * @return array<int, string>
     */
    private function sheetNames(DOMDocument $workbook): array
    {
        $xpath = new DOMXPath($workbook);
        $xpath->registerNamespace('x', self::NS_MAIN);

        $names = [];

        foreach ($xpath->query('//x:sheets/x:sheet') ?: [] as $index => $sheet) {
            if (! $sheet instanceof DOMElement) {
                continue;
            }

            $names[(int) $index] = (string) $sheet->getAttribute('name');
        }

        return $names === [] ? [1 => 'Sheet1'] : $names;
    }

    /**
     * One sheet as a table, skipping the empty cells Word insists on padding.
     */
    private function table(DOMDocument $sheet, array $shared): string
    {
        $xpath = new DOMXPath($sheet);
        $xpath->registerNamespace('x', self::NS_MAIN);

        $rows = $xpath->query('//x:sheetData/x:row');

        if (! $rows instanceof \DOMNodeList || $rows->length === 0) {
            return '';
        }

        $out = '<table>';
        $emitted = 0;

        foreach ($rows as $row) {
            if ($emitted >= self::MAX_ROWS) {
                break;
            }

            $cells = '';

            foreach ($this->rowCells($xpath, $row) as $cell) {
                $value = $this->cellValue($xpath, $cell, $shared);

                if ($value === '') {
                    continue;
                }

                $cells .= '<td>'.$this->html($value).'</td>';
                $emitted++;
            }

            if ($cells !== '') {
                $out .= '<tr>'.$cells.'</tr>';
            }
        }

        return $out.'</table>';
    }

    /**
     * How many rows were left out, so the notice can be honest about it.
     */
    private function rowsOmitted(DOMDocument $sheet): int
    {
        $xpath = new DOMXPath($sheet);
        $xpath->registerNamespace('x', self::NS_MAIN);

        return max(0, $xpath->query('//x:sheetData/x:row')->length - self::MAX_ROWS);
    }

    /**
     * The cells of a row, in document order, up to the column cap.
     *
     * Empty cells are not materialised. Excel omits them from the XML, so
     * counting them forward to find a column reference would need a pad table
     * that buys nothing: a table renders correctly without the gaps.
     *
     * @return \Generator<int, DOMElement>
     */
    private function rowCells(DOMXPath $xpath, DOMNode $row): \Generator
    {
        $emitted = 0;

        foreach ($xpath->query('./x:c', $row) ?: [] as $cell) {
            if ($emitted >= self::MAX_COLUMNS) {
                break;
            }

            if (! $cell instanceof DOMElement) {
                continue;
            }

            $emitted++;

            yield $cell;
        }
    }

    /**
     * One cell's displayed value.
     *
     * The `t` attribute is the whole story. Absent or "n" is a number, "s" is an
     * index into the shared table, "str" is a formula's cached result, "inlineStr"
     * carries its own text, and "b" is a boolean.
     */
    private function cellValue(DOMXPath $xpath, DOMNode $cell, array $shared): string
    {
        $type = (string) $cell->getAttribute('t');

        $value = $xpath->query('./x:v', $cell);
        $raw = $value instanceof \DOMNodeList && $value->length > 0
            ? (string) $value->item(0)->nodeValue
            : '';

        return match ($type) {
            's' => (string) ($shared[(int) $raw] ?? ''),
            'inlineStr' => $this->text(implode('', iterator_to_array($this->texts($xpath, $cell)))),
            'b' => $raw === '1' ? 'TRUE' : 'FALSE',
            default => $this->text($raw),
        };
    }
}
