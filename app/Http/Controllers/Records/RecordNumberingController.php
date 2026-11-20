<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRecordNumberingRequest;
use App\Models\Company;
use App\Models\RecordActivityLog;
use App\Services\CompanyContext;
use App\Support\RecordNumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The Record Numbering tab's save.
 *
 * A settings write, shaped like the Usage tab's Plan limits save: an ordinary
 * post that redirects back with `setup_tab` so the modal reopens where it came
 * from.
 *
 * The write takes the same company row lock filing does. The counter that decides
 * the next Record ID lives on that row, so an operator save that ran unlocked could
 * interleave with a filing and rewind the sequence. It cannot produce a duplicate —
 * the generator walks forward and a unique index backs it — but it can silently move
 * numbering backwards, and a number that goes backwards is one somebody has to
 * reconcile by hand.
 */
class RecordNumberingController extends Controller
{
    public function __construct(
        private readonly CompanyContext $context,
        private readonly RecordNumberGenerator $numbers,
    ) {}

    public function update(UpdateRecordNumberingRequest $request): RedirectResponse
    {
        $description = DB::transaction(function () use ($request): string {
            // Re-read under the lock rather than taking the context's instance as-is.
            //
            // Filing advances record_next_sequence on its own, inside the filing
            // transaction, through a different instance. An instance held across that
            // still carries the old number, so forceFill() would find nothing dirty
            // and save() would write nothing at all — the operator would see
            // "Record numbering updated" and nothing would have changed. Reading the
            // row locked makes it both fresh and serialised, so one read serves for
            // both jobs.
            $company = Company::query()
                ->whereKey($this->context->id())
                ->lockForUpdate()
                ->firstOrFail();

            // What the operator is about to change, captured before it changes.
            $before = [
                'prefix' => (string) $company->record_prefix,
                'digits' => (int) $company->record_sequence_digits,
                'next_sequence' => (int) $company->record_next_sequence,
                'include_year' => (bool) $company->record_include_year,
            ];

            $company->forceFill([
                'record_prefix' => $request->prefix(),
                'record_sequence_digits' => (int) $request->input('record_sequence_digits'),
                'record_next_sequence' => (int) $request->input('record_next_sequence'),
                'record_include_year' => $request->boolean('record_include_year'),
            ])->save();

            // Audited, unlike the other setup screens. Numbering governs the
            // identifier on every record filed from now on, so a later "where did
            // REC-2026-000412 come from" needs an answer that is not in the file
            // itself — changing the prefix never rewrites the records already issued.
            //
            // Written inside the transaction, so a rollback cannot leave a trail
            // entry describing a change that never happened.
            RecordActivityLog::query()->create([
                'tenant_id' => $company->tenant_id,
                'company_id' => $company->id,
                'record_id' => null,
                'record_intake_item_id' => null,
                'user_id' => Auth::id(),
                'action' => 'record.numbering_changed',
                'description' => sprintf(
                    'Record numbering set to %s.',
                    $this->numbers->preview($company),
                ),
                'properties' => ['before' => $before],
                'ip_address' => request()?->ip(),
            ]);

            return sprintf(
                'Record numbering set to %s.',
                $this->numbers->preview($company),
            );
        });

        return back()
            ->with('setup_tab', 'numbering')
            ->with('status', $description);
    }
}
