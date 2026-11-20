<?php

namespace App\Http\Requests;

use App\Services\Records\RecordLookups;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Uploading a file as a Record in its own right.
 *
 * Carries the same progressive-disclosure fields as the filing form: the
 * Classification and Index are required because a Record cannot exist without a
 * place in the scheme, and everything else is optional.
 *
 * Classification and Index are validated against the codes this company actually
 * owns rather than trusted, exactly as the filing form validates them.
 */
class UploadAsRecordRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                ...$this->documentRules(),
                'max:'.($this->maxFileSizeMb() * 1024),
            ],
            'record_classification_id' => ['required', 'string', Rule::in($this->classificationIds())],
            'record_index_id' => ['required', 'string', Rule::in($this->indexIds())],
            'title' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'external_reference_number' => ['nullable', 'string', 'max:255'],
            'is_confidential' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => "That file is larger than the {$this->maxFileSizeMb()} MB limit.",
            'record_classification_id.in' => 'That Classification is not part of this company\'s scheme.',
            'record_index_id.in' => 'That Index is not part of this company\'s scheme.',
            ...$this->documentMessages('file'),
        ];
    }

    private function maxFileSizeMb(): int
    {
        return max(1, (int) config('ordo.uploads.record_file_max_size_mb', 50));
    }

    /**
     * @return list<string>
     */
    private function classificationIds(): array
    {
        return app(RecordLookups::class)->classifications()
            ->map(fn ($classification): string => (string) $classification->id)
            ->all();
    }

    /**
     * @return list<string>
     */
    private function indexIds(): array
    {
        return app(RecordLookups::class)->indexes()
            ->map(fn ($index): string => (string) $index->id)
            ->all();
    }
}
