<?php

namespace App\Services\Records;

use App\Enums\CaptureSource;
use App\Enums\FileRole;
use App\Enums\IntakeStatus;
use App\Enums\RecordStatus;
use App\Models\Record;
use App\Models\RecordActivityLog;
use App\Models\RecordFile;
use App\Models\RecordIntakeItem;
use App\Models\RecordLink;
use App\Services\CompanyContext;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use LogicException;

/**
 * Record Unit and storage metering for the active company.
 *
 * Two figures are tracked separately and never blended, because AGENTS.md bills them
 * differently: an officially filed Record consumes 1 Record Unit, while an intake
 * item and every supporting file or link consume storage only.
 *
 * Storage is summed from record_files rather than from the storage_bytes counters on
 * records and intake items. Filing copies an intake item's counter onto the new
 * record without clearing the original, so summing the two columns would count every
 * filed document twice.
 */
class RecordCapacityService
{
    public function __construct(private readonly CompanyContext $context) {}

    /**
     * Record Units consumed, the allocation ceiling, and what is left.
     *
     * An allocation of null means unlimited, and remaining/utilisation are null
     * rather than zero so a dashboard can tell "unlimited" from "fully consumed".
     */
    public function units(): array
    {
        $consumed = (int) Record::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->sum('record_units');

        $allocation = $this->allocation();

        return [
            'consumed' => $consumed,
            'allocation' => $allocation,
            'remaining' => $allocation === null ? null : max(0, $allocation - $consumed),
            'utilisation' => $allocation === null || $allocation === 0 ? null : min(1.0, $consumed / $allocation),
            'unlimited' => $allocation === null,
        ];
    }

    /**
     * The company's Record Unit ceiling, or null when it has not been assigned one.
     */
    public function allocation(): ?int
    {
        $allocation = $this->context->current()->record_unit_allocation;

        return $allocation === null ? null : (int) $allocation;
    }

    /**
     * The company's storage ceiling in bytes, or null when it has not been assigned one.
     */
    public function storageLimit(): ?int
    {
        $limit = $this->context->current()->storage_limit_bytes;

        return $limit === null ? null : (int) $limit;
    }

    /**
     * Record Capacity and Storage as the Repository Usage card needs them: the
     * used figure, the allowed figure, and the share of it in use.
     *
     * Utilisation is null rather than zero when there is no limit on file, so the
     * card can say "unlimited" instead of drawing an empty meter that reads as a
     * company which has used none of a limit it does not have.
     *
     * @return array<string, array{used: int, limit: ?int, percent: ?float}>
     */
    public function usage(?array $units = null, ?array $storage = null): array
    {
        $units ??= $this->units();
        $storage ??= $this->storage();

        $storageLimit = $this->storageLimit();

        $share = static function (int $used, ?int $limit): ?float {
            if ($limit === null || $limit <= 0) {
                return null;
            }

            return max(0.0, min(100.0, round($used / $limit * 100, 2)));
        };

        return [
            'records' => [
                'used' => $units['consumed'],
                'limit' => $units['allocation'],
                'percent' => $share($units['consumed'], $units['allocation']),
            ],
            'storage' => [
                'used' => $storage['total'],
                'limit' => $storageLimit,
                'percent' => $share($storage['total'], $storageLimit),
            ],
        ];
    }

