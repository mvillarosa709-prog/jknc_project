<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyTravelCreditService;
use App\Services\OfficialBusinessAuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyTravelCreditController extends Controller
{
    public function show(
        Request $request,
        Company $company,
        CompanyTravelCreditService $credits
    ) {
        $this->authorizeFinance($request);

        $summary = $credits->summary($company);

        if ($request->expectsJson()) {
            return response()->json([
                'travel_credit' => $summary,
            ]);
        }

        return view('company.travel-credits', [
            'company' => $company,
            'summary' => $summary,
        ]);
    }

    public function adjust(
        Request $request,
        Company $company,
        CompanyTravelCreditService $credits
    ) {
        $this->authorizeFinance($request);
        $validated = $request->validate([
            'entry_type' => ['required', Rule::in(['Added', 'Adjustment'])],
            'units_delta' => ['required', 'decimal:0,2'],
            'credit_value' => ['nullable', 'decimal:0,2', 'gt:0'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $unitsDelta = (float) $validated['units_delta'];
        if ($validated['entry_type'] === 'Added' && $unitsDelta <= 0) {
            throw ValidationException::withMessages([
                'units_delta' => 'Added credits must be greater than zero.',
            ]);
        }
        if ($unitsDelta === 0.0 && ! filled($validated['credit_value'] ?? null)) {
            throw ValidationException::withMessages([
                'units_delta' => 'Enter a credit adjustment or a new configurable credit value.',
            ]);
        }

        $entry = $credits->adjust(
            $request->user(),
            $company,
            $unitsDelta,
            filled($validated['credit_value'] ?? null) ? (float) $validated['credit_value'] : null,
            $validated['reason'],
            $validated['entry_type']
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Client travel-credit ledger updated successfully.',
                'entry_id' => $entry->id,
                'travel_credit' => $credits->summary($company),
            ], 201);
        }

        return redirect()
            ->route('activities.official-business.travel-credits.show', $company)
            ->with('success', 'Client travel-credit ledger updated successfully.');
    }

    private function authorizeFinance(Request $request): void
    {
        abort_unless(
            app(OfficialBusinessAuthorizationService::class)->canReadFinance($request->user()),
            403
        );
    }
}
