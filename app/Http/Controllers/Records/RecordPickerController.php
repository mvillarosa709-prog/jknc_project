<?php

namespace App\Http\Controllers\Records;

use App\Enums\LinkRelationship;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyRecordLinkRequest;
use App\Http\Requests\StoreRecordLinkRequest;
use App\Models\Record;
use App\Models\RecordLink;
use App\Services\CompanyContext;
use App\Services\Records\RecordLinkService;
use App\Services\Records\RecordSearchService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use LogicException;

/**
 * Picking a record to link to, and writing the link.
 *
 * The picker only ever offers records from the active company: the query is scoped
 * server-side and the source record is excluded so a record cannot link to itself.
 *
 * The source record arrives as a string, not a model. The ordo.record middleware
 * has already resolved it under the company's scope and re-bound it onto the route,
 * so type-hinting a model here would only ask Laravel to bind it a second time —
 * and that second binding would be the unscoped one.
 */
class RecordPickerController extends Controller
{
    private const SEARCH_LIMIT = 25;

    public function __construct(
        private readonly RecordLinkService $links,
        private readonly CompanyContext $context,
        // For its scoped() builder, not for search: that builder is where the
        // confidentiality rule lives, and a picker that offered the title and
        // Document Reference of a Record the Role cannot open would hand it
        // out through a list.
        private readonly RecordSearchService $search,
    ) {}

    public function index(Request $request, string $record): View
    {
        $source = $this->sourceRecord($request);
        $search = trim((string) $request->query('q', ''));

        return view('records.partials.record-picker-modal', [
            'record' => $source,
            'candidates' => $this->candidatePayload($source),
            'linkedIds' => $this->linkedIds($source),
            'relationships' => LinkRelationship::options(),
            'search' => $search,
        ]);
    }

    public function store(StoreRecordLinkRequest $request, string $record): RedirectResponse
    {
        $source = $this->sourceRecord($request);

        try {
            $message = $request->linksToRecord()
                ? $this->linkToRecord($source, $request)
                : $this->linkToModule($source, $request);
        } catch (LogicException $e) {
            return redirect()
                ->route('records.index')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('records.index')
            ->with('status', $message);
    }

    /**
     * The scoped source record, as rebound by the ordo.record middleware.
     */
    private function sourceRecord(Request $request): Record
    {
        $record = $request->route('record');

        if (! $record instanceof Record) {
            throw new ModelNotFoundException;
        }

        return $record;
    }

    private function linkToRecord(Record $source, StoreRecordLinkRequest $request): string
    {
        $target = $this->findScopedRecord((string) $request->input('target'));

        $link = $this->links->linkRecordToRecord(
            $source,
            $target,
            LinkRelationship::from((string) $request->input('link_type')),
            Auth::user(),
        );

        $this->keepNotes($link, $request);

        return sprintf(
            'Linked as %s to %s (%s). Nothing was copied: 0 Record Units and 0 storage bytes added.',
            $link->link_type->label(),
            (string) $link->linked_label,
            (string) $target->document_reference,
        );
    }

    private function linkToModule(Record $source, StoreRecordLinkRequest $request): string
    {
        $link = $this->links->linkRecordToModule(
            $source,
            (string) $request->input('module'),
            (string) $request->input('reference_id'),
            (string) $request->input('linked_label', ''),
            LinkRelationship::from((string) $request->input('link_type')),
            Auth::user(),
        );

        $this->keepNotes($link, $request);

        return sprintf(
            'Linked as %s to the %s object %s. Nothing was copied: 0 Record Units and 0 storage bytes added.',
            $link->link_type->label(),
            $link->moduleLabel(),
            (string) ($link->linked_label ?? $link->linked_id),
        );
    }

    private function keepNotes(RecordLink $link, StoreRecordLinkRequest $request): void
    {
        if (filled($request->input('notes'))) {
            $link->forceFill(['notes' => $request->input('notes')])->save();
        }
    }

    public function destroy(DestroyRecordLinkRequest $request, string $link): RedirectResponse
    {
        $scoped = RecordLink::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->find($link) ?? throw (new ModelNotFoundException)->setModel(RecordLink::class);

        $label = $scoped->linked_label ?: $scoped->moduleLabel();

        $this->links->unlink($scoped, Auth::user());

        return redirect()
            ->route('records.index')
            ->with('status', sprintf(
                'Unlinked from %s. Nothing was deleted: the files and Record Units of both records are untouched.',
                $label,
            ));
    }

    /**
     * Records in the active company that can be linked to the source record.
     *
     * Off the search service's scoped builder, so confidential Records are
     * filtered out for a Role that cannot see them rather than merely omitted
     * from the markup — the same rule the register applies.
     */
    private function candidates(Record $source, string $search): Collection
    {
        return $this->search->scoped()
            ->where('id', '!=', $source->getKey())
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('document_reference', 'like', '%'.$search.'%')
                    ->orWhere('external_reference_number', 'like', '%'.$search.'%')
                    ->orWhere('source', 'like', '%'.$search.'%')
            ))
            ->with('index')
            ->orderByDesc('document_reference')
            ->orderBy('id')
            ->limit(self::SEARCH_LIMIT)
            ->get();
    }

    /**
     * The picker filters client-side, so the candidate list ships with the modal.
     * It stays capped so one page load cannot turn into a full dump of the register.
     *
     * @return array<int, array<string, string>>
     */
    private function candidatePayload(Record $source): array
    {
        return $this->candidates($source, '')
            ->map(fn (Record $candidate): array => [
                'id' => (string) $candidate->id,
                'title' => $candidate->title,
                'documentReference' => (string) $candidate->document_reference,
                'indexLabel' => $candidate->index === null
                    ? ''
                    : sprintf('%s — %s', $candidate->index->code, $candidate->index->name),
            ])
            ->all();
    }

    /**
     * The ids the source record already links to, so the picker can show them as taken.
     *
     * @return array<int, string>
     */
    private function linkedIds(Record $source): array
    {
        return RecordLink::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->where('record_id', $source->getKey())
            ->where('linked_type', Record::class)
            ->pluck('linked_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
 * The link target arrives as a submitted id rather than a route parameter, so it
     * is resolved here under the active company. A target belonging to another
     * company is a 404, so its existence is never confirmed.
     *
     * The same confidentiality rule the picker itself applies is applied to the
     * written target. The candidate list reads through RecordSearchService::scoped()
     * and withholds confidential records, but that only governs what the dialog
     * offers — it does nothing about an id submitted directly. The ordo.record
     * middleware does not help either, because it resolves the {record} route
     * parameter, which is the source of the link, not its target.
     *
     * Without this the reference below would be echoed back in the flash message
     * and persisted as linked_label, so a role that cannot see a confidential
     * record could still read its Document Reference by posting its id.
     */
    private function findScopedRecord(string $record): Record
    {
        return Record::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->when(! Gate::allows('viewConfidential'), fn (Builder $query) => $query->where('is_confidential', false))
            ->find($record) ?? throw (new ModelNotFoundException)->setModel(Record::class);
    }
}
