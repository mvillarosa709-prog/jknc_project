<?php

namespace App\Services\Records;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Pulls plain text out of a stored document.
 *
 * One entry point for every format the allowlist admits, because the reading job
 * has to decide what to do with a file long before it knows what the file is. The
 * formats split three ways:
 *
 *   - PDFs with embedded text are parsed first. Scanned PDFs fall back to
 *     Tesseract after their pages are rasterized by Poppler; supported images go
 *     directly to Tesseract.
 *   - Plain text and CSV are already text.
 *   - Office archives are ZIP containers, so their bytes are never the document's
 *     text and this deliberately refuses them rather than returning the archive's
 *     internal strings, which would be noise dressed up as content.
 *
 * Nothing here throws for an unreadable file. A corrupt PDF, a missing file or a
 * parser fault all become a null with a reason, because AGENTS.md requires that a
 * failed read never costs us the stored file and never blocks filing.
 */
class DocumentTextExtractor
{
    /** Bytes read from a text file. Above this it is a mislabelled binary. */
    private const MAX_TEXT_BYTES = 20971520;

    public function __construct(
        private readonly Parser $parser,
        private readonly TesseractOcr $ocr,
    ) {}

    /**
     * @return array{text: string, reason: string|null} The text, or the reason
     *                                                  there is none.
     */
    public function extract(string $absolutePath, string $mimeType, string $extension): array
    {
        $extension = strtolower(trim($extension));

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return $this->none('The stored file could not be read.');
        }

        return match (true) {
            $this->isText($mimeType, $extension) => $this->fromTextFile($absolutePath),
            $extension === 'pdf' => $this->fromPdfOrOcr($absolutePath, $mimeType, $extension),
            $this->isImage($mimeType, $extension) => $this->fromOcr($absolutePath, $mimeType, $extension),
            default => $this->none('This format carries no readable text layer.'),
        };
    }

    /**
     * @return array{text: string, reason: string|null}
     */
    private function fromPdf(string $absolutePath): array
    {
        try {
            $text = trim($this->parser->parseFile($absolutePath)->getText());
        } catch (Throwable $e) {
            // A corrupt or truncated PDF lands here. It is a Needs Review case, not
            // a job failure: the bytes are still stored and still fileable.
            return $this->none('The PDF could not be parsed: '.$e->getMessage());
        }

        if ($text === '') {
            return $this->none('The PDF has no text layer. It is probably a scan.');
        }

        return ['text' => $text, 'reason' => null];
    }

    /**
     * Preserve embedded PDF text extraction and OCR only scanned PDFs.
     *
     * @return array{text: string, reason: string|null}
     */
    private function fromPdfOrOcr(string $absolutePath, string $mimeType, string $extension): array
    {
        $text = $this->fromPdf($absolutePath);

        if ($text['reason'] === null || ! str_contains($text['reason'], 'no text layer')) {
            return $text;
        }

        return $this->fromOcr($absolutePath, $mimeType, $extension, $text['reason']);
    }

    /**
     * @return array{text: string, reason: string|null}
     */
    private function fromOcr(string $absolutePath, string $mimeType, string $extension, ?string $fallbackReason = null): array
    {
        try {
            $text = trim($this->ocr->extract($absolutePath, $mimeType, $extension));
        } catch (Throwable $exception) {
            $reason = $fallbackReason === null
                ? $exception->getMessage()
                : $fallbackReason.' OCR could not complete: '.$exception->getMessage();

            return $this->none($reason);
        }

        if ($text === '') {
            return $this->none($fallbackReason === null
                ? 'OCR did not detect readable text.'
                : $fallbackReason.' OCR did not detect readable text.');
        }

        return ['text' => $text, 'reason' => null];
    }

    /**
     * @return array{text: string, reason: string|null}
     */
    private function fromTextFile(string $absolutePath): array
    {
        $size = (int) filesize($absolutePath);

        if ($size > self::MAX_TEXT_BYTES) {
            return $this->none('The file is too large to read as text.');
        }

        $contents = @file_get_contents($absolutePath);

        if ($contents === false) {
            return $this->none('The text file could not be read.');
        }

        // A NUL byte in the first kilobyte is the cheapest reliable signal that
        // this is a binary wearing a .txt extension.
        if (str_contains(substr($contents, 0, 1024), "\0")) {
            return $this->none('The file is not text despite its extension.');
        }

        $text = trim($contents);

        return $text === ''
            ? $this->none('The file is empty.')
            : ['text' => $text, 'reason' => null];
    }

    private function isText(string $mimeType, string $extension): bool
    {
        return in_array(strtolower($mimeType), ['text/plain', 'text/csv'], true)
            || in_array($extension, ['txt', 'csv'], true);
    }

    private function isImage(string $mimeType, string $extension): bool
    {
        return str_starts_with(strtolower($mimeType), 'image/')
            && in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff', 'bmp'], true);
    }

    /**
     * @return array{text: string, reason: string}
     */
    private function none(string $reason): array
    {
        return ['text' => '', 'reason' => $reason];
    }
}