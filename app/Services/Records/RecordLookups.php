<?php

namespace App\Services\Records;

use App\Models\RecordClassification;
use App\Models\RecordIndex;
use App\Models\RecordSource;
use App\Services\CompanyContext;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Shared read-only lookups for the classification and index scheme.
 *
 * Both the intake queue and the master register offer the same Classification to
 * Index relationship, so the queries live here rather than being repeated per controller.
 *
 * Results are cached per tenant and company. These are small reference tables that
 * the scheme seeds rather than the interface edits, and every register, queue and
 * drawer page load reads all four of them, so caching removes a fixed cost from
 * every page. Each lookup keeps its own cache entry and its own SQL ordering: the
 * flat index list is sorted by code while the grouped one is sorted by sort order,
 * and merging them into a single entry would silently reorder one of the two.
 *
 * Only raw attribute rows are cached and the models are rebuilt from them, so no
 * hydrated model ever leaves this class and a cached entry can never hold a value
 * belonging to another company.
 */
class RecordLookups
{
    /**
     * The cache keys this class owns, relative to a tenant and company.
     *
     * @var array<int, string>
     */
    private const KEYS = [
        'classifications',
        'indexes',
        'indexes_by_classification',
        'sources',
    ];

    public function __construct(private readonly CompanyContext $context) {}

    /**
     * @return Collection<int, RecordClassification>
     */
    public function classifications(): Collection
    {
        return $this->remember(
            'classifications',
            RecordClassification::class,
            fn (): Collection => RecordClassification::query()
                ->where('tenant_id', $this->context->tenantId())
                ->where('company_id', $this->context->id())
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
        );
    }

    /**
     * Active indexes grouped by their classification, keyed by classification id.
     *
     * @return array<string, Collection<int, RecordIndex>>
     */
    public function indexesByClassification(): array
    {
        return $this->remember(
            'indexes_by_classification',
            RecordIndex::class,
            fn (): Collection => RecordIndex::query()
                ->where('tenant_id', $this->context->tenantId())
                ->where('company_id', $this->context->id())
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
        )->groupBy('record_classification_id')->all();
    }

    /**
     * Every active index for the company, flattened, for filtering by index code alone.
     *
     * @return Collection<int, RecordIndex>
     */
    public function indexes(): Collection
    {
        return $this->remember(
            'indexes',
            RecordIndex::class,
            fn (): Collection => RecordIndex::query()
                ->where('tenant_id', $this->context->tenantId())
                ->where('company_id', $this->context->id())
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
        );
    }

    /**
     * @return Collection<int, RecordSource>
     */
    public function sources(): Collection
    {
        return $this->remember(
            'sources',
            RecordSource::class,
            fn (): Collection => RecordSource::query()
                ->active()
                ->ordered()
                ->where('tenant_id', $this->context->tenantId())
                ->where('company_id', $this->context->id())
                ->get(),
        );
    }

    /**
     * Sources offered by filing and metadata forms.
     *
     * "Other" is represented by the special "Others" option plus its text
     * input, so exposing the seeded "Other" row as a second option is redundant.
     *
     * @return Collection<int, RecordSource>
     */
    public function documentSources(): Collection
    {
        return $this->sources()
            ->reject(fn (RecordSource $source): bool => strcasecmp($source->name, 'Other') === 0)
            ->values();
    }

    /**
     * Drop this company's cached lookups.
     *
     * Callers that change the scheme — the seeder, and any future editor for
     * classifications or indexes — must call this, otherwise the next request
     * would serve a stale dropdown until the entry expired.
     */
    public function forget(): void
    {
        self::forgetFor($this->context->tenantId(), $this->context->id());
    }

    /**
     * Drop one company's cached lookups without needing a request-scoped context.
     *
     * The seeder runs outside a session, so it cannot resolve an active company
     * and has to name the tenant and company it is about to change.
     */
    public static function forgetFor(string $tenantId, string $companyId): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget(self::cacheKeyFor($tenantId, $companyId, $key));
        }
    }

    /**
     * Run a lookup once per tenant, company and cache window.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  Closure(): Collection<int, TModel>  $fetch
     * @return Collection<int, TModel>
     */
    private function remember(string $key, string $model, Closure $fetch): Collection
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = Cache::remember(
            $this->cacheKey($key),
            (int) config('ordo.lookup_cache_ttl', 300),
            fn (): array => $fetch()
                ->map(fn (Model $row): array => $row->getAttributes())
                ->all(),
        );

        $prototype = new $model;

        // The cached rows are rebuilt into models here. They must be mapped over
        // rather than over a fresh collection: mapping a new, empty collection
        // never invokes the callback, which silently yields no lookups at all.
        return $prototype->newCollection()->make(
            array_map(fn (array $row): Model => $prototype->newFromBuilder($row), $rows),
        );
    }

    /**
     * The cache key for one lookup, scoped to the active tenant and company so no
     * two companies can ever read each other's scheme.
     */
    private function cacheKey(string $key): string
    {
        return self::cacheKeyFor($this->context->tenantId(), $this->context->id(), $key);
    }

    private static function cacheKeyFor(string $tenantId, string $companyId, string $key): string
    {
        return sprintf('ordo.lookups.%s.%s.%s', $tenantId, $companyId, $key);
    }
}
