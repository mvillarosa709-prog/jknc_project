<?php

namespace App\Support;

class PdfPageCounter
{
    /**
     * Counts PDF pages by reading the page tree /Count entry.
     *
     * Returns null when the page count cannot be determined. Malformed,
     * encrypted or non-PDF input never throws, so intake is never blocked
     * by an unreadable page count.
     */
    public function count(string $absolutePath): ?int
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $contents = (string) file_get_contents($absolutePath);

        if (! str_starts_with($contents, '%PDF-')) {
            return null;
        }

        if (preg_match_all('#/Type\s*/Pages\b[^>]*?/Count\s+(\d+)#s', $contents, $matches) > 0) {
            return max(array_map('intval', $matches[1]));
        }

        if (preg_match('#/Type\s*/Pages\b[^>]*?/Kids\s*\[\s*(\d+)\s+#s', $contents, $kids) === 1) {
            return (int) $kids[1];
        }

        if (preg_match_all('#/Type\s*/Page\b(?!s)#', $contents, $pageMatches) > 0) {
            return count($pageMatches[0]);
        }

        return null;
    }
}
