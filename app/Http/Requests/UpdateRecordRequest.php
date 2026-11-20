<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRecordRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:255'],
            'source_other' => ['nullable', 'required_if:source,Others', 'string', 'max:255'],
            'external_reference_number' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'identifiers' => ['nullable', 'array', 'max:25'],
            'identifiers.*' => ['nullable', 'string', 'max:255'],
            'additional_dates' => ['nullable', 'array', 'max:25'],
            'additional_dates.*' => ['nullable', 'string', 'max:255'],
            'signatories' => ['nullable', 'array', 'max:25'],
            'signatories.*' => ['nullable', 'string', 'max:255'],
            'financials' => ['nullable', 'array', 'max:25'],
            'financials.*' => ['nullable', 'string', 'max:255'],
            'retention_period' => ['nullable', 'string', 'max:50'],
            'is_confidential' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'A Document Title is required.',
            'source_other.required_if' => 'Specify the source selected as Others.',
            'amount.numeric' => 'The Amount must be a number.',
            'currency.size' => 'The Currency must be a 3-letter code.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        $record = $this->route('record');
        $query = [];
        parse_str((string) parse_url(url()->previous(), PHP_URL_QUERY), $query);
        $query['edit'] = (string) (is_object($record) ? $record->getKey() : $record);

        return route('records.index', $query);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'document_date' => 'document date',
            'external_reference_number' => 'external reference number',
            'retention_period' => 'retention period',
            'source_other' => 'specified source',
        ];
    }
}
