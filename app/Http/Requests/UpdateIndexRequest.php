<?php

namespace App\Http\Requests;

use App\Models\RecordIndex;
use App\Policies\RecordClassificationPolicy;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Updating an existing Index in the active company's scheme.
 */
class UpdateIndexRequest extends FormRequest
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
        $indexId = $this->route('index');
        $index = RecordIndex::query()
            ->where('tenant_id', $this->tenantId())
            ->where('company_id', $this->companyId())
            ->where('id', $indexId)
            ->first();

        $parentId = $index?->record_classification_id;

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/',
                Rule::unique('record_indexes', 'code')
                    ->where('tenant_id', $this->tenantId())
                    ->where('company_id', $this->companyId())
                    ->where('record_classification_id', $parentId)
                    ->ignore($indexId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable'],
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

    public function tenantId(): string
    {
        return app(CompanyContext::class)->tenantId();
    }

    public function companyId(): string
    {
        return app(CompanyContext::class)->id();
    }
}
