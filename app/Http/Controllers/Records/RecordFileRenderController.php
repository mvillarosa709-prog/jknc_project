<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Models\RecordFile;
use App\Services\CompanyContext;
use App\Services\Records\DocumentRenderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;

/**
 * Show a Word or Excel document's contents as a page.
 *
 * This endpoint is the one place in ORDO that deliberately emits HTML, and it is
 * the reason the rest of the preview stack is built the way it is. Everything
 * here is generated from a file the reader uploaded, so it is attacker-supplied
 * text, and the headers below are the whole security argument:
 *
 *   sandbox; default-src 'none'; style-src 'unsafe-inline'
 *     `sandbox` with no allow-same-origin puts the document in an opaque origin,
 *     so it cannot read this session's cookies or reach this page's DOM. There is
 *     no script-src at all, and default-src 'none' means scripts are denied even
 *     if a future edit adds one by accident.
 *
 *   nosniff
 *     Stops the response being re-interpreted as anything other than what it
 *     claims to be.
 *
 * Every value in the body is escaped by the renderers, so the escaping is the
 * second layer and the headers are the first. Neither is optional.
 *
 * The residual risk is worth stating rather than hiding: sandboxed HTML on this
 * domain could still be used to show a convincing fake ORDO page. Serving
 * previews from a separate origin would remove it entirely, and that is an
 * infrastructure decision rather than a code one.
 */
class RecordFileRenderController extends Controller
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly DocumentRenderService $renders,
    ) {}

    public function rendered(string $file): Response
    {
        $record = $this->file($file);

        $page = $this->renders->page($record);

        abort_if($page === null, 404);

        return response($page, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; style-src 'unsafe-inline'",
            'Content-Disposition' => 'inline',
            // A rendered page is derived from the file, and a new one is built the
            // moment the file changes. Caching it would only ever show a reader
            // yesterday's version of a document.
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * @throws ModelNotFoundException
     */
    private function file(string $file): RecordFile
    {
        $resolved = RecordFile::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->find($file);

        if (! $resolved instanceof RecordFile) {
            throw (new ModelNotFoundException)->setModel(RecordFile::class);
        }

        return $resolved;
    }
}
