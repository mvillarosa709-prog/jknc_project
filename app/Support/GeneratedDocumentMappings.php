<?php

namespace App\Support;

/**
 * The planned module → Record mapping shown on the Integration tab.
 *
 * The reference mock's six pairings, verbatim, because it is a statement of what
 * each module intends to register once it reaches the Records engine. What it is
 * not is a list of working integrations: no module in this codebase reaches the
 * engine yet, and Corporate Governance is explicitly a later phase.
 *
 * It lives here rather than on the setup controller because three things need it
 * — the tab that displays it, the request that validates a simulation, and the
 * action that files one — and a controller constant is not somewhere a request
 * should be reaching into.
 */
final class GeneratedDocumentMappings
{
    /**
     * @return list<array{module: string, document: string, trigger: string, classification: string, index: string}>
     */
    public static function rows(): array
    {
        return [
            ['module' => 'Entity & Governance', 'document' => 'Board Resolution', 'trigger' => 'Finalized / Approved', 'classification' => '301', 'index' => '301-13'],
            ['module' => 'Entity & Governance', 'document' => "Secretary's Certificate", 'trigger' => 'Finalized / Issued', 'classification' => '301', 'index' => '301-15'],
            ['module' => 'Finance', 'document' => 'Financial Statements', 'trigger' => 'Finalized', 'classification' => '701', 'index' => '701-5'],
            ['module' => 'Human Capital', 'document' => 'Employment Memo / Notice', 'trigger' => 'Issued', 'classification' => '201', 'index' => '201-2'],
            ['module' => 'Transmittal', 'document' => 'Transmittal Output', 'trigger' => 'Accepted / Finalized', 'classification' => '1001', 'index' => '1001-27'],
            ['module' => 'Reports', 'document' => 'Generated Report', 'trigger' => 'Generated / Saved', 'classification' => '1001', 'index' => '1001-27'],
        ];
    }

    /**
     * One pairing by its zero-based position on the tab, or null if out of range.
     *
     * @return array{module: string, document: string, trigger: string, classification: string, index: string}|null
     */
    public static function resolve(int $position): ?array
    {
        return self::rows()[$position] ?? null;
    }

    public static function count(): int
    {
        return count(self::rows());
    }

    /**
     * A filename a module would plausibly produce for this document.
     *
     * Derived from the pairing rather than invented, so the sample in the queue
     * reads as the document the mapping row claims it is.
     */
    public static function sampleFilename(array $row): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $row['document']));
        $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');

        return sprintf('%s-generated.pdf', $slug !== '' ? $slug : 'generated-document');
    }
}
