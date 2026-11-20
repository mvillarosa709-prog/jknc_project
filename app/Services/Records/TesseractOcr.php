<?php

namespace App\Services\Records;

use App\Support\PdfPageCounter;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class TesseractOcr
{
    public function __construct(private readonly PdfPageCounter $pageCounter) {}

    public function extract(string $path, string $mimeType, string $extension): string
    {
        $extension = strtolower($extension);

        if ($extension === 'pdf' || $mimeType === 'application/pdf') {
            return $this->fromPdf($path);
        }

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff', 'bmp'], true)) {
            throw new RuntimeException('This file type is not supported by OCR.');
        }

        return $this->recognize($path);
    }

    private function fromPdf(string $path): string
    {
        $pageCount = $this->pageCounter->count($path);
        $maxPages = max(1, (int) config('ordo.ocr.max_pdf_pages', 100));

        if ($pageCount === null) {
            throw new RuntimeException('The PDF page count could not be determined for OCR.');
        }

        if ($pageCount > $maxPages) {
            throw new RuntimeException(sprintf(
                'The PDF has %d pages; OCR is limited to %d pages per file.',
                $pageCount,
                $maxPages,
            ));
        }

        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ordo-ocr-'.bin2hex(random_bytes(12));

        if (! mkdir($directory, 0700)) {
            throw new RuntimeException('A temporary OCR workspace could not be created.');
        }

        $prefix = $directory.DIRECTORY_SEPARATOR.'page';
        $pattern = $prefix.'-*.png';

        try {
            $this->run([
                (string) config('ordo.ocr.pdftoppm_binary', 'pdftoppm'),
                '-f', '1',
                '-l', (string) $pageCount,
                '-r', (string) max(72, (int) config('ordo.ocr.pdf_dpi', 250)),
                '-png',
                $path,
                $prefix,
            ]);

            $pages = glob($pattern);

            if ($pages === false || $pages === []) {
                throw new RuntimeException('The PDF pages could not be rendered for OCR.');
            }

            natsort($pages);
            $text = [];

            foreach ($pages as $page) {
                $text[] = $this->recognize($page);
            }

            return trim(implode("\n\n", array_filter($text, static fn (string $page): bool => $page !== '')));
        } finally {
            $pages = glob($pattern);

            if ($pages !== false) {
                foreach ($pages as $page) {
                    if (is_file($page) && ! unlink($page)) {
                        throw new RuntimeException('A temporary OCR page could not be removed.');
                    }
                }
            }

            if (is_dir($directory) && ! rmdir($directory)) {
                throw new RuntimeException('The temporary OCR workspace could not be removed.');
            }
        }
    }

    private function recognize(string $path): string
    {
        return $this->run([
            (string) config('ordo.ocr.tesseract_binary', 'tesseract'),
            $path,
            'stdout',
            '-l',
            (string) config('ordo.ocr.languages', 'eng'),
            '--psm',
            '3',
        ]);
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): string
    {
        $process = new Process($command);
        $process->setTimeout(max(1, (int) config('ordo.ocr.timeout_seconds', 180)));

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new RuntimeException('OCR could not start: '.$exception->getMessage(), previous: $exception);
        }

        if (! $process->isSuccessful()) {
            $reason = trim($process->getErrorOutput());

            throw new RuntimeException($reason !== ''
                ? 'OCR failed: '.$reason
                : 'OCR failed with exit code '.$process->getExitCode().'.');
        }

        return trim($process->getOutput());
    }
}
