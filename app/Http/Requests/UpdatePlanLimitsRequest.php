<?php

namespace App\Http\Requests;

use App\Policies\RecordClassificationPolicy;
use App\Services\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing the plan limits shown by the Repository Usage card.
 *
 * Authorised through the same manageScheme ability as the rest of setup. These
 * are billing inputs, so they are treated as reference data rather than
 * day-to-day records work.
 *
 * The company is never read from the request: it comes from the active context,
 * so a hand-crafted payload cannot set the limits of another company.
 */
class UpdatePlanLimitsRequest extends FormRequest
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
            // Zero is the explicit "no limit" case rather than an invalid one: the
            // Repository Usage card has to be able to show an unlimited company.
            'record_unit_allocation' => ['required', 'integer', 'min:0', 'max:100000000'],
            // Entered in gigabytes, because that is the unit a plan is sold in.
            'storage_limit_gb' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'record_unit_allocation.min' => 'A Record Unit allowance cannot be negative. Use 0 for unlimited.',
            'storage_limit_gb.min' => 'A storage allowance cannot be negative. Use 0 for unlimited.',
            'storage_limit_gb.max' => 'That storage allowance is larger than the maximum this module meters.',
        ];
    }

    /**
     * The storage ceiling in the raw bytes the column holds.
     */
    public function storageLimitBytes(): ?int
    {
        $gigabytes = (float) $this->input('storage_limit_gb');

        if ($gigabytes <= 0) {
            return null;
        }

        return (int) round($gigabytes * 1073741824);
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
