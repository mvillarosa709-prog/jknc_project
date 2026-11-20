<?php

namespace App\Http\Controllers\Records;

use App\Enums\RecordStatus;
use App\Enums\ReportTrigger;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateReportRequest;
use App\Models\Record;
use App\Models\RecordReportRun;
use App\Services\CompanyContext;
use App\Services\Records\RecordLookups;
use App\Services\Records\RecordSearchService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Formal Records Management outputs.
 *
 * The preview and the CSV are driven by one filter builder, so a download can
 * never disagree with the report the user was looking at when they chose it.
 *
 * Two scoping decisions are worth stating. Company scope and the confidential
 * rule come from the shared search service rather than from a query written here,
 * which is what keeps a report from reporting on records its reader could not
 * open in the register. And archived records are excluded: the register covers
 * them with their own status, so a management report reads the active set.
 *
 * The same builder serves three things: the preview on screen, the CSV that is
 * downloaded, and the figures frozen onto a history entry when a report is taken
 * onward. One builder behind all three is why the history can be trusted to
 * describe the report that was actually issued.
 */
class RecordReportController extends Controller
{
    public function __construct(
        private readonly RecordSearchService $search,
        private readonly RecordLookups $lookups,
        private readonly CompanyContext $context,
    ) {}

    public function index(): View
    {
        $classifications = $this->lookups->classifications();

        return view('records.reports', [
            'title' => 'Reports',
            'reportTypes' => ReportType::cases(),
            'classifications' => $classifications,
            'indexes' => $this->lookups->indexes(),
            'sources' => $this->lookups->sources(),
            // The shared lookup groups indexes by Classification *id*; the builder
            // narrows its Index list by Classification *code*, which is what the
            // filter sends. Keying a second map by code keeps that translation in
            // one place instead of in the browser.
            'indexesByCode' => collect($this->lookups->indexesByClassification())
                ->mapWithKeys(function (Collection $group) use ($classifications): array {
                    $code = $classifications
                        ->firstWhere('id', $group->first()->record_classification_id)?->code;

                    return $code === null ? [] : [$code => $group->values()];
                })
                ->all(),
            'generateUrl' => route('records.reports.generate'),
            'exportUrl' => route('records.reports.export'),
            'storeRunUrl' => route('records.reports.runs.store'),
            'historyUrl' => route('records.reports.history'),
        ]);
    }

