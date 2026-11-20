<?php

namespace App\Http\Requests;

use App\Policies\RecordClassificationPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Record Numbering settings.
 *
 * The bounds are the reference mock's own: a prefix that falls back to REC when
 * blank, three to ten sequence digits, and a next sequence of at least one.
 * Clamping here rather than in the service means a number can never be issued
 * from a width or a sequence outside the range the operator was shown.
 *
 * Authorised through the same manageScheme ability as the rest of setup. Numbering
 * is reference data, not day-to-day records work: it decides the identifier on
 * every record filed from now on, and the next sequence can be reset. The Usage
 * tab's Plan limits save is guarded identically, and the scheme tables below this
 * tab are too.
 *
 * The company is never read from the request; it comes from the active context, so
 * a hand-crafted payload cannot renumber another company.
 *
 * None of this touches the Document Reference. That identifier is generated
 * system-side at filing and stays outside anything a person can edit.
 */
class UpdateRecordNumberingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(RecordClassificationPolicy::class)->create($this->user());
    }

    /**
     * Translate the select's Yes/No into the boolean the rules expect.
     *
     * The mock's control posts the strings "yes" and "no", and Laravel's boolean
     * rule accepts neither — so without this the form as written rejects itself,
     * which is the worst possible way for a setting to be broken: it looks right
     * on screen and fails on save.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('record_include_year')) {
            return;
        }

        $value = $this->input('record_include_year');

        if (! is_string($value)) {
            return;
        }

        $normalised = strtolower(trim($value));

        // Only the words the control actually offers are translated. Anything
        // else is left exactly as it arrived so the boolean rule rejects it —
        // folding an unrecognised value into `false` would silently switch the
        // year off for a company that never asked for that.
        if (in_array($normalised, ['yes', 'no'], true)) {
            $this->merge(['record_include_year' => $normalised === 'yes']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'record_prefix' => ['required', 'string', 'max:10'],
            'record_sequence_digits' => ['required', 'integer', 'min:3', 'max:10'],
            'record_next_sequence' => ['required', 'integer', 'min:1'],
            'record_include_year' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'record_prefix.required' => 'The record prefix cannot be empty; ORDO would fall back to REC.',
            'record_prefix.max' => 'The record prefix cannot be longer than 10 characters.',
            'record_sequence_digits.min' => 'A record number needs at least 3 sequence digits.',
            'record_sequence_digits.max' => 'A record number can have at most 10 sequence digits.',
            'record_next_sequence.min' => 'The next sequence must be 1 or higher.',
            'record_include_year.boolean' => 'Choose whether the year is included.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'record_prefix' => 'record prefix',
            'record_sequence_digits' => 'sequence digits',
            'record_next_sequence' => 'next sequence',
            'record_include_year' => 'include year',
        ];
    }

    /**
     * The prefix, normalised the way the mock does.
     */
    public function prefix(): string
    {
        $prefix = trim((string) $this->input('record_prefix'));

        return $prefix === '' ? 'REC' : $prefix;
    }
}
