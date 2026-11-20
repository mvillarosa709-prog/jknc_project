<?php

namespace App\Services\Records\Rendering;

use App\Models\RecordFile;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMXPath;

/**
 * Renders a .docx as HTML.
 *
 * A .docx stores its body in word/document.xml as WordprocessingML: a flat run of
 * paragraphs, each holding runs of text, with headings and tables expressed as
 * attributes on those paragraphs rather than as structure. Reading it is a matter
 * of walking that tree and translating the handful of markers that matter —
 * heading style, bold, italic, numbering, tables — into the equivalent HTML.
 *
 * Fidelity is honest about its limits: text, headings, emphasis, lists and tables
 * survive; images, footnotes and headers are dropped. A reader is better served
 * by the document's words in ORDO's own type than by a broken attempt at Word's
 * layout.
 */
class DocxHtmlRenderer extends OfficeHtmlRenderer
{
    /**
     * Paragraphs beyond this are not worth building a page for.
     */
    private const MAX_PARAGRAPHS = 2000;

    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public function supports(RecordFile $file): bool
    {
        return strtolower((string) $file->extension) === 'docx';
    }

    protected function extension(): string
    {
        return 'docx';
    }

    public function body(RecordFile $file): ?string
    {
        $document = $this->dom($this->part($file, 'word/document.xml'));

        if (! $document instanceof DOMDocument) {
            return null;
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::NS);

        $body = $xpath->query('//w:body');
        $root = $body instanceof DOMNodeList ? $body->item(0) : null;

        if (! $root instanceof DOMElement) {
            return null;
        }

        $out = '';
        $paragraphs = 0;
        $inList = false;

        foreach ($root->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->localName === 'p') {
                if (++$paragraphs > self::MAX_PARAGRAPHS) {
                    $out .= $this->truncatedNotice(self::MAX_PARAGRAPHS.' paragraphs');

                    break;
                }

                // The list element is opened and closed around a run of
                // consecutive list paragraphs, rather than inside the paragraph
                // that happens to start one. Opening it from the paragraph cannot
                // know where the list ends, and an <ul> left open runs the rest
                // of the document into a bullet list.
                $listed = $this->isListItem($xpath, $child);

                if ($listed !== $inList) {
                    $out .= $listed ? '<ul>' : '</ul>';
                    $inList = $listed;
                }

                $out .= $this->paragraph($xpath, $child);

                continue;
            }

            if ($inList) {
                $out .= '</ul>';
                $inList = false;
            }

            // Tables are siblings of paragraphs in the body, not children.
            if ($child->localName === 'tbl') {
                $out .= $this->table($xpath, $child);
            }
        }

        if ($inList) {
            $out .= '</ul>';
        }

        return $out === '' ? '<p class="empty">This document has no readable content.</p>' : $out;
    }

    /**
     * One paragraph, with its heading level, emphasis and list marker.
     */
    private function paragraph(DOMXPath $xpath, DOMElement $node): string
    {
        $text = $this->runs($xpath, $node);

        if ($text === '') {
            return '';
        }

        $tag = $this->headingTag($xpath, $node);

        if ($tag !== null) {
            return '<'.$tag.'>'.$text.'</'.$tag.'>';
        }

        return $this->isListItem($xpath, $node)
            ? '<li>'.$text.'</li>'
            : '<p>'.$text.'</p>';
    }

    /**
     * The runs inside a paragraph, with bold and italic carried across.
     *
     * Bold and italic are tracked as open tags rather than as a property of the
     * whole paragraph, because Word applies them per run and a single sentence in
     * a document is routinely half-bold.
     */
    private function runs(DOMXPath $xpath, DOMNode $context): string
    {
        $runs = $xpath->query('.//w:r', $context);

        if (! $runs instanceof DOMNodeList) {
            return '';
        }

        $out = '';
        $bold = false;
        $italic = false;

        foreach ($runs as $run) {
            if (! $run instanceof DOMElement) {
                continue;
            }

            $text = $this->runText($xpath, $run);

            if ($text === '') {
                continue;
            }

            $nowBold = $xpath->query('./w:rPr/w:b', $run)->length > 0;
            $nowItalic = $xpath->query('./w:rPr/w:i', $run)->length > 0;

            if ($nowBold !== $bold) {
                $out .= $nowBold ? '<strong>' : '</strong>';
                $bold = $nowBold;
            }

            if ($nowItalic !== $italic) {
                $out .= $nowItalic ? '<em>' : '</em>';
                $italic = $nowItalic;
            }

            $out .= $this->html($text);
        }

        if ($bold) {
            $out .= '</strong>';
        }

        if ($italic) {
            $out .= '</em>';
        }

        return $out;
    }

    /**
     * A run's text, with its tabs and breaks turned into something visible.
     */
    private function runText(DOMXPath $xpath, DOMNode $run): string
    {
        $out = '';

        foreach ($run->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $out .= match ($node->localName) {
                // The only child that carries literal text. Anything else a run
                // holds is a field code or a drawing, neither of which is content.
                't' => $node->textContent,
                'tab' => "\t",
                'br', 'cr' => "\n",
                default => '',
            };
        }

        return $this->text($out);
    }

    /**
     * The heading level this paragraph declares, if any.
     *
     * Word encodes it as a style name rather than an element, and the names are
     * localised, so only the shape is matched: "Heading3" in any language ends
     * the same way.
     */
    private function headingTag(DOMXPath $xpath, DOMNode $node): ?string
    {
        $styles = $xpath->query('./w:pPr/w:pStyle/@w:val', $node);

        if (! $styles instanceof DOMNodeList || $styles->length === 0) {
            return null;
        }

        $value = (string) $styles->item(0)->nodeValue;

        if (preg_match('/^Heading\s*([1-6])$/i', $value, $matches) === 1) {
            return 'h'.((int) $matches[1] + 1);
        }

        if (preg_match('/^Title$/i', $value) === 1) {
            return 'h1';
        }

        return null;
    }

    /**
     * Whether this paragraph is a bullet or numbered item.
     */
    private function isListItem(DOMXPath $xpath, DOMNode $node): bool
    {
        return $xpath->query('./w:pPr/w:numPr/w:numId', $node)->length > 0;
    }

    /**
     * A table, row by row and cell by cell.
     */
    private function table(DOMXPath $xpath, DOMElement $node): string
    {
        $out = '<table>';
        $rowIndex = 0;

        foreach ($xpath->query('./w:tr', $node) ?: [] as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $first = $rowIndex === 0;
            $rowIndex++;
            $out .= '<tr>';

            foreach ($xpath->query('./w:tc', $row) ?: [] as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }

                // A header row is the first row unless the author marked the cells
                // individually. Word records a real header by shading the cell,
                // but plenty of tables rely on position alone, and a reader needs
                // the labels to read as labels rather than as the first line of
                // data.
                $header = $first || $xpath->query('.//w:tcPr/w:shd/@w:fill', $cell)->length > 0;
                $tag = $header ? 'th' : 'td';

                $text = '';

                foreach ($xpath->query('./w:p', $cell) ?: [] as $cellParagraph) {
                    if (! $cellParagraph instanceof DOMElement) {
                        continue;
                    }

                    $text .= $this->text($this->runs($xpath, $cellParagraph));
                }

                $out .= '<'.$tag.'>'.$this->html($text).'</'.$tag.'>';
            }

            $out .= '</tr>';
        }

        return $out.'</table>';
    }
}
