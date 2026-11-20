<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Attaching a file to a Record that already exists.
 *
 * One file per post. Supporting files and versions are deliberately one-at-a-time:
 * each is its own audited write with its own storage guard, and a batch would
 * make a partial failure — some files stored, one refused on the ceiling — much
 * harder to reason about than re-uploading what did not land.
 */
class AttachRecordFileRequest extends FormRequest
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => "That file is larger than the {$this->maxFileSizeMb()} MB limit.",
            ...$this->documentMessages('file'),
        ];
    }

    private function maxFileSizeMb(): int
    {
        return max(1, (int) config('ordo.uploads.record_file_max_size_mb', 50));
    }
}
