<?php

namespace App\Models;

use App\Enums\ReportTrigger;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One report this company took onward.
 *
 * A run is a record of an output, not a record of the report: the filters and
 * the headline figures are stored exactly as they were when the report was
 * presented or downloaded, so history reports what left the system rather than
 * re-running the numbers against a register that has since moved on.
 *
 * Nothing in the app edits or removes a run, so the guards that make
 * RecordActivityLog append-only are deliberately absent here: a report run has
 * no subject record whose history depends on it surviving an edit.
 */
class RecordReportRun extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'user_id',
        'type',
        'trigger',
        'filters',
        'metrics',
        'comments',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'metrics' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reportType(): ReportType
    {
        // Falls back to Inventory rather than throwing. The column is written
        // from a validated enum, so an unreadable value means the row predates a
        // case being renamed, and history should still render the entry.
        return ReportType::tryFrom((string) $this->type) ?? ReportType::Inventory;
    }

    public function triggerType(): ReportTrigger
    {
        return ReportTrigger::tryFrom((string) $this->trigger) ?? ReportTrigger::Present;
    }

    /**
     * The filters this run was built from, normalised to the four the builder
     * sends. A row written before a filter existed reads as an empty string for
     * it rather than as a missing key, so the history view never has to ask
     * whether a key is there.
     *
     * @return array<string, string>
     */
    public function filterSet(): array
    {
        $filters = (array) ($this->filters ?? []);

        return [
            'classification' => trim((string) ($filters['classification'] ?? '')),
            'index' => trim((string) ($filters['index'] ?? '')),
            'source' => trim((string) ($filters['source'] ?? '')),
            'year' => trim((string) ($filters['year'] ?? '')),
        ];
    }

    /**
     * The filters as they read on one line of the history list.
     *
     * An unused filter is described rather than omitted, so "All indexes" is
     * visibly a deliberate choice and not a filter the list forgot to show.
     */
    public function filterSummary(): string
    {
        $labels = [
            'classification' => 'Classification',
            'index' => 'Index',
            'source' => 'Source',
            'year' => 'Year',
        ];

        $parts = [];

        foreach ($this->filterSet() as $key => $value) {
            $parts[] = $value === ''
                ? sprintf('All %s', lcfirst($labels[$key]))
                : sprintf('%s %s', $labels[$key], $value);
        }

        return implode(' · ', $parts);
    }

    /**
     * The headline figures, normalised the way the preview shows them.
     *
     * @return array<string, mixed>
     */
    public function figures(): array
    {
        $metrics = (array) ($this->metrics ?? []);

        return [
            'records' => (int) ($metrics['records'] ?? 0),
            'classifications' => (int) ($metrics['classifications'] ?? 0),
            'sources' => (int) ($metrics['sources'] ?? 0),
            'year' => (string) ($metrics['year'] ?? 'All'),
        ];
    }
}
