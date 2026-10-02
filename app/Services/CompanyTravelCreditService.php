<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyTravelCreditAccount;
use App\Models\CompanyTravelCreditEntry;
use App\Models\OfficialBusinessTripTicket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyTravelCreditService
{
    public function adjust(
        User $actor,
        Company $company,
        float $unitsDelta,
        ?float $creditValue,
        string $reason,
        string $entryType = 'Adjustment'
    ): CompanyTravelCreditEntry {
        $this->authorizeFinance($actor);

        return DB::transaction(function () use ($actor, $company, $unitsDelta, $creditValue, $reason, $entryType): CompanyTravelCreditEntry {
            $account = $this->lockedAccount($company);
            $nextBalance = round((float) $account->available_units + $unitsDelta, 2);
            if ($nextBalance < 0) {
                throw ValidationException::withMessages([
                    'units_delta' => 'This adjustment would make the client travel-credit balance negative.',
                ]);
            }

            if ($creditValue !== null) {
                if ($creditValue <= 0) {
                    throw ValidationException::withMessages([
                        'credit_value' => 'The configured travel-credit value must be greater than zero.',
                    ]);
                }
                $account->credit_value = $creditValue;
            }

            $account->available_units = $nextBalance;
            $account->save();

            return $account->entries()->create([
                'entry_type' => $entryType,
                'units_delta' => $unitsDelta,
                'unit_value' => $account->credit_value,
                'balance_after' => $nextBalance,
                'reason' => $reason,
                'recorded_by_user_id' => $actor->id,
            ]);
        });
    }

    public function applyTicketUsage(
        User $actor,
        OfficialBusinessTripTicket $ticket,
        ?CompanyTravelCreditEntry $currentEntry,
        ?float $units,
        ?float $requestedUnitValue
    ): ?CompanyTravelCreditEntry {
        $this->authorizeFinance($actor);

        $account = $this->lockedAccount($ticket->company);
        $units = $units !== null ? round($units, 2) : null;
        $unitValue = $requestedUnitValue && $requestedUnitValue > 0
            ? round($requestedUnitValue, 2)
            : round((float) $account->credit_value, 2);

        if ($currentEntry && $currentEntry->reversed_at === null) {
            $currentUnits = abs((float) $currentEntry->units_delta);
            if ($units !== null && $currentUnits === $units && (float) $currentEntry->unit_value === $unitValue) {
                return $currentEntry;
            }

            $this->reverseUsage($actor, $account, $currentEntry, 'Trip Ticket financial treatment changed.');
        }

        if ($units === null) {
            return null;
        }

        if ($units <= 0) {
            throw ValidationException::withMessages([
                'travel_credit_units' => 'Travel credit units must be greater than zero.',
            ]);
        }
        if ((float) $account->available_units < $units) {
            throw ValidationException::withMessages([
                'travel_credit_units' => "Only {$account->available_units} travel credit(s) are available for {$ticket->client_name_snapshot}.",
            ]);
        }

        $account->available_units = round((float) $account->available_units - $units, 2);
        $account->save();

        return $account->entries()->create([
            'official_business_trip_ticket_id' => $ticket->id,
            'entry_type' => 'Used',
            'units_delta' => -$units,
            'unit_value' => $unitValue,
            'balance_after' => $account->available_units,
            'reason' => 'Travel credit used by '.$ticket->reference_no.'.',
            'recorded_by_user_id' => $actor->id,
        ]);
    }

    public function summary(Company $company): array
    {
        $account = $company->travelCreditAccount()
            ->with(['entries.recordedBy:id,name,email'])
            ->first();

        return [
            'company_id' => $company->id,
            'available_units' => $account?->available_units ?? '0.00',
            'credit_value' => $account?->credit_value
                ?? number_format((float) config('official_business.default_travel_credit_value', 500), 2, '.', ''),
            'entries' => $account?->entries->map(fn (CompanyTravelCreditEntry $entry): array => [
                'id' => $entry->id,
                'trip_ticket_id' => $entry->official_business_trip_ticket_id,
                'entry_type' => $entry->entry_type,
                'units_delta' => $entry->units_delta,
                'unit_value' => $entry->unit_value,
                'balance_after' => $entry->balance_after,
                'reason' => $entry->reason,
                'recorded_by' => $entry->recordedBy?->name,
                'reverses_entry_id' => $entry->reverses_entry_id,
                'reversed_at' => $entry->reversed_at?->toIso8601String(),
                'created_at' => $entry->created_at?->toIso8601String(),
            ])->values() ?? collect(),
        ];
    }

    private function reverseUsage(
        User $actor,
        CompanyTravelCreditAccount $account,
        CompanyTravelCreditEntry $entry,
        string $reason
    ): CompanyTravelCreditEntry {
        if ($entry->reversed_at !== null) {
            throw ValidationException::withMessages([
                'travel_credit_units' => 'This travel-credit use has already been reversed.',
            ]);
        }

        $units = abs((float) $entry->units_delta);
        $account->available_units = round((float) $account->available_units + $units, 2);
        $account->save();
        $entry->forceFill([
            'reversed_at' => now(),
            'reversed_by_user_id' => $actor->id,
        ])->save();

        return $account->entries()->create([
            'official_business_trip_ticket_id' => $entry->official_business_trip_ticket_id,
            'entry_type' => 'Reversal',
            'units_delta' => $units,
            'unit_value' => $entry->unit_value,
            'balance_after' => $account->available_units,
            'reason' => $reason,
            'recorded_by_user_id' => $actor->id,
            'reverses_entry_id' => $entry->id,
        ]);
    }

    private function lockedAccount(Company $company): CompanyTravelCreditAccount
    {
        CompanyTravelCreditAccount::query()->firstOrCreate(
            ['company_id' => $company->id],
            [
                'available_units' => 0,
                'credit_value' => config('official_business.default_travel_credit_value', 500),
            ]
        );

        return CompanyTravelCreditAccount::query()
            ->where('company_id', $company->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function authorizeFinance(User $actor): void
    {
        if (! app(OfficialBusinessAuthorizationService::class)->canReadFinance($actor)) {
            throw new AuthorizationException('Finance access is required to manage client travel credits.');
        }
    }
}
