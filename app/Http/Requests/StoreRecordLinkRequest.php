<?php

namespace App\Http\Requests;

use App\Enums\LinkRelationship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecordLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target' => ['required_without:module', 'nullable', 'string'],
            'module' => ['required_without:target', 'nullable', 'string', 'max:60'],
            'reference_id' => ['required_with:module', 'nullable', 'string', 'max:26'],
            'linked_label' => ['nullable', 'string', 'max:255'],
            'link_type' => ['required', 'string', Rule::in(array_keys(LinkRelationship::options()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target.required_without' => 'Choose a record to link, or name an ORDO module object.',
            'module.required_without' => 'Choose a record to link, or name an ORDO module object.',
        ];
    }

    public function linksToRecord(): bool
    {
        return filled($this->input('target'));
    }
}
