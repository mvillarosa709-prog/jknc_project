<?php

namespace App\Http\Controllers\Records;

use App\Enums\ReferenceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRecordFieldRequest;
use App\Models\RecordField;
use Illuminate\Http\RedirectResponse;

/**
 * The custom Record field registry, as maintained from the setup modal.
 *
 * Writing a field registers the company's intent to capture it. It is deliberately
 * not wired into the filing form in this phase: the registry stands on its own so
 * the scheme can be agreed and reviewed without the working filing flow moving.
 */
class RecordFieldController extends Controller
{
    public function store(StoreRecordFieldRequest $request): RedirectResponse
    {
        // New fields land after the existing ones rather than jumping to the top
        // of the table every time one is added.
        $sortOrder = (int) RecordField::query()
            ->where('tenant_id', $request->tenantId())
            ->where('company_id', $request->companyId())
            ->max('sort_order');

        RecordField::query()->create([
            'tenant_id' => $request->tenantId(),
            'company_id' => $request->companyId(),
            'name' => trim((string) $request->input('name')),
            'type' => (string) $request->input('type'),
            // An unchecked checkbox is simply absent from the payload, so the
            // default is applied rather than coerced.
            'is_filterable' => $request->boolean('is_filterable'),
            'status' => ReferenceStatus::Active->value,
            'sort_order' => $sortOrder + 1,
        ]);

        return back()
            ->with('setup_tab', 'fields')
            ->with('status', sprintf('Record field %s was added.', $request->input('name')));
    }
}
