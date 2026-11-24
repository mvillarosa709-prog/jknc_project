<?php

namespace App\Http\Controllers\Records;

use App\Enums\IntakeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkFileRecordsRequest;
use App\Http\Requests\FileRecordRequest;
use App\Models\RecordFile;
use App\Models\RecordIntakeItem;
use App\Services\CompanyContext;
use App\Services\Records\RecordFileStreamer;
use App\Services\Records\RecordFilingService;
use App\Services\Records\RecordIntakeService;
use App\Services\Records\RecordLookups;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassifyController extends Controller
{
    public function __construct(
        private readonly RecordFilingService $filing,
        private readonly RecordLookups $lookups,
        private readonly RecordFileStreamer $streamer,
    ) {}

    public function index(Request $request, CompanyContext $context): View
    {
        $search = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');

        return view('records.unclassified', [
            'title' => 'Unclassified Records',
            'unclassifiedCount' => $this->openItems($context)->count(),
            // Which item to open the drawer on, if any. The Integration tab's
            // "Register" links here with ?open= so a named upload can be filed
            // without hunting for it in the list. Null on an ordinary visit, and
            // an id this company does not own is ignored rather than honoured.
            'openItemId' => $this->openItemId($request, $context),
            'intakeItems' => $this->openItems($context)
                ->with(['files' => fn ($query) => $query->orderBy('id')])
                ->when($search !== '', fn (Builder $query) => $query->where(
                    fn (Builder $inner) => $inner
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('source', 'like', '%'.$search.'%')
                        ->orWhere('external_reference_number', 'like', '%'.$search.'%')
                        ->orWhereHas('files', fn (Builder $files) => $files->where('original_name', 'like', '%'.$search.'%'))
                ))
                ->when($this->typeFilter($type) !== null, fn (Builder $query) => $this->applyTypeFilter($query, $type))
                ->latest('created_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'classifications' => $this->lookups->classifications(),
            'indexesByClassification' => $this->lookups->indexesByClassification(),
            'sources' => $this->lookups->documentSources(),
            'drawerItems' => $this->drawerItems($context),
            'search' => $search,
            'type' => $type,
        ]);
    }

    public function store(FileRecordRequest $request, CompanyContext $context, string $intake): RedirectResponse
    {
        $item = $this->findOpenItem($context, $intake);

        $record = $this->filing->file($item, $this->attributes($request), Auth::user());

        return redirect()
            ->route('records.unclassified')
            ->with('status', sprintf(
                'Filed as Document Reference %s. This record now uses 1 Record Unit.',
                $record->document_reference,
            ));
    }

    public function bulkStore(BulkFileRecordsRequest $request, CompanyContext $context): RedirectResponse
    {
        $items = $this->openItems($context)
            ->whereIn('id', $request->input('items', []))
            ->get();

        if ($items->count() !== count(array_unique($request->input('items', [])))) {
            throw new LogicException('One or more of the selected unclassified records is no longer available.');
        }

        $records = $this->filing->fileMany(
            $items->modelKeys(),
            $this->attributes($request),
            Auth::user(),
        );

        return redirect()
            ->route('records.unclassified')
            ->with('status', sprintf(
                '%d record(s) classified and filed. Each uses 1 Record Unit.',
                count($records),
            ));
    }

    /**
     * Move an intake item out of the staging queue. Nothing is deleted from disk.
     */
    public function destroy(CompanyContext $context, string $intake, RecordIntakeService $intakeService): RedirectResponse
    {
        $item = $this->findOpenItem($context, $intake);

        $discarded = $intakeService->discard($item, Auth::user());

        return redirect()
            ->route('records.unclassified')
            ->with('status', sprintf(
                '"%s" was discarded. Its stored file is retained on disk and its activity trail is preserved.',
                $discarded->title,
            ));
    }

    public function preview(CompanyContext $context, string $intake): StreamedResponse
    {
        $item = $this->findOpenItem($context, $intake);
        $file = RecordFile::currentMasterFor($item->files());

        abort_if($file === null, 404);

        return $this->streamer->stream($file);
    }

    /**
     * The item the drawer should open on, or null.
     *
     * Checked against this company's own open items rather than trusted, so a
     * crafted ?open= resolves to nothing rather than pointing the drawer at a
     * document belonging to somebody else.
     */
    private function openItemId(Request $request, CompanyContext $context): ?string
    {
        $requested = trim((string) $request->query('open', ''));

        if ($requested === '') {
            return null;
        }

        $owned = $this->openItems($context)->whereKey($requested)->exists();

        return $owned ? $requested : null;
    }

    /**
     * The classification drawer opens client-side, so each open item needs the small
     * amount of state the workspace shows before anything is submitted.
     *
     * @return array<int, array<string, mixed>>
     */
    private function drawerItems(CompanyContext $context): array
    {
        return $this->openItems($context)
            ->with(['files' => fn ($query) => $query->currentMasterFirst()])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (RecordIntakeItem $item): array => [
                'id' => (string) $item->id,
                'title' => $item->title,
                'source' => $item->source,
                'documentDate' => $item->document_date?->format('Y-m-d'),
                'externalReference' => $item->external_reference_number,
                'storageBytes' => (int) $item->storage_bytes,
                'status' => $item->status->value,
                'statusLabel' => $item->status->label(),
                'suggestedClassificationId' => (string) ($item->suggested_classification_id ?? ''),
                'suggestedIndexId' => (string) ($item->suggested_index_id ?? ''),
                'files' => $item->files->map(fn ($file): array => [
                    'id' => (string) $file->id,
                    'name' => $file->original_name,
                    'mimeType' => $file->mime_type,
                    'extension' => $file->extension,
                    'sizeBytes' => (int) $file->size_bytes,
                    'pageCount' => $file->page_count,
                    'documentType' => $file->documentType(),
                    // The drawer picks which file to preview with the same rule the
                    // server uses — master first, then the highest version — so both
                    // fields have to be present or that sort degrades to id order
                    // without anything failing.
                    'version' => (int) $file->version,
                    'fileRole' => $file->file_role->value,
                    'ocrStatus' => $file->ocr_status->value,
                    'ocrStatusLabel' => $file->ocr_status->label(),
                    // So the preview can explain an empty OCR pane with the file's
                    // real reason rather than a generic message.
                    'ocrError' => $file->ocr_error,
                    'ocrText' => $file->ocr_text,
                    'previewUrl' => route('records.files.preview', ['file' => $file->id]),
                    'downloadUrl' => route('records.files.download', ['file' => $file->id]),
                    'isImage' => $file->isImage(),
                    'isPdf' => $file->isPdf(),
                    'isText' => $file->isText(),
                    // One rule, shared with RecordFileStreamer: what the drawer
                    // draws and what the browser is sent have to be one decision.
                    'renderMode' => $file->renderMode(),
                    // Null for everything else, so the drawer only offers the frame
                    // when there is something we can put in it.
                    'renderedUrl' => $file->canRenderAsHtml()
                        ? route('records.files.rendered', ['file' => $file->id])
                        : null,
                ])->all(),
            ])
            ->all();
    }

    /**
     * Unclassified records are intake items that have not been filed or discarded yet.
     */
    private function openItems(CompanyContext $context): Builder
    {
        return RecordIntakeItem::query()
            ->where('tenant_id', $context->tenantId())
            ->where('company_id', $context->id())
            ->whereNotIn('status', [IntakeStatus::Filed, IntakeStatus::Discarded]);
    }

    private function findOpenItem(CompanyContext $context, string $intake): RecordIntakeItem
    {
        return $this->openItems($context)
            ->with('files')
            ->findOrFail($intake);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(FileRecordRequest|BulkFileRecordsRequest $request): array
    {
        return [
            'record_classification_id' => $request->input('record_classification_id'),
            'record_index_id' => $request->input('record_index_id'),
            'title' => $request->input('title'),
            'document_date' => $request->input('document_date'),
            'source' => $request->input('source') === 'Others'
                ? $request->input('source_other')
                : $request->input('source'),
            'external_reference_number' => $request->input('external_reference_number'),
            'identifiers' => $request->input('identifiers'),
            'additional_dates' => $request->input('additional_dates'),
            'signatories' => $request->input('signatories'),
            'financials' => $request->input('financials'),
            'is_confidential' => $request->boolean('is_confidential'),
        ];
    }

    private function typeFilter(string $type): ?string
    {
        return in_array($type, ['image', 'pdf', 'other'], true) ? $type : null;
    }

    private function applyTypeFilter(Builder $query, string $type): Builder
    {
        return match ($this->typeFilter($type)) {
            'image' => $query->whereHas('files', fn (Builder $files) => $files->where('mime_type', 'like', '%image%')),
            'pdf' => $query->whereHas('files', fn (Builder $files) => $files->where('mime_type', 'like', '%pdf%')),
            'other' => $query->whereHas('files', fn (Builder $files) => $files->where(function (Builder $inner): void {
                $inner->whereNull('mime_type')
                    ->orWhere('mime_type', 'not like', '%image%')
                    ->orWhere('mime_type', 'not like', '%pdf%');
            })),
            default => $query,
        };
    }
}
