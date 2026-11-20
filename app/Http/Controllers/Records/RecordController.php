<?php

namespace App\Http\Controllers\Records;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ArchiveRecordRequest;
use App\Http\Requests\UpdateRecordRequest;
use App\Models\Record;
use App\Models\RecordActivityLog;
use App\Models\RecordFile;
use App\Models\RecordLink;
use App\Services\Records\RecordFileStreamer;
use App\Services\Records\RecordLookups;
use App\Services\Records\RecordSearchService;
use App\Services\Records\RecordService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordController extends Controller
{
    /**
     * The 11 columns shown in the register, in order. The CSV export mirrors this exactly.
     */
    private const EXPORT_HEADERS = [
        'Doc Ref', 'Record ID', 'Title', 'Classification', 'Index',
        'Source', 'Document Date', 'Amount', 'Currency', 'Origin', 'Status',
    ];

    public function __construct(
        private readonly RecordService $records,
        private readonly RecordLookups $lookups,
        private readonly RecordSearchService $search,
        private readonly RecordFileStreamer $streamer,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        $records = $this->filtered($filters)->paginate(25)->withQueryString();

        return view('records.index', [
            'title' => 'All Records',
            'records' => $records,
            'classifications' => $this->lookups->classifications(),
            'indexesByClassification' => $this->lookups->indexesByClassification(),
            'indexes' => $this->lookups->indexes(),
            'sources' => $this->lookups->sources(),
            'sourceOptions' => $this->lookups->documentSources(),
            'filters' => $filters,
            'workspaceRecords' => $this->workspace($records->getCollection()),
            'pickerUrl' => route('records.links.picker', ['record' => '__record__']),
            'reReadUrl' => route('records.files.re-read', ['file' => '__file__']),
            'fileStatusUrl' => route('records.files.status', ['file' => '__file__']),
            // The Files tab acts on whichever record is open, so these carry a
            // placeholder rather than an id the server does not know yet.
            'supportingFileUrl' => route('records.files.supporting', ['record' => '__record__']),
            'newVersionUrl' => route('records.files.version', ['record' => '__record__']),
            'uploadAsRecordUrl' => route('records.files.upload-as-record'),
            // Passed exactly as the classify drawer receives them, so the modal
            // shares the same indexOptions helper rather than a second shape.
            'uploadClassifications' => $this->lookups->classifications()->pluck('name', 'id'),
            'uploadIndexesByClassification' => $this->lookups->indexesByClassification(),
        ]);
    }

    /**
     * Flatten the paginated register into the payload the workspace drawer renders client-side.
     *
     * @param  Collection<int, Record>  $records
     * @return array<int, array<string, mixed>>
     */
    private function workspace(Collection $records): array
    {
        return $records->map(fn (Record $record): array => [
            'id' => (string) $record->id,
            'documentReference' => $record->document_reference,
            'title' => $record->title,
            'status' => $record->status->value,
            'statusLabel' => $record->status->label(),
            'archived' => $record->status === RecordStatus::Archived,
            'classificationLabel' => $record->classification
                ? sprintf('%s — %s', $record->classification->code, $record->classification->name)
                : '',
            'classificationCode' => $record->classification?->code,
            'indexLabel' => $record->index
                ? sprintf('%s — %s', $record->index->code, $record->index->name)
                : '',
            'indexCode' => $record->index?->code,
            'documentDate' => $record->document_date?->format('Y-m-d'),
            'source' => $record->source,
            'externalReference' => $record->external_reference_number,
            'amount' => $record->amount,
            'currency' => $record->currency,
            'notes' => $record->notes,
            'identifiers' => $record->identifiers,
            'additionalDates' => $record->additional_dates,
            'signatories' => $record->signatories,
            'financials' => $record->financials,
            'retentionPeriod' => $record->retention_period,
            'isOnLegalHold' => $record->isOnLegalHold(),
            'isConfidential' => $record->is_confidential,
            'recordUnits' => $record->recordUnits(),
            'storageBytes' => (int) $record->storage_bytes,
            'storageDisk' => (string) config('filesystems.default'),
            // files is eager loaded through currentMasterFirst(), so first() is the master
                // and not merely the oldest row.
                'storageKey' => (string) ($record->files->first()?->path ?? ''),
            'intakeItemId' => $record->record_intake_item_id,
            'tenantId' => (string) $record->tenant_id,
            'companyId' => (string) $record->company_id,
            'filedAt' => $record->filed_at?->format('Y-m-d H:i'),
            'filedBy' => $record->filedBy?->name,
            'createdAt' => $record->created_at?->format('Y-m-d H:i'),
            'updatedAt' => $record->updated_at?->format('Y-m-d H:i'),
            'linkCount' => (int) ($record->links_count ?? $record->links->count()),
            'links' => $record->links->map(fn (RecordLink $link): array => [
                'id' => (string) $link->id,
                'module' => $link->moduleLabel(),
                'moduleSlug' => (string) $link->linked_type,
                'linkedId' => (string) $link->linked_id,
                'linkedLabel' => $link->linked_label,
                'linkType' => $link->link_type->value,
                'linkTypeLabel' => $link->link_type->label(),
                'notes' => $link->notes,
                'createdAt' => $link->created_at?->format('Y-m-d H:i'),
            ])->all(),
            'history' => $record->activityLogs->map(fn (RecordActivityLog $log): array => [
                'id' => (string) $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user?->name,
                'createdAt' => $log->created_at?->format('Y-m-d H:i'),
            ])->sortByDesc('createdAt')->values()->all(),
            'files' => $record->files->map(fn (RecordFile $file): array => [
                'id' => (string) $file->id,
                'name' => $file->original_name,
                'mimeType' => $file->mime_type,
                'extension' => $file->extension,
                'sizeBytes' => (int) $file->size_bytes,
                'pageCount' => $file->page_count,
                'documentType' => $file->documentType(),
                'checksum' => $file->checksum_sha256,
                'version' => (int) $file->version,
                'fileRole' => $file->file_role->value,
                'isImage' => $file->isImage(),
                'isPdf' => $file->isPdf(),
                'isText' => $file->isText(),
                // One rule, shared with RecordFileStreamer: what the drawer draws
                // and what the browser is sent have to be one decision.
                'renderMode' => $file->renderMode(),
                // Null for everything else, so the drawer only offers the frame
                // when there is something we can put in it.
                'renderedUrl' => $file->canRenderAsHtml()
                    ? route('records.files.rendered', ['file' => $file->id])
                    : null,
                'ocrStatusLabel' => $file->ocr_status->label(),
                // Carried so the preview can explain an empty OCR pane with the
                // file's real reason rather than a generic message.
                'ocrError' => $file->ocr_error,
                'ocrText' => $file->ocr_text,
                'createdAt' => $file->created_at?->format('Y-m-d H:i'),
                // Per file, not per record: the record-scoped URL only ever
                // resolves the current master, so sharing it across the payload
                // left supporting files and versions unopenable.
                'previewUrl' => route('records.files.preview', ['file' => $file->id]),
                'downloadUrl' => route('records.files.download', ['file' => $file->id]),
            ])->all(),
        ])->all();
    }

    /**
     * The record arrives already resolved and scoped by the ordo.record middleware,
     * so these actions never re-read it and cannot pick up an unscoped id.
     */
    public function update(UpdateRecordRequest $request, Record $record): RedirectResponse
    {
        $attributes = $request->validated();
        $attributes['source'] = $request->input('source') === 'Others'
            ? $request->input('source_other')
            : $request->input('source');
        unset($attributes['source_other']);

        $this->records->updateMetadata($record, $attributes, Auth::user());

        return redirect()
            ->route('records.index')
            ->with('status', sprintf('Record %s updated.', $record->document_reference));
    }

    public function archive(ArchiveRecordRequest $request, Record $record): RedirectResponse
    {
        $this->records->archive($record, Auth::user(), $request->input('reason'));

        return redirect()
            ->route('records.index')
            ->with('status', sprintf(
                'Record %s archived. It stays in the register and keeps its Document Reference and Record Unit.',
                $record->document_reference,
            ));
    }

    public function preview(Record $record): StreamedResponse
    {
        $file = RecordFile::currentMasterFor($record->files());

        abort_if($file === null, 404);

        return $this->streamer->stream($file);
    }

    /**
     * Stream the currently filtered register as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        return response()->stream(function () use ($filters): void {
            echo $this->csvLine(self::EXPORT_HEADERS);

            $this->filtered($filters)
                ->with(['classification', 'index'])
                ->chunk(200, function ($chunk): void {
                    foreach ($chunk as $model) {
                        echo $this->csvLine($this->exportRow($model));
                    }
                });
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ordo-records-register.csv"',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        return RecordSearchService::normalise($request->query());
    }

    /**
     * The register's eager loads and ordering, on top of the shared search service.
     *
     * @param  array<string, string>  $filters
     */
    private function filtered(array $filters): Builder
    {
        return $this->search->search($filters)
            ->withCount('links')
            // The workspace payload reads all five of these on every row of the
            // page. Eager loading them keeps the page at a fixed number of queries
            // instead of growing with the page size.
            ->with([
                'classification',
                'index',
                'filedBy:id,name',
                'files' => fn ($query) => $query->currentMasterFirst(),
                'links',
                'activityLogs.user:id,name',
            ])
            ->latest('document_reference')
            ->latest('id');
    }

    /**
     * @return array<int, string>
     */
    private function exportRow(Record $record): array
    {
        return [
            (string) ($record->document_reference ?? ''),
            (string) $record->id,
            (string) $record->title,
            (string) ($record->classification?->code ?? ''),
            (string) ($record->index?->code ?? ''),
            (string) ($record->source ?? ''),
            $record->document_date?->format('Y-m-d') ?? '',
            $record->amount !== null ? (string) $record->amount : '',
            (string) ($record->currency ?? ''),
            'Uploaded',
            (string) $record->status->label(),
        ];
    }

    /**
     * @param  array<int, string>  $values
     */
    private function csvLine(array $values): string
    {
        return implode(',', array_map(static function (string $value): string {
            return '"'.str_replace('"', '""', $value).'"';
        }, $values))."\r\n";
    }
}
