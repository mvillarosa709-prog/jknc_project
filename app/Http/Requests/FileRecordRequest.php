<?php

namespace App\Http\Requests;

use App\Models\RecordIndex;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FileRecordRequest extends FormRequest
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
            'record_classification_id' => ['required', 'string', 'max:26'],
            'record_index_id' => ['required', 'string', 'max:26'],
            'title' => ['required', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'source_other' => ['nullable', 'required_if:source,Others', 'string', 'max:255'],
            'external_reference_number' => ['nullable', 'string', 'max:255'],
            'identifiers' => ['nullable', 'array', 'max:25'],
            'identifiers.*' => ['nullable', 'string', 'max:255'],
            'additional_dates' => ['nullable', 'array', 'max:25'],
            'additional_dates.*' => ['nullable', 'string', 'max:255'],
            'signatories' => ['nullable', 'array', 'max:25'],
            'signatories.*' => ['nullable', 'string', 'max:255'],
            'financials' => ['nullable', 'array', 'max:25'],
            'financials.*' => ['nullable', 'string', 'max:255'],
            'is_confidential' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'record_classification_id.required' => 'Select a Classification Code.',
            'record_index_id.required' => 'Select an Index Code.',
            'title.required' => 'Enter a Document Title.',
            'source_other.required_if' => 'Specify the source selected as Others.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $context = app(CompanyContext::class);
            $index = RecordIndex::query()
                ->where('tenant_id', $context->tenantId())
                ->where('company_id', $context->id())
                ->whereKey((string) $this->input('record_index_id'))
                ->first();

            if (! $index instanceof RecordIndex) {
                return;
            }

            $config = $index->advanced_field_config;
            $required = $config === true
                ? ['identifiers', 'additional_dates', 'signatories', 'financials']
                : (is_array($config) && is_array($config['required'] ?? null)
                    ? $config['required']
                    : []);

            foreach (array_intersect($required, ['identifiers', 'additional_dates', 'signatories', 'financials']) as $field) {
                $values = $this->input($field, []);

                if (! is_array($values) || collect($values)->filter(fn ($value): bool => filled($value))->isEmpty()) {
                    $validator->errors()->add($field, 'Complete at least one '.str_replace('_', ' ', $field).' entry required by this Index.');
                }
            }
        });
    }

    protected function getRedirectUrl(): string
    {
        $query = [];
        parse_str((string) parse_url(url()->previous(), PHP_URL_QUERY), $query);
        $query['open'] = (string) $this->route('intake');

        return route('records.unclassified', $query);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'record_classification_id' => 'classification',
            'record_index_id' => 'index',
            'document_date' => 'document date',
            'external_reference_number' => 'external reference number',
            'source_other' => 'specified source',
        ];
    }
}
