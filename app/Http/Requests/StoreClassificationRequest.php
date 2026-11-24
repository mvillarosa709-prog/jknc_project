<?php

namespace App\Http\Requests;

use App\Policies\RecordClassificationPolicy;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Adding a Classification to the active company's scheme.
 *
 * The tenant and company are never read from the request: they come from the
 * active context, so a hand-crafted payload cannot file a code against another
 * company.
 */
class StoreClassificationRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:20',
                // The scheme is numeric-prefixed, e.g. 301, so a free-text code
                // like "gen" would sit oddly beside it in the tree and in the
                // dropdowns that sort by code.
                'regex:/^[A-Za-z0-9][A-Za-z0-9\-]*$/',
                Rule::unique('record_classifications', 'code')
                    ->where('tenant_id', $this->tenantId())
                    ->where('company_id', $this->companyId()),
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
