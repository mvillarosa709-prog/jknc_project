<?php

namespace App\Http\Requests;

use App\Enums\ReportTrigger;
use App\Enums\ReportType;
use App\Services\CompanyContext;
use App\Services\Records\RecordLookups;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Configuring a report, and recording it once it is taken onward.
 *
 * The same request serves the preview and the history entry, because both are
 * built from the same four filters: a run that could be described with different
 * values from the preview beside it would be describing something the reader
 * never saw.
 *
 * Classification and Index are validated against the codes this company actually
 * owns rather than trusted, because the preview's row set is derived from them.
 * Source is a free-text field on the record, so it is bounded by length rather
 * than membership.
 *
 * tenant_id and company_id are never read from the payload. They come from the
 * request's resolved context, so a run cannot be filed against someone else's
 * company by posting a different id.
 */
class GenerateReportRequest extends FormRequest
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
            'type' => ['required', Rule::in(array_keys(ReportType::options()))],
            'classification' => ['nullable', 'string', 'max:20', Rule::in($this->classificationCodes())],
            'index' => ['nullable', 'string', 'max:20', Rule::in($this->indexCodes())],
            'source' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'digits:4', 'min:1900', 'max:2999'],
            // Only sent when the report is being recorded. A preview sends
            // neither, and requiring them here would break the preview.
            'trigger' => ['nullable', 'string', Rule::in(array_keys(ReportTrigger::options()))],
            'comments' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classification.in' => 'That Classification is not part of this company\'s scheme.',
            'index.in' => 'That Index is not part of this company\'s scheme.',
        ];
    }

    public function reportType(): ReportType
    {
        return ReportType::from((string) $this->input('type'));
    }

    /**
     * How the report is being taken onward, or null when this is a preview.
     */
    public function trigger(): ?ReportTrigger
    {
        return ReportTrigger::tryFrom((string) $this->input('trigger'));
    }

    /**
     * The management / consultant observations written into the preview.
     *
     * Blank input becomes null rather than an empty string, so a report saved
     * without comments is distinguishable from one saved with them.
     */
    public function comments(): ?string
    {
        $comments = trim((string) ($this->input('comments') ?? ''));

        return $comments === '' ? null : $comments;
    }

    public function tenantId(): string
    {
        return $this->context()->tenantId();
    }

    public function companyId(): string
    {
        return $this->context()->id();
    }

    private function context(): CompanyContext
    {
        return app(CompanyContext::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'classification' => trim((string) ($this->input('classification') ?? '')),
            'index' => trim((string) ($this->input('index') ?? '')),
            'source' => trim((string) ($this->input('source') ?? '')),
            'year' => trim((string) ($this->input('year') ?? '')),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function classificationCodes(): array
    {
        return app(RecordLookups::class)->classifications()
            ->map(fn ($classification): string => (string) $classification->code)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function indexCodes(): array
    {
        return app(RecordLookups::class)->indexes()
            ->map(fn ($index): string => (string) $index->code)
            ->all();
    }
}