    /**
     * Total bytes on disk, split by owner so a filed document is counted once.
     *
     * A file belongs to a record once filing has re-pointed it; until then it
     * belongs to the intake item that captured it. Both figures come from one
     * conditional aggregate over a single pass of record_files rather than from
     * two separate sums, and the file counts ride along in the same query because
     * they read the same rows under the same scope.
     *
     * The three role counts the Repository Usage card shows are conditional
     * aggregates on this same pass. A prior version is any file whose version
     * number is above 1; the role a file was stored under is what separates a
     * master document from a supporting attachment.
     *
     * @return array{filed: int, intake: int, total: int, files: int, supporting: int, prior_versions: int}
     */
    public function storage(): array
    {
        $row = RecordFile::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(CASE WHEN record_id IS NOT NULL THEN size_bytes ELSE 0 END), 0) as filed,
                COALESCE(SUM(CASE WHEN record_id IS NULL AND record_intake_item_id IS NOT NULL THEN size_bytes ELSE 0 END), 0) as intake,
                COUNT(*) as files,
                COALESCE(SUM(CASE WHEN file_role = ? THEN 1 ELSE 0 END), 0) as supporting,
                COALESCE(SUM(CASE WHEN version > 1 THEN 1 ELSE 0 END), 0) as prior_versions
                SQL, [FileRole::Supporting->value])
            ->toBase()
            ->first();

        $filed = (int) ($row->filed ?? 0);
        $intake = (int) ($row->intake ?? 0);

        return [
            'filed' => $filed,
            'intake' => $intake,
            'total' => $filed + $intake,
            'files' => (int) ($row->files ?? 0),
            'supporting' => (int) ($row->supporting ?? 0),
            'prior_versions' => (int) ($row->prior_versions ?? 0),
        ];
    }

    /**
     * Headline counts for the dashboard.
     *
     * Each side is counted in one conditional aggregate instead of one query per
     * figure. records and record_intake_items are separate tables and so remain
     * separate queries; the file count is taken from the storage read, which has
     * already scanned those rows.
     *
     * @param  array{filed: int, intake: int, total: int, files: int}|null  $storage
     * @return array<string, int>
     */
    public function counts(?array $storage = null): array
    {
        $storage ??= $this->storage();

        // The completeness and activity aggregates are folded into the count the
        // dashboard already runs rather than issued as their own queries: they read
        // the same rows, so a separate pass would cost a query per figure. All of
        // them go through visibleRecords(), so a record the current user may not
        // see is never counted as one they could fix or one they filed.
        $monthStart = now()->startOfMonth();

        $records = $this->visibleRecords()
            ->selectRaw(
                'COUNT(*) as records,
                 COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as active,
                 COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as archived,
                 COALESCE(SUM(CASE WHEN title IS NULL OR title = \'\' THEN 1 ELSE 0 END), 0) as missing_title,
                 COALESCE(SUM(CASE WHEN document_date IS NULL THEN 1 ELSE 0 END), 0) as missing_date,
                 COALESCE(SUM(CASE WHEN source IS NULL OR source = \'\' THEN 1 ELSE 0 END), 0) as missing_source,
                 COALESCE(SUM(CASE WHEN filed_at >= ? THEN 1 ELSE 0 END), 0) as added_this_month,
                 COALESCE(SUM(CASE WHEN updated_at >= ? THEN 1 ELSE 0 END), 0) as updated_this_month',
                [RecordStatus::Filed->value, RecordStatus::Archived->value, $monthStart, $monthStart],
            )
            ->toBase()
            ->first();

        // The intake aggregate also carries the count of uploads that did not come
        // from a person. Every capture source defined today is a human one, so
        // this reads zero until module registration exists; it is computed rather
        // than hardcoded so it becomes correct on its own when a non-upload source
        // is introduced, and so the tile cannot quietly claim a number it has not
        // counted.
        $intake = RecordIntakeItem::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END), 0) as open_items,
                 COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as needs_review,
                 COALESCE(SUM(CASE WHEN capture_source NOT IN (?, ?, ?) THEN 1 ELSE 0 END), 0) as `generated`',
                [
                    IntakeStatus::Filed->value,
                    IntakeStatus::Discarded->value,
                    IntakeStatus::NeedsReview->value,
                    CaptureSource::Upload->value,
                    CaptureSource::MultiUpload->value,
                    CaptureSource::Camera->value,
                ],
            )
            ->toBase()
            ->first();

        return [
            'records' => (int) ($records->records ?? 0),
            'active' => (int) ($records->active ?? 0),
            'archived' => (int) ($records->archived ?? 0),
            'open_intake' => (int) ($intake->open_items ?? 0),
            'needs_review' => (int) ($intake->needs_review ?? 0),
            // Uploads a module registered rather than a person.
            'generated' => (int) ($intake->generated ?? 0),
            'files' => $storage['files'],
            // Completeness of the same register the counts above describe.
            'missing_title' => (int) ($records->missing_title ?? 0),
            'missing_date' => (int) ($records->missing_date ?? 0),
            'missing_source' => (int) ($records->missing_source ?? 0),
            // Movement since the first of this month.
            'added_this_month' => (int) ($records->added_this_month ?? 0),
            'updated_this_month' => (int) ($records->updated_this_month ?? 0),
        ];
    }

    /**
     * Records the current user is allowed to see counted, confidential included.
     */
    private function visibleRecords(): Builder
    {
        $query = Record::query()
            ->where('records.tenant_id', $this->context->tenantId())
            ->where('records.company_id', $this->context->id());

        if (! Gate::allows('viewConfidential')) {
            $query->where('records.is_confidential', false);
        }

        return $query;
    }

    /**
     * Records per classification code, busiest first, for the distribution cards.
     *
     * @return array<int, array{code: string, name: string, records: int, units: int}>
     */
    public function classificationDistribution(): array
    {
        $rows = $this->visibleRecords()
            // Qualified: both tables carry tenant_id and company_id.
            ->join('record_classifications', function ($join): void {
                $join->on('record_classifications.id', '=', 'records.record_classification_id');
            })
            ->groupBy('record_classifications.id', 'record_classifications.code', 'record_classifications.name')
            ->selectRaw('record_classifications.code as code, record_classifications.name as name, COUNT(*) as records, SUM(records.record_units) as units')
            ->orderByDesc('records')
            ->orderBy('record_classifications.code')
            ->get();

        return $rows->map(fn ($row): array => [
            'code' => (string) $row->code,
            'name' => (string) $row->name,
            'records' => (int) $row->records,
            'units' => (int) $row->units,
        ])->all();
    }

    /**
     * The most recent activity-log entries across every record in the company.
     *
     * @return Collection<int, RecordActivityLog>
     */
    public function recentActivity(int $limit = 8)
    {
        return RecordActivityLog::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->with(['user', 'record'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Records per source, busiest first, for the Records by Source card.
     *
     * A record with no source is reported as its own row rather than dropped, so
     * the gap is visible here rather than only in the quality card.
     *
     * @return array<int, array{source: string, records: int}>
     */
    public function sourceDistribution(int $limit = 6): array
    {
        return $this->visibleRecords()
            ->selectRaw('COALESCE(NULLIF(records.source, \'\'), \'(No source)\') as source, COUNT(*) as records')
            ->groupBy('source')
            ->orderByDesc('records')
            ->orderBy('source')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'source' => (string) $row->source,
                'records' => (int) $row->records,
            ])
            ->all();
    }

    /**
     * The most recently filed records, for the Recently Added table.
     *
     * Joined rather than eager loaded. The table shows the Classification, the
     * Index and who filed it, which eager loading would answer with three further
     * queries on top of this one; the dashboard has a query budget, and three
     * columns are not worth three round trips.
     *
     * @return Collection<int, Record>
     */
    public function recentRecords(int $limit = 8)
    {
        return $this->visibleRecords()
            // Left joins: a record whose related row was later removed still has to
            // appear in the table, with those columns empty rather than dropped.
            ->leftJoin('record_classifications', 'record_classifications.id', '=', 'records.record_classification_id')
            ->leftJoin('record_indexes', 'record_indexes.id', '=', 'records.record_index_id')
            ->leftJoin('users', 'users.id', '=', 'records.created_by')
            // records.* is selected explicitly rather than left to a bare
            // addSelect(): with a bare addSelect the query builder has no base
            // column list, so it would select only the four aliases below and the
            // title, source and date would come back null on every row.
            ->select('records.*')
            ->addSelect([
                'record_classifications.code as classification_code',
                'record_classifications.name as classification_name',
                'record_indexes.code as index_code',
                'users.name as created_by_name',
            ])
            ->whereNotNull('records.filed_at')
            ->orderByDesc('records.filed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Records per index code, busiest first, for the Top Record Indexes card.
     *
     * Joined rather than eager loaded: the card shows the index code and name and
     * its count, which three further queries would otherwise answer.
     *
     * @return array<int, array{code: string, name: string, records: int}>
     */
    public function indexDistribution(int $limit = 6): array
    {
        // Only the index's own columns and the count are selected, and all three
        // are in the GROUP BY. Carrying records.* through here — as the
        // recent-records read does, where it needs every column — would break on
        // MySQL's ONLY_FULL_GROUP_BY, which rejects any selected column that is
        // neither grouped nor aggregated. SQLite tolerates it, so this only
        // surfaces against the real driver.
        return $this->visibleRecords()
            ->join('record_indexes', 'record_indexes.id', '=', 'records.record_index_id')
            ->selectRaw('record_indexes.code as index_code, record_indexes.name as index_name, COUNT(*) as records')
            ->groupBy('record_indexes.code', 'record_indexes.name')
            ->orderByDesc('records')
            ->orderBy('record_indexes.code')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($row): array => [
                'code' => (string) $row->index_code,
                'name' => (string) $row->index_name,
                'records' => (int) $row->records,
            ])
            ->all();
    }

    /**
     * Filings per calendar month for the last twelve months, oldest first.
     *
     * Counted from filed_at rather than from the activity trail, because the card
     * is captioned "This month" movement on the KPI row and a trend that mixes in
     * edits, links and archives would not agree with it.
     *
     * Every month in the window is returned, including those with no filings, so
     * the chart always draws a full-width axis rather than compressing its gaps.
     * Months with nothing in them are zero: the alternative is dropping them,
     * which would make a quiet quarter look like a busy one.
     *
     * @return list<array{month: string, label: string, filed: int}>
     */
    public function monthlyActivity(int $months = 12): array
    {
        $since = now()->subMonths($months - 1)->startOfMonth();

        $filed = $this->visibleRecords()
            ->whereNotNull('records.filed_at')
            ->where('records.filed_at', '>=', $since)
            ->toBase()
            // Grouped by the leading Y-m of the timestamp rather than by a
            // Carbon month: toBase() hands back plain rows with string dates, so
            // the string is what there is to group on, and it keeps this free of
            // hydrating a model per filing just to read one field.
            ->get(['records.filed_at'])
            ->groupBy(fn ($row): string => substr((string) $row->filed_at, 0, 7))
            ->map(fn ($group): int => $group->count())
            ->all();

        $series = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $month = now()->subMonths($offset);
            $key = $month->format('Y-m');

            $series[] = [
                'month' => $key,
                'label' => $month->format('M'),
                'filed' => (int) ($filed[$key] ?? 0),
            ];
        }

        return $series;
    }

    /**
     * Cross-module reuse: how many links exist, over how many records, by module.
     *
     * One grouped read answers all three. The linked-record count is a count of
     * distinct record_id over the same rows rather than a second query, and the
     * module grouping is the same rows grouped a different way.
     *
     * @return array{total: int, records: int, by_module: array<string, int>}
     */
    public function links(int $limit = 6): array
    {
        $rows = $this->linksQuery()
            ->toBase()
            ->get(['record_id', 'linked_type', 'linked_label']);

        $byModule = [];

        foreach ($rows as $row) {
            // The label is what the interface names a module by; the raw type is
            // the fallback for a link saved before one was given.
            $name = $row->linked_label ?: $row->linked_type;
            $byModule[$name] = ($byModule[$name] ?? 0) + 1;
        }

        arsort($byModule);

        return [
            'total' => $rows->count(),
            'records' => $rows->pluck('record_id')->unique()->count(),
            'by_module' => array_slice($byModule, 0, $limit, true),
        ];
    }

    /**
     * Cross-module links for the active company.
     */
    private function linksQuery(): Builder
    {
        return RecordLink::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id());
    }

    /**
     * Refuse to consume Record Units the company has not been allocated.
     *
     * Called from filing so the guard actually protects something. With no
     * allocation set this always passes, which is the prototype default.
     */
    public function verifyFilingCapacity(int $unitsWanted = 1): void
    {
        $allocation = $this->allocation();

        if ($allocation === null) {
            return;
        }

        $consumed = (int) Record::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->id())
            ->sum('record_units');

        if ($consumed + $unitsWanted > $allocation) {
            throw new LogicException(sprintf(
                'Filing this document would use %d Record Unit(s) of the %d allocated. No further records can be filed until the allocation is increased.',
                $consumed + $unitsWanted,
                $allocation,
            ));
        }
    }

    /**
     * Everything the dashboard needs, in one scoped read.
     *
     * Storage is read once and handed to counts(), because both figures come
     * from the same pass of record_files and reading it twice would double the
     * dashboard's query cost for no new information.
     *
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $storage = $this->storage();

        return [
            'units' => $this->units(),
            'storage' => $storage,
            'counts' => $this->counts($storage),
            'distribution' => $this->classificationDistribution(),
            'activity' => $this->recentActivity(),
        ];
    }
}
