<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachRecordFileRequest;
use App\Http\Requests\UploadAsRecordRequest;
use App\Models\Record;
use App\Services\Records\RecordAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * The Files tab's three actions.
 *
 * Each is an ordinary form post that redirects back with a status, because each
 * changes the record on the server and the tab has to show what changed. They sit
 * behind ordo.record, so a record belonging to another company is a 404 before
 * this runs.
 *
 * A LogicException from the service is a refusal the reader can act on — the
 * storage ceiling, or an archived record — so it comes back as a flash rather
 * than a 500.
 */
class RecordFileAttachmentController extends Controller
{
    public function __construct(private readonly RecordAttachmentService $attachments) {}

    public function supportingFile(AttachRecordFileRequest $request, Record $record): RedirectResponse
    {
        return $this->run(
            fn () => $this->attachments->addSupportingFile($record, $request->file('file'), Auth::user()),
            sprintf('Supporting file %s added to %s.', $request->file('file')->getClientOriginalName(), $record->document_reference),
        );
    }

    public function version(AttachRecordFileRequest $request, Record $record): RedirectResponse
    {
        return $this->run(
            fn () => $this->attachments->addVersion($record, $request->file('file'), Auth::user()),
            sprintf('New version of %s stored. No Record Unit was consumed.', $record->document_reference),
        );
    }

    public function uploadAsRecord(UploadAsRecordRequest $request): RedirectResponse
    {
        return $this->run(
            function () use ($request): void {
                $record = $this->attachments->uploadAsRecord(
                    $request->file('file'),
                    $request->validated(),
                    Auth::user(),
                );

                session()->flash('status', sprintf(
                    'Uploaded and filed as %s. One Record Unit was consumed.',
                    $record->document_reference,
                ));
            },
            '',
        );
    }

    /**
     * Run a write, turning a refusal into a flash message.
     *
     * The service throws LogicException for the two conditions a reader can do
     * something about — the storage ceiling and an archived record — and those are
     * reported rather than surfaced as a server error.
     */
    private function run(callable $write, string $success): RedirectResponse
    {
        try {
            $write();
        } catch (LogicException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $success);
    }
}
