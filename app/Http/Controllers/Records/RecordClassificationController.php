<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClassificationRequest;
use App\Http\Requests\StoreIndexRequest;
use App\Models\RecordClassification;
use App\Models\RecordIndex;
use App\Services\CompanyContext;
use App\Services\Records\RecordLookups;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The official Classification and Index scheme.
 *
 * Every read and every write is scoped to the active company. Nothing here
 * accepts a tenant or company from the request: the context resolves them, so a
 * hand-crafted payload cannot touch another company's scheme.
 *
 * Both writes forget the cached lookups afterwards. Without that, a code created
 * here would be missing from the register's and the queue's dropdowns for as long
 * as the cache entry lived, which is the screen the user reaches next.
 */
class RecordClassificationController extends Controller
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly RecordLookups $lookups,
    ) {}

    /**
     * The scheme as a two-level tree: every Classification with its Indexes.
     *
     * Inactive rows are shown rather than hidden, with the status spelled out, so
     * a retired code that old Records still reference stays visible instead of
     * silently vanishing from the structure.
     */
    public function index(): View
    {
        Gate::authorize('viewAny', RecordClassification::class);

        $tenantId = $this->context->tenantId();
        $companyId = $this->context->id();

        $classifications = RecordClassification::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->with(['indexes' => fn ($query) => $query->orderBy('sort_order')->orderBy('code')])
            ->orderBy('code')
            ->get();

        return view('records.classifications', [
            'title' => 'Classifications',
            'classifications' => $classifications,
            'scheme' => $classifications->map(fn (RecordClassification $classification): array => [
                'code' => $classification->code,
                'name' => $classification->name,
                // Everything a search should match in this block: the
                // Classification's own fields plus every Index beneath it. The
                // block is shown or hidden as a unit so a hit on an Index Code
                // keeps its parent Classification on screen.
                'text' => $classification->searchText(),
            ])->values()->all(),
            'canManage' => Gate::allows('manageScheme'),
        ]);
    }

    public function storeClassification(StoreClassificationRequest $request): RedirectResponse
    {
        $classification = RecordClassification::query()->create([
            'tenant_id' => $request->tenantId(),
            'company_id' => $request->companyId(),
            'code' => trim((string) $request->input('code')),
            'name' => trim((string) $request->input('name')),
            'description' => $request->input('description'),
            'is_active' => true,
        ]);

        $this->forgetLookups();

        return back()
            ->with('setup_tab', 'classifications')
            ->with('status', sprintf('Classification %s was added.', $classification->code));
    }

    /**
     * Add an Index beneath an existing Classification.
     *
     * The parent is named by its code and resolved inside the active company. A
     * code from another company does not resolve, so there is no route to
     * attaching an Index to a Classification this user cannot see.
     */
    public function storeIndex(StoreIndexRequest $request, string $classification): RedirectResponse
    {
        $parent = $request->parent();

        if ($parent === null || $parent->code !== $classification) {
            throw (new ModelNotFoundException)->setModel(RecordClassification::class);
        }

        $sortOrder = (int) RecordIndex::query()
            ->where('record_classification_id', $parent->getKey())
            ->max('sort_order');

        $index = RecordIndex::query()->create([
            'tenant_id' => $parent->tenant_id,
            'company_id' => $parent->company_id,
            'record_classification_id' => $parent->getKey(),
            'code' => trim((string) $request->input('code')),
            'name' => trim((string) $request->input('name')),
            'description' => $request->input('description'),
            // New indexes land after the existing ones rather than jumping to
            // the front of the drawer every time one is added.
            'sort_order' => $sortOrder + 1,
            'is_active' => true,
        ]);

        $this->forgetLookups();

        return back()
            ->with('setup_tab', 'classifications')
            ->with('status', sprintf('Index %s was added under %s.', $request->input('code'), $parent->code));
    }

    /**
     * Drop this company's cached scheme lookups after a write.
     *
     * Called straight after the create rather than through afterCommit: there is
     * no surrounding transaction here, and dropping the cache early is harmless —
     * a stale entry skipped is one query, whereas a missed invalidation would hide
     * the code the user just created from the dropdowns they reach next.
     */
    private function forgetLookups(): void
    {
        $this->lookups->forget();
    }
}
