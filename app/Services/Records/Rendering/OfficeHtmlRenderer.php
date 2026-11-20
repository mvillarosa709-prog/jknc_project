<?php

namespace App\Services\Records\Rendering;

use App\Models\RecordFile;
use Illuminate\Support\Facades\Storage;

/**
 * Turns the contents of an Office archive into HTML a browser can show.
 *
 * A .docx and an .xlsx are both ZIP archives of XML, and PHP has had ZipArchive
 * and DOMDocument for twenty years. That is the whole reason this works without
 * a single package: no converter binary, no vendor library, nothing to install
 * and nothing to keep patched.
 *
 * Every value that reaches the page goes through html(). That is not defensive
 * tidiness — a .docx is a ZIP a reader uploaded, so its text is attacker-supplied
 * by definition, and this output is served as HTML. The escaping is the only
 * thing standing between a paragraph and a script.
 */
abstract class OfficeHtmlRenderer
{
    /**
     * A ZIP entry this size is a decompression bomb or a mistake, not a document.
     */
    protected const MAX_PART_BYTES = 8 * 1024 * 1024;

    /**
     * Whether this renderer handles the given file.
     */
    abstract public function supports(RecordFile $file): bool;

    /**
     * The document body, or null when the archive cannot be read.
     *
     * Null is a normal outcome, not an error: a .docx whose central directory is
     * damaged, or one that turns out to be an OLE file renamed .docx, is stored
     * perfectly well and simply cannot be shown. The caller renders a message
     * instead of failing the request.
     */
    abstract public function body(RecordFile $file): ?string;

    /**
     * The file extension this renderer claims.
     */
    abstract protected function extension(): string;

    /**
     * Read one part of the archive, or null if it is absent or implausibly large.
     *
     * The archive is copied to local disk for the duration of the read, because
     * ZipArchive needs a seekable path and a Storage disk may not have one. The
     * copy is removed only after the archive is closed: on Windows a ZipArchive
     * reopens its path as it reads, so deleting the file while it is still open
     * makes every subsequent read fail.
     */
    protected function part(RecordFile $file, string $name): ?string
    {
        $contents = $this->bytes($file);

        if ($contents === null || $contents === '') {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'ordo-office-');

        if ($temp === false || file_put_contents($temp, $contents) === false) {
            return null;
        }

        try {
            $zip = new \ZipArchive;

            if ($zip->open($temp) !== true) {
                return null;
            }

            try {
                $stat = $zip->statName($name);

                if ($stat === false || $stat['size'] > self::MAX_PART_BYTES) {
                    return null;
                }

                $part = $zip->getFromName($name);

                return $part === false ? null : $part;
            } finally {
                $zip->close();
            }
        } finally {
            @unlink($temp);
        }
    }

    /**
     * The raw stored bytes of the file.
     */
    protected function bytes(RecordFile $file): ?string
    {
        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            return null;
        }

        $contents = $disk->get($file->path);

        return $contents === null ? null : $contents;
    }

    /**
     * Parse an XML part, refusing anything that is not well-formed.
     *
     * External entities are disabled outright. A .docx is untrusted input, and
     * XXE on a file the reader uploaded is a read of the server's own filesystem.
     */
    protected function dom(?string $xml): ?\DOMDocument
    {
        if ($xml === null || trim($xml) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument;

            // LIBXML_NONET and nothing that substitutes entities. NOENT would be
            // the wrong flag entirely: it *resolves* entities, which is how a DTD
            // in an uploaded file turns into a read of the server's own files.
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);

            return $loaded === true ? $document : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * The only way a value reaches the page.
     */
    protected function html(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Collapse the whitespace Office XML leaves behind inside a run.
     */
    protected function text(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    /**
     * The stylesheet both renderers share.
     *
     * Written against the application's own type scale and palette rather than a
     * document-styled one: this is ORDO showing a reader what is in a file, not a
     * reproduction of how Word laid the file out.
     */
    protected function styles(): string
    {
        return <<<'CSS'
            :root { color-scheme: light; }
            body {
                margin: 0; padding: 22px 26px; background: #fff; color: #1f2937;
                font-family: Inter, ui-sans-serif, system-ui, sans-serif;
                font-size: 13px; line-height: 1.6;
            }
            h1, h2, h3, h4 { color: #101828; line-height: 1.3; margin: 18px 0 6px; }
            h1 { font-size: 17px; } h2 { font-size: 15px; } h3 { font-size: 14px; } h4 { font-size: 13px; }
            p { margin: 0 0 9px; }
            ul, ol { margin: 0 0 9px; padding-left: 22px; }
            li { margin: 0 0 3px; }
            table { border-collapse: collapse; width: 100%; margin: 6px 0 14px; font-size: 12px; }
            th, td { border: 1px solid #d9dee7; padding: 5px 8px; text-align: left; vertical-align: top; }
            th { background: #f3f4f6; font-weight: 600; color: #344054; }
            tr:nth-child(even) td { background: #fbfcfe; }
            .sheet-title { font-size: 12px; font-weight: 700; text-transform: uppercase;
                           letter-spacing: .04em; color: #475467; margin: 0 0 8px; }
            .truncated { margin: 14px 0 0; padding: 8px 10px; border-radius: 6px;
                         background: #fff8f8; border: 1px solid #f4cccc; color: #b42318; font-size: 11px; }
            .empty { color: #98a2b3; font-size: 12px; }
        CSS;
    }

    /**
     * Wrap a body in the shared document shell.
     *
     * Public because the render service builds the "could not open this" page too,
     * and that page must come out wearing the same shell as a real one.
     */
    public function wrap(string $body, ?string $title = null): string
    {
        $head = $title === null
            ? ''
            : '<title>'.$this->html($title).'</title>';

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'.$head
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<style>'.$this->styles().'</style></head><body>'.$body.'</body></html>';
    }

    /**
     * The notice shown when a document was too large to render in full.
     */
    protected function truncatedNotice(string $what): string
    {
        return '<p class="truncated">Showing the first '.$this->html($what)
            .'. Download the original to read the whole document.</p>';
    }
}
