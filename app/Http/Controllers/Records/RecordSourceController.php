<?php

namespace App\Http\Controllers\Records;

use App\Enums\ReferenceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSourceRequest;
use App\Models\RecordSource;
use App\Services\CompanyContext;
use App\Services\Records\RecordLookups;
use Illuminate\Http\RedirectResponse;

/**
 * Sources, as maintained from the setup modal.
 *
 * Reading happens through RecordSetupController, which serves the tab fragment.
 * This controller owns the one write.
 *
 * The cached lookups are dropped afterwards for the same reason the scheme does:
 * a Source added here is otherwise missing from the filing form's Source dropdown
 * for as long as the cache entry lives, which is the very next screen the user
 * reaches.
 */
class RecordSourceController extends Controller
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly RecordLookups $lookups,
    ) {}

    public function store(StoreSourceRequest $request): RedirectResponse
    {
        // New sources land after the existing ones rather than jumping to the top
        // of the table every time one is added, matching the Index behaviour.
        $sortOrder = (int) RecordSource::query()
            ->where('tenant_id', $request->tenantId())
            ->where('company_id', $request->companyId())
            ->max('sort_order');

        RecordSource::query()->create([
            'tenant_id' => $request->tenantId(),
            'company_id' => $request->companyId(),
            'name' => trim((string) $request->input('name')),
            'type' => (string) $request->input('type'),
            'status' => ReferenceStatus::Active->value,
            'sort_order' => $sortOrder + 1,
        ]);

        $this->lookups->forget();

        return back()
            ->with('setup_tab', 'sources')
            ->with('status', sprintf('Source %s was added.', $request->input('name')));
    }
}
