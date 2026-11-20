<?php

namespace App\Http\Requests\Concerns;

/**
 * The one rule for what ORDO will accept as a document.
 *
 * Shared by all three upload paths — intake, a supporting file on an existing
 * Record, and Upload as Record — because they were not agreeing. Intake checked
 * the file type and the other two checked nothing at all, so the ceiling was
 * whatever the browser's file picker happened to offer.
 *
 * Validation is on the extension, not on the content-detected type, and that is
 * the whole point. The `mimes` rule maps a file's sniffed MIME to the first
 * extension Symfony knows for it, which turned away perfectly good documents:
 *
 *   application/x-ole-storage  →  no extension at all  →  a legacy .xls refused
 *   text/plain                 →  txt                  →  a .txt refused
 *
 * The allowlist below is the contract with the reader, and it is the same list
 * the file picker's `accept` attribute offers, so the dialog and the server agree
 * — both read config('ordo.document_extensions'), rather than two hand-kept copies.
 *
 * What it deliberately does not do is re-check the content. A file whose bytes
 * are not what its name says is stored with the type it actually has, shown as
 * such in the Files tab, and served under a Content-Type that cannot execute —
 * see RecordFileStreamer. Refusing it outright would only ever reject files that
 * are already refused by the extension list; what matters is that a stored
 * document is never handed to the browser's HTML engine.
 */
trait ValidatesUploadedDocuments
{
    /**
     * @return list<string>
     */
    protected function documentExtensions(): array
    {
        $configured = config('ordo.document_extensions');

        // The configured list is the single source of truth, because the file
        // pickers read the same key to build their accept attribute. The fallback
        // exists only so a missing or emptied config cannot turn the allowlist
        // into "allow anything", which is the one failure this rule must not have.
        if (! is_array($configured) || $configured === []) {
            return [
                'pdf', 'txt', 'csv', 'doc', 'docx',
                'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp',
            ];
        }

        return array_values(array_map(
            static fn ($extension): string => strtolower(trim((string) $extension)),
            $configured,
        ));
    }

    /**
     * The extension rules for a single uploaded file.
     *
     * @return list<string>
     */
    protected function documentRules(): array
    {
        return ['extensions:'.implode(',', $this->documentExtensions())];
    }

    /**
     * @return array<string, string>
     */
    protected function documentMessages(string $attribute): array
    {
        return [
            "{$attribute}.extensions" => sprintf(
                'Each file must be a %s document.',
                $this->documentExtensionsLabel(),
            ),
        ];
    }

    /**
     * The same list, written the way a reader would say it.
     */
    protected function documentExtensionsLabel(): string
    {
        return 'PDF, text, CSV, Word, Excel or image';
    }
}
