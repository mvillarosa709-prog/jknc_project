<?php

namespace App\Http\Controllers\Records;

use App\Enums\OcrStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessIntakeOcr;
use App\Models\RecordActivityLog;
use App\Models\RecordFile;
use App\Services\CompanyContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Re-reading a document from either preview.
 *
 * The same control appears in two places: the record workspace drawer, for a file
 * that has been filed, and the classify drawer, for one still sitting on an
 * unclassified intake item. A file is a file either way, so one controller serves
 * both and the file is resolved against the active company rather than against a
 * parent that only one of the two callers has.
 *
 * The control does exactly one thing here: it puts this one file back on the
 * queue. It does not extract anything itself, and it does not pretend to —
 * extraction is ProcessIntakeOcr's job, on a worker. What this controller owns is
 * the part that is real: the file is reset to Queued, the job is dispatched against
 * that single file, and the preview can then poll for the genuine outcome rather
 * than showing a timer that means nothing.
 *
 * Both routes are driven by fetch, so they answer with JSON.
 *
 * A note on the single-parameter signature. Laravel hands route parameters to a
 * controller by position, and only rewrites one into a method parameter when that
 * parameter is class-typed; anything else is taken from the route's parameter
 * list in order. A method declaring just `string $file` against a route carrying
 * two parameters therefore receives the *first* one — and since a model casts to
 * its own JSON when a string is demanded, the record arrives looking like an id
 * and then matches nothing. These routes carry the file alone, which is what the
 * controller actually needs: the file is resolved against the active company, and
 * that is the boundary the rest of the app scopes by.
 */
class RecordFileReadingController extends Controller
{
    public function __construct(private readonly CompanyContext $context) {}

    /**
     * Queue this file to be read again.
     *
     * Idempotent while a read is already in flight: a second click returns the
     * state already in progress instead of stacking another job on the queue. That
     * matters because the button stays live for the whole run, and a reader who
     * clicks twice should not leave two jobs writing the same file.
     */
    public function reRead(string $file): JsonResponse
    {
        $target = $this->file($file);

        // Blocked only while a read is genuinely in progress. A file sitting on
        // Queued is not proof that anything is happening: this queue is
        // database-backed and the prototype may have no worker running, in which
        // case Queued is effectively permanent and refusing to re-queue would
        // leave the control inert for exactly the case it exists to solve. Two
        // jobs settling the same file is harmless — the job writes the same
        // terminal state either way.
        if ($target->ocr_status === OcrStatus::Reading) {
            return $this->state($target);
        }

        $target->forceFill([
            'ocr_status' => OcrStatus::Queued,
            'ocr_error' => null,
            'ocr_completed_at' => null,
        ])->save();

        // Scoped to this one file. The intake item travels too because it is the
        // job's primary key into the domain, but a null one is fine here and is
        // the normal case for a supporting file or a new version.
        ProcessIntakeOcr::dispatch(
            $target->record_intake_item_id,
            $this->context->tenantId(),
            $this->context->id(),
            (string) $target->id,
        );

        $this->log($target);

        return $this->state($target->refresh());
    }

    /**
     * The file's real reading state, for the preview to poll.
     */
    public function status(string $file): JsonResponse
    {
        return $this->state($this->file($file));
    }

    /**
     * Resolve a file inside the active company.
     *
     * Tenant and company are the boundary here. They are enough on their own:
     * the caller may be holding a record id, an intake id, or neither, and what
     * matters for reading a file is only that it belongs to this company.
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

    /**
     * Record the request against whatever the file belongs to.
     *
     * A filed file is logged against its Record; one still on the queue is logged
     * against its intake item, because no Record exists yet — the same rule the
     * rest of the audit trail follows.
     */
    private function log(RecordFile $file): void
    {
        RecordActivityLog::query()->create([
            'tenant_id' => $file->tenant_id,
            'company_id' => $file->company_id,
            'record_id' => $file->record_id,
            'record_intake_item_id' => $file->record_id === null ? $file->record_intake_item_id : null,
            'record_file_id' => $file->id,
            'user_id' => Auth::id(),
            'action' => 'record.reread_requested',
            'description' => sprintf('Document reading re-queued for %s.', $file->original_name),
            'properties' => ['ocr_status' => OcrStatus::Queued->value],
            'ip_address' => request()?->ip(),
        ]);
    }

    /**
     * The payload the preview polls.
     *
     * The error is included as-is rather than as a friendly rewrite. Until an
     * engine is installed the genuine reason a read produced nothing is the most
     * useful thing on the screen, and hiding it behind a generic message would
     * make a working system look broken.
     */
    private function state(RecordFile $file): JsonResponse
    {
        return response()->json([
            'id' => (string) $file->id,
            'ocrStatus' => $file->ocr_status->value,
            'ocrStatusLabel' => $file->ocr_status->label(),
            'ocrTerminal' => $file->ocr_status->isTerminal(),
            'ocrError' => $file->ocr_error,
            'ocrText' => $file->ocr_text,
            'ocrCompletedAt' => $file->ocr_completed_at?->format('Y-m-d H:i'),
        ]);
    }
}
