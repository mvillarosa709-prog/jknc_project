<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePlanLimitsRequest;
use App\Services\CompanyContext;
use Illuminate\Http\RedirectResponse;

/**
 * The plan limits behind the Repository Usage card.
 *
 * Both limits live on the company row and are written in one statement so the
 * card can never show a capacity ceiling from one edit and a storage ceiling from
 * another.
 *
 * Zero is stored as NULL rather than as zero: the rest of the application reads
 * a null allowance as "unlimited", so storing 0 would show up as a company that
 * has consumed its whole allowance of nothing.
 */
class RecordPlanLimitsController extends Controller
{
    public function __construct(private readonly CompanyContext $context) {}

    public function update(UpdatePlanLimitsRequest $request): RedirectResponse
    {
        $company = $this->context->current();

        $company->forceFill([
            'record_unit_allocation' => $this->normalise($request->input('record_unit_allocation')),
            'storage_limit_bytes' => $request->storageLimitBytes(),
        ])->save();

        return back()
            ->with('setup_tab', 'usage')
            ->with('status', 'Plan limits were updated.');
    }

    /**
     * A zero allowance means unlimited, which this codebase stores as null.
     */
    private function normalise(mixed $value): ?int
    {
        $value = (int) $value;

        return $value > 0 ? $value : null;
    }
}
