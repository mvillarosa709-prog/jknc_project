<?php

namespace App\Http\Requests;

use App\Policies\RecordClassificationPolicy;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Updating an existing Classification in the active company's scheme.
 */
class UpdateClassificationRequest extends FormRequest
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
        $classificationId = $this->route('classification');

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/',
                Rule::unique('record_classifications', 'code')
                    ->where('tenant_id', $this->tenantId())
                    ->where('company_id', $this->companyId())
                    ->ignore($classificationId),
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
            'code.regex' => 'A Classification Code may contain letters, numbers and hyphens only.',
            'code.unique' => 'This company already uses that Classification Code.',
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
