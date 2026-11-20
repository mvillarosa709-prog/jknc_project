<?php

namespace App\Http\Requests;

use App\Enums\RecordFieldType;
use App\Policies\RecordClassificationPolicy;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registering a custom Record field for the active company.
 *
 * Authorised through the same `manageScheme` ability that guards the
 * Classification scheme, because registering a field is the same kind of
 * administrative act: it changes what the company intends to capture.
 *
 * The tenant and company are never read from the request; they come from the
 * active context, so a hand-crafted payload cannot register a field against
 * another company.
 */
class StoreRecordFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RecordClassificationPolicy::class)->create($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                // Scoped uniqueness, matching the database's per-company key: the
                // same field name may exist in two companies without colliding.
                Rule::unique('record_fields', 'name')
                    ->where('tenant_id', $this->tenantId())
                    ->where('company_id', $this->companyId()),
            ],
            // Validated against the enum rather than a free list, so the stored
            // value is always one the application knows how to label.
            'type' => ['required', 'string', Rule::in(RecordFieldType::options())],
            'is_filterable' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This company already has a Record field with that name.',
            'type.in' => 'Choose one of the supported Record field types.',
        ];
    }

    public function tenantId(): string
    {
        return app(CompanyContext::class)->tenantId();
    }

    public function companyId(): string
    {
        return app(CompanyContext::class)->id();
    }
}
