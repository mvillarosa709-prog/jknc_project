<?php

namespace App\Http\Requests;

use App\Models\RecordClassification;
use App\Policies\RecordClassificationPolicy;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding an Index beneath a Classification.
 *
 * Uniqueness is scoped to the parent Classification rather than the company,
 * because the unique index on record_indexes is
 * (tenant_id, company_id, record_classification_id, code). Two Classifications
 * may both hold an index called "301-6"-style codes without colliding.
 *
 * The parent is resolved by code inside the active company by the controller, so
 * a code belonging to another company is simply not found.
 */
class StoreIndexRequest extends FormRequest
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
        $parentId = $this->parent()?->getKey();

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/',
                Rule::unique('record_indexes', 'code')
                    ->where('tenant_id', $this->tenantId())
                    ->where('company_id', $this->companyId())
                    ->where('record_classification_id', $parentId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'An Index Code may contain letters, numbers and hyphens only.',
            'code.unique' => 'This Classification already has an Index with that code.',
        ];
    }

    /**
     * The parent Classification, resolved under the active company.
     *
     * Null when the code names no Classification in this company, which the
     * controller turns into a 404 rather than a write against someone else's
     * scheme.
     */
    public function parent(): ?RecordClassification
    {
        $code = trim((string) $this->input('classification'));

        if ($code === '') {
            return null;
        }

        return RecordClassification::query()
            ->where('tenant_id', $this->tenantId())
            ->where('company_id', $this->companyId())
            ->where('code', $code)
            ->first();
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
