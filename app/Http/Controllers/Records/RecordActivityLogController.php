<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Models\RecordActivityLog;
use App\Services\CompanyContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The append-only audit trail.
 *
 * Every query is scoped to the active company. The table rejects updates and
 * deletes at the model level, so the trail can only ever grow — and the one
 * thing written here is an entry saying the trail was exported, because a trail
 * that cannot say it was read is a trail with a hole in it.
 */
class RecordActivityLogController extends Controller
{
    private const PAGE_SIZE = 30;

    public function __construct(private readonly CompanyContext $context) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);

        return view('records.logs', [
            'title' => 'Audit Log',
            // The table resolves each row's subject and author, so all three are
            // loaded up front. Column lists are trimmed to what the row renders:
            // the audit trail grows without limit, so a per-row read here would
            // make this the most expensive page in the app.
            'entries' => $this->filtered($filters)
                ->with([
                    'record:id,title,document_reference',
                    'intakeItem:id,title',
                    'user:id,name',
                ])
                ->paginate(self::PAGE_SIZE)->withQueryString(),
            'actions' => $this->actions(),
            'filters' => $filters,
            'drawerEntries' => $this->drawerEntries($filters),
            'csvUrl' => route('records.logs.export', array_merge($filters, ['format' => 'csv'])),
            'pdfUrl' => route('records.logs.export', array_merge($filters, ['format' => 'pdf'])),
        ]);
    }

    /**
     * Stream the filtered trail as CSV, or as a JSON compliance envelope.
     *
     * The JSON form carries who produced it and when, because a compliance export
     * is evidence and has to be attributable on its own — a CSV of a trail says
     * nothing about whose trail it is.
     */
    public function export(Request $request): Response
    {
        $filters = $this->filters($request);
        $format = in_array($request->query('format'), ['json', 'pdf'], true)
            ? (string) $request->query('format')
            : 'csv';

        // How many entries existed before the export was recorded. This is what
        // was actually taken out of the trail.
        $existing = $this->filtered($filters)->count();

        // Written before the transfer starts, not after it finishes.
        //
        // The trail is append-only, so the fact that someone pulled it has to
        // survive even if they abandon the download half way. That is worth more
        // than tidiness: a trail that cannot say it was read is a trail with a
        // hole in it.
        $this->recordExport($format, $existing, $filters);

        // Counted again rather than incremented, because the entry just written
        // lands in the file only when it happens to match the filters. Pulling
        // the whole trail includes the record of the pull; pulling one action
        // filters it straight back out, and a header that overcounts by one is
        // the kind of thing an auditor notices before anything else.
        if ($format === 'pdf') {
            $entries = $this->filtered($filters)
                ->with(['record', 'intakeItem', 'user'])
                ->get();

            return Pdf::loadView('records.pdf.audit-log', [
                'entries' => $entries,
                'filters' => $filters,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape')->download('ORDO_Audit_Log_'.now()->format('Y-m-d').'.pdf');
        }

        return $format === 'json'
            ? $this->json($filters, $this->filtered($filters)->count())
            : $this->csv($filters);
    }

    /**
     * Note in the trail that the trail was taken out of it.
     *
     * @param  array<string, mixed>  $filters
     */
    private function recordExport(string $format, int $count, array $filters): void
    {
        RecordActivityLog::query()->create([
            'tenant_id' => $this->context->tenantId(),
            'company_id' => $this->context->id(),
            'record_id' => null,
            'record_intake_item_id' => null,
            'user_id' => Auth::id(),
            'action' => 'audit.exported',
            'description' => sprintf(
                'Audit trail exported as %s (%d entries).',
                strtoupper($format),
                $count,
            ),
            'properties' => [
                'format' => $format,
                'entry_count' => $count,
                'filters' => array_filter($filters),
            ],
            'ip_address' => request()?->ip(),
        ]);
    }

    private function csv(array $filters): StreamedResponse
    {
        return response()->stream(function () use ($filters): void {
            echo $this->csvLine([
                'Log ID', 'Timestamp', 'Action', 'Description',
                'Subject Type', 'Subject', 'Doc Ref', 'User', 'IP Address', 'Properties',
            ]);

            $this->filtered($filters)
                ->with(['record', 'intakeItem', 'user'])
                ->chunk(200, function (Collection $chunk): void {
                    foreach ($chunk as $entry) {
                        echo $this->csvLine($this->exportRow($entry));
                    }
                });
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="ordo-records-audit-log.csv"',
        ]);
    }

    /**
     * Stream the trail as one JSON document.
     *
     * Built by hand rather than handed to response()->json(), because that one
     * takes a collection and this cannot: the trail grows without limit, and
     * loading a whole export into memory to serialise it is how an export
     * becomes the one request that takes the server down. The CSV beside it
     * already chunks, and this now matches it.
     *
     * @param  array<string, mixed>  $filters
     */
    private function json(array $filters, int $count): StreamedResponse
    {
        $meta = [
            'export' => 'ordo-records-audit-log',
            'generated_at' => now()->toIso8601String(),
            'generated_by' => Auth::user()?->name,
            'tenant_id' => (string) $this->context->tenantId(),
            'company_id' => (string) $this->context->id(),
            'company_name' => (string) ($this->context->current()->name ?? ''),
            'filters' => array_filter($filters),
            'entry_count' => $count,
        ];

        return response()->stream(function () use ($filters, $meta): void {
            echo '{"meta":'.$this->encode($meta).',"entries":[';

            $first = true;

            $this->filtered($filters)
                ->with(['record:id,title,document_reference', 'intakeItem:id,title', 'user:id,name'])
                ->chunk(200, function (Collection $chunk) use (&$first): void {
                    foreach ($chunk as $entry) {
                        // The separator is written by hand, so chunk boundaries
                        // are the only thing that can put a comma in the wrong
                        // place.
                        echo ($first ? '' : ',').$this->encode($this->entry($entry));

                        $first = false;
                    }
                });

            echo ']}';
        }, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="ordo-records-audit-log.json"',
        ]);
    }

    /**
     * One trail entry as the envelope shapes it.
     *
     * @return array<string, mixed>
     */
    private function entry(RecordActivityLog $entry): array
    {
        return [
            'log_id' => (string) $entry->id,
            'timestamp' => $entry->created_at?->format('Y-m-d H:i:s'),
            'action' => $entry->action,
            'description' => $entry->description,
            'subject_type' => $entry->subjectType(),
            'subject' => $entry->subjectLabel(),
            'document_reference' => $entry->record?->document_reference,
            'user' => $entry->user?->name ?? 'System',
            'ip_address' => $entry->ip_address,
            'properties' => $entry->properties,
        ];
    }

    /**
     * Encode one fragment of the document.
     *
     * Slashes and unicode are left readable on purpose. This file is read by
     * people as well as scripts — a document reference full of backslashes is
     * hard to check by eye against a register — and nothing here needs escaping
     * that escapes it cannot safely render inside a JSON string.
     *
     * @param  array<string, mixed>  $value
     */
    private function encode(array $value): string
    {
        return (string) json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
    }

    /**
     * The rows the properties drawer renders, shipped with the page so opening a
     * row needs no request.
     *
     * @return array<int, array<string, mixed>>
     */
    private function drawerEntries(array $filters): array
    {
        return $this->filtered($filters)
            ->get(['record_activity_logs.*', 'record_activity_logs.properties'])
            ->map(fn (RecordActivityLog $entry): array => [
                'id' => (string) $entry->id,
                'action' => $entry->action,
                'description' => (string) $entry->description,
                'createdAt' => $entry->created_at?->format('Y-m-d H:i'),
                'properties' => $entry->properties ?? [],
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'action' => trim((string) $request->query('action', '')),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filtered(array $filters): Builder
    {
        return RecordActivityLog::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            // The trail names its subject, and for a filed record that subject is
            // the title and the Document Reference. Routing it through the register's
            // confidentiality rule is what stops the audit log from being a way to
            // read what the register refuses to show: the export below is the same
            // query, so without this the CSV and JSON would carry both fields too.
            //
            // A row carries two independent subject slots and subjectLabel() renders
            // whichever it finds, so each slot is filtered on its own. The intake
            // slot matters as much as the record slot: an intake item is the same
            // document before it was filed, so withholding only the post-filing
            // entries would still hand the title over through the pre-filing ones.
            //
            // Both slots pass vacuously when empty, so a row about a settings change
            // or an export — no subject at all — is kept. Dropping those would
            // quietly rewrite the history the page exists to show.
            ->when(! Gate::allows('viewConfidential'), fn (Builder $query) => $query->where(
                fn (Builder $keep) => $keep
                    // Slot one: the filed record.
                    ->where(fn (Builder $subject) => $subject
                        ->whereNull('record_id')
                        ->orWhereHas('record', fn (Builder $record) => $record->where('is_confidential', false)),
                    )
                    // Slot two: the intake item, which may since have been filed
                    // into a confidential record.
                    ->where(fn (Builder $subject) => $subject
                        ->whereDoesntHave('intakeItem.record')
                        ->orWhereHas('intakeItem.record', fn (Builder $record) => $record->where('is_confidential', false)),
                    ),
            ))
            ->when($filters['q'] !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner
                    ->where('description', 'like', '%'.$filters['q'].'%')
                    ->orWhere('action', 'like', '%'.$filters['q'].'%')
            ))
            ->when($filters['action'] !== '', fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->where('created_at', '<=', $filters['to'].' 23:59:59'))
            ->latest('id');
    }

    /**
     * Every action type this company has actually produced, so the filter cannot
     * drift away from the data.
     *
     * @return array<int, string>
     */
    private function actions(): array
    {
        return RecordActivityLog::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();
    }

    /**
     * A log row resolves to either a filed record or the intake item it captured.
     *
     * @return array<int, string>
     */
    private function exportRow(RecordActivityLog $entry): array
    {
        return [
            (string) $entry->created_at?->format('Y-m-d H:i:s'),
            $entry->action,
            (string) $entry->description,
            $entry->subjectType(),
            $entry->subjectLabel(),
            (string) ($entry->record?->document_reference ?? ''),
            (string) ($entry->user?->name ?? 'System'),
            (string) ($entry->ip_address ?? ''),
            $entry->properties === null ? '' : (string) json_encode($entry->properties),
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
