<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkFileRecordsRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:25'],
            'items.*' => ['required', 'string', 'max:26'],
            'record_classification_id' => ['required', 'string', 'max:26'],
            'record_index_id' => ['required', 'string', 'max:26'],
            'source' => ['nullable', 'string', 'max:255'],
            'source_other' => ['nullable', 'required_if:source,Others', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Select at least one unclassified record to classify.',
            'items.max' => 'A maximum of 25 records can be classified at once.',
            'record_classification_id.required' => 'Select a Classification Code.',
            'record_index_id.required' => 'Select an Index Code.',
            'source_other.required_if' => 'Specify the source selected as Others.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items' => 'selected records',
            'record_classification_id' => 'classification',
            'record_index_id' => 'index',
        ];
    }
}
