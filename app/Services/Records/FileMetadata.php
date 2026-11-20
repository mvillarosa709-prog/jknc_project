<?php

namespace App\Services\Records;

use App\Support\PdfPageCounter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\MimeTypes;

class FileMetadata
{
    public function __construct(private readonly PdfPageCounter $pageCounter) {}

    /**
     * @return array{sha256: string, size_bytes: int, mime_type: ?string, extension: ?string, page_count: ?int, document_type: string}
     */
    public function extract(UploadedFile $file, string $disk, string $path): array
    {
        $absolutePath = Storage::disk($disk)->path($path);

        clearstatcache(true, $absolutePath);

        $mime = $this->mimeType($absolutePath);

        return [
            'sha256' => (string) hash_file('sha256', $absolutePath),
            'size_bytes' => (int) filesize($absolutePath),
            'mime_type' => $mime,
            'extension' => $this->extension($file),
            'page_count' => $this->pageCount($mime, $file, $absolutePath),
            'document_type' => $this->documentType($file, $mime),
        ];
    }

    /**
     * The stored bytes decide what type a document is.
     *
     * An upload's declared MIME type comes from the client and is therefore
     * attacker-controlled, so it is never used to classify a record: a file
     * renamed to .pdf is stored and displayed as whatever it actually is. When
     * the bytes cannot be identified the record keeps a null type rather than a
     * guessed one, and the interface falls back to the file extension for the
     * document label instead.
     */
    private function mimeType(string $absolutePath): ?string
    {
        if (! is_file($absolutePath)) {
            return null;
        }

        $detected = (new MimeTypes)->guessMimeType($absolutePath);

        return $detected !== '' ? $detected : null;
    }

    private function extension(UploadedFile $file): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return $extension !== '' ? $extension : null;
    }

    private function pageCount(?string $mime, UploadedFile $file, string $absolutePath): ?int
    {
        if ($this->isPdf($mime, $file)) {
            return $this->pageCounter->count($absolutePath);
        }

        if (str_starts_with((string) $mime, 'image/')) {
            return 1;
        }

        return null;
    }

    private function isPdf(?string $mime, UploadedFile $file): bool
    {
        return $mime === 'application/pdf'
            || strtolower($file->getClientOriginalExtension()) === 'pdf';
    }

    private function documentType(UploadedFile $file, ?string $mime): string
    {
        $name = strtolower($file->getClientOriginalName());
        $mime = strtolower((string) $mime);

        return match (true) {
            $mime === 'application/pdf', str_ends_with($name, '.pdf') => 'PDF Document',
            $mime === 'image/jpeg', (bool) preg_match('/\.jpe?g$/', $name) => 'JPEG Image',
            $mime === 'image/png', str_ends_with($name, '.png') => 'PNG Image',
            $mime === 'image/webp', str_ends_with($name, '.webp') => 'WEBP Image',
            (bool) preg_match('/wordprocessingml|msword/', $mime), (bool) preg_match('/\.docx?$/', $name) => 'Word Document',
            (bool) preg_match('/spreadsheetml|excel/', $mime), (bool) preg_match('/\.xlsx?$/', $name) => 'Excel Spreadsheet',
            default => 'Document',
        };
    }
}
