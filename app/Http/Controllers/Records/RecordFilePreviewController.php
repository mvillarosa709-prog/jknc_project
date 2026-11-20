<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Models\RecordFile;
use App\Services\CompanyContext;
use App\Services\Records\RecordFileStreamer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Preview one specific stored file.
 *
 * The record and intake routes above address a preview by their owner, which is
 * only ever the current master. That is the right default for a pane, but it left
 * a record's supporting files and versions unopenable: every file in the payload
 * carried the same URL, because the URL described the record rather than the
 * file. This route names the file.
 *
 * One route parameter, and it is a string rather than a model. Laravel resolves
 * only class-typed parameters by name; anything else arrives positionally in
 * route order, so a two-parameter action here would hand this method the wrong
 * value. The arity matters — the file routes beside it were collapsed for the
 * same reason.
 *
 * Scope is the company, not the record: a file belongs to one or the other, and
 * both are only ever read inside the tenant and company the session is in.
 */
class RecordFilePreviewController extends Controller
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly RecordFileStreamer $streamer,
    ) {}

    public function preview(string $file): StreamedResponse
    {
        return $this->streamer->stream($this->file($file));
    }

    public function download(string $file): StreamedResponse
    {
        return $this->streamer->download($this->file($file));
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
