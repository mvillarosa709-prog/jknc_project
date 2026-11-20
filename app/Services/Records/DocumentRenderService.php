<?php

namespace App\Services\Records;

use App\Models\RecordFile;
use App\Services\Records\Rendering\DocxHtmlRenderer;
use App\Services\Records\Rendering\OfficeHtmlRenderer;
use App\Services\Records\Rendering\XlsxHtmlRenderer;
use Illuminate\Support\Facades\Cache;

/**
 * Renders an Office document to a page a reader can look at.
 *
 * The reason this exists at all is that no browser will show a .docx or an .xlsx.
 * Both are ZIP archives of XML, so the alternative was either shipping the reader
 * to a converter, or asking someone to install one on the server. Reading the
 * archive directly is the third option and it costs nothing: PHP has had
 * ZipArchive and DOMDocument for twenty years.
 *
 * What comes out is served under a strict sandbox by RecordFileRenderController —
 * see the note there about scripts and origins, which is the part that makes
 * generating HTML from an uploaded file acceptable at all.
 */
class DocumentRenderService
{
    /**
     * Bumped whenever a renderer's output changes, so cached pages from an older
     * build are replaced rather than served until they expire.
     */
    private const CACHE_VERSION = 'v1';

    private const CACHE_MINUTES = 60;

    /** @var list<OfficeHtmlRenderer> */
    private readonly array $renderers;

    public function __construct(
        DocxHtmlRenderer $docx,
        XlsxHtmlRenderer $xlsx,
    ) {
        $this->renderers = [$docx, $xlsx];
    }

    /**
     * The full page for a file, or null when this service cannot render it.
     */
    public function page(RecordFile $file): ?string
    {
        $renderer = $this->rendererFor($file);

        if (! $renderer instanceof OfficeHtmlRenderer) {
            return null;
        }

        // Keyed on the checksum, so re-uploading different bytes under the same
        // name cannot serve a stale render, and a re-read of the same bytes costs
        // nothing.
        return Cache::remember(
            $this->cacheKey($file),
            self::CACHE_MINUTES,
            function () use ($file, $renderer): string {
                $body = $renderer->body($file);

                return $body === null
                    ? $this->failurePage($file)
                    : $renderer->wrap($body, $file->original_name);
            },
        );
    }

    /**
     * Whether a file can be rendered at all, without rendering it.
     *
     * Decided on the extension so the drawer can offer the link without paying
     * for a parse on every page load. Whether the conversion then succeeds is
     * found out at request time and reported in the page.
     */
    public function canRender(RecordFile $file): bool
    {
        return $this->rendererFor($file) instanceof OfficeHtmlRenderer;
    }

    private function rendererFor(RecordFile $file): ?OfficeHtmlRenderer
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($file)) {
                return $renderer;
            }
        }

        return null;
    }

    private function cacheKey(RecordFile $file): string
    {
        return 'record-file-render:'.self::CACHE_VERSION.':'.$file->id.':'.$file->checksum_sha256;
    }

    /**
     * What a reader sees when the archive cannot be read.
     *
     * A damaged .docx is a normal thing to be handed — a truncated upload, or an
     * OLE file renamed .docx by somebody in a hurry. It is stored perfectly well
     * and can still be downloaded, so this says so rather than failing the
     * request or pretending the document is empty.
     */
    private function failurePage(RecordFile $file): string
    {
        $body = '<p class="empty">'
            .'This document could not be opened for preview. Its contents may be damaged, '
            .'or it may not be the kind of file its name suggests.'
            .'</p>'
            .'<p class="empty">The original is still stored exactly as it was uploaded.</p>';

        return $this->renderers[0]->wrap($body, $file->original_name);
    }
}