    /**
     * Record a report this company has taken onward.
     *
     * Called when the reader presents or downloads, not when a preview is built:
     * configuring a report is not the same act as issuing one, and a history
     * listing every preview ever drawn would be a log of clicks rather than a
     * list of outputs.
     *
     * The figures are recomputed here from the same filter builder the preview
     * used, rather than accepted from the browser, so the entry cannot claim a
     * record count the server did not produce. The user_id is nullable because
     * the prototype has no sign-in yet — there is a session company here, but no
     * session user to attribute the run to.
     */
    public function store(GenerateReportRequest $request): JsonResponse
    {
        $filters = $request->filters();

        RecordReportRun::query()->create([
            'tenant_id' => $request->tenantId(),
            'company_id' => $request->companyId(),
            'user_id' => $request->user()?->id,
            'type' => $request->reportType()->value,
            'trigger' => ($request->trigger() ?? ReportTrigger::Present)->value,
            'filters' => $filters,
            'metrics' => $this->metrics($filters),
            'comments' => $request->comments(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * This company's report history, as a fragment for the modal to inject.
     *
     * Scoped by the resolved company rather than by anything in the payload, and
     * newest first, capped: the list is a record of recent outputs, not an
     * archive to be paged through.
     */
    public function history(): View
    {
        return view('records.partials.report-history', [
            'runs' => RecordReportRun::query()
                ->where('tenant_id', $this->context->tenantId())
                ->where('company_id', $this->context->id())
                ->latest('id')
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * The preview, rendered as a fragment for the builder to inject.
     */
    public function generate(GenerateReportRequest $request): View
    {
        return view('records.partials.report-preview', [
            'type' => $request->reportType(),
            'filters' => $request->filters(),
            'metrics' => $this->metrics($request->filters()),
        ]);
    }

    /**
     * Stream the filtered records as CSV, matching the preview exactly.
     */
    public function export(Request $request): Response
    {
        $filters = $this->filtersFrom($request);
        $type = $this->reportTypeFrom($request);

        if ($request->query('format') === 'pdf') {
            $records = $this->filtered($filters)->get();

            return Pdf::loadView('records.pdf.report', [
                'type' => $type,
                'filters' => $filters,
                'metrics' => $this->metrics($filters),
                'records' => $records,
                'comments' => mb_substr((string) $request->query('comments', ''), 0, 5000),
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape')->download(sprintf(
                'ORDO_%s_%s.pdf',
                str_replace('-', '_', strtoupper($type->slug())),
                now()->format('Y-m-d'),
            ));
        }

        return response()->stream(function () use ($filters): void {
            echo $this->csvLine([
                'Doc Ref', 'Record ID', 'Document Title', 'Classification', 'Index',
                'Source', 'Document Date', 'Amount', 'Currency', 'Status', 'Filed On',
            ]);

            $this->filtered($filters)
                ->chunk(200, function (Collection $chunk): void {
                    foreach ($chunk as $record) {
                        echo $this->csvLine($this->exportRow($record));
                    }
                });
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="ordo-%s.csv"', $type->slug()),
        ]);
    }

    /**
     * The records a set of filters covers.
     *
     * @param  array<string, string>  $filters
     */
    private function filtered(array $filters): Builder
    {
        return $this->search->scoped()
            ->where('status', '!=', RecordStatus::Archived)
            // Each filter is only applied when it has a value: a blank
            // whereHas('code', '') would match nothing and silently empty the
            // report rather than meaning "all".
            ->when($filters['classification'] !== '', fn (Builder $query) => $query->whereHas(
                'classification',
                fn (Builder $inner) => $inner->where('code', $filters['classification']),
            ))
            ->when($filters['index'] !== '', fn (Builder $query) => $query->whereHas(
                'index',
                fn (Builder $inner) => $inner->where('code', $filters['index']),
            ))
            ->when($filters['source'] !== '', fn (Builder $query) => $query->where('source', $filters['source']))
            ->when($filters['year'] !== '', fn (Builder $query) => $query->whereYear('document_date', (int) $filters['year']))
            ->with(['classification', 'index'])
            ->orderByDesc('document_reference')
            ->latest('id');
    }

    /**
     * The headline figures the preview leads with.
     *
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function metrics(array $filters): array
    {
        $records = $this->filtered($filters)->get();

        // A record with no source still counts toward "Records Included", but an
        // absent value is not a source, so it is left out of the distinct count.
        $sources = $records
            ->map(fn (Record $record): string => trim((string) $record->source))
            ->filter(fn (string $source): bool => $source !== '')
            ->unique();

        $distribution = $records
            ->filter(fn (Record $record): bool => $record->classification !== null)
            ->groupBy(fn (Record $record): string => (string) $record->classification->code)
            ->map(fn (Collection $group): array => [
                'code' => $group->first()->classification->code,
                'name' => $group->first()->classification->name,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values();

        return [
            'records' => $records->count(),
            'classifications' => $distribution->count(),
            'sources' => $sources->count(),
            'year' => $filters['year'] !== '' ? $filters['year'] : 'All',
            'distribution' => $distribution,
        ];
    }

    /**
     * @param  array<string, string>  $filters
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
            (string) $record->status->label(),
            $record->filed_at?->format('Y-m-d H:i') ?? '',
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

    /**
     * Filters from a GET query string, normalised so the export honours exactly
     * the same values the preview was built from.
     *
     * @return array<string, string>
     */
    private function filtersFrom(Request $request): array
    {
        return [
            'classification' => trim((string) $request->query('classification', '')),
            'index' => trim((string) $request->query('index', '')),
            'source' => trim((string) $request->query('source', '')),
            'year' => trim((string) $request->query('year', '')),
        ];
    }

    private function reportTypeFrom(Request $request): ReportType
    {
        return ReportType::tryFrom((string) $request->query('type', 'inventory')) ?? ReportType::Inventory;
    }
}
