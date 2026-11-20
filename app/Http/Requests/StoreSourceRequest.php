<?php

namespace App\Http\Requests;

use App\Enums\SourceType;
use App\Policies\RecordClassificationPolicy;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a Source to the active company's reference data.
 *
 * Sources are reference data that the scheme screen does not own, so this is
 * authorised through the same `manageScheme` ability rather than a new one.
 */
class StoreSourceRequest extends FormRequest
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
                Rule::unique('record_sources', 'name')
                    ->where('tenant_id', $this->tenantId())
                    ->where('company_id', $this->companyId()),
            ],
            'type' => ['required', 'string', Rule::in(SourceType::options())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This company already uses that Source name.',
            'type.in' => 'Choose one of the supported Source types.',
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
