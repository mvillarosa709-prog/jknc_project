<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIntakeRequest extends FormRequest
{
    use Concerns\ValidatesUploadedDocuments;

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
            'files' => ['required', 'array', 'min:1', 'max:25'],
            'files.*' => [
                'required',
                'file',
                ...$this->documentRules(),
                'max:'.($this->maxFileSizeMb() * 1024),
            ],
            'capture_source' => ['nullable', 'string', 'in:upload,multi_upload,camera'],
            'title' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'external_reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Choose at least one file to upload.',
            'files.max' => 'A maximum of 25 files can be uploaded at once.',
            'files.*.max' => "Each file must be {$this->maxFileSizeMb()} MB or smaller.",
            ...$this->documentMessages('files.*'),
        ];
    }

    private function maxFileSizeMb(): int
    {
        return max(1, (int) config('ordo.uploads.intake_max_file_size_mb', 20));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'files.*' => 'file',
            'document_date' => 'document date',
            'external_reference_number' => 'external reference number',
        ];
    }
}
