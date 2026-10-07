<?php

namespace App\Services;

use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripPersonnel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OfficialBusinessMasterPersonnelService
{
    /** @param array<int, int|string> $teamEmployeeIds */
    public function sync(OfficialBusinessTrip $trip, array $teamEmployeeIds, array $natures = []): void
    {
        if (! Schema::hasTable('official_business_trip_personnel')) {
            return;
        }

        $employeeIds = collect($teamEmployeeIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->when($trip->employee_id, fn ($ids) => $ids->prepend((int) $trip->employee_id))
            ->unique()->values()->all();

        if (collect($teamEmployeeIds)->filter(fn ($id): bool => filled($id))->count()
            !== collect($teamEmployeeIds)->filter(fn ($id): bool => filled($id))->unique()->count()
            || ($trip->employee_id && in_array((int) $trip->employee_id, array_map('intval', $teamEmployeeIds), true))) {
            throw ValidationException::withMessages([
                'team_member_employee_ids' => 'Team members must be unique and cannot include the Primary Traveler.',
            ]);
        }

        if ($trip->tripTickets()->exists()) {
            $outside = $trip->tripTickets()->whereHas('personnel', function ($query) use ($employeeIds): void {
                $query->whereNotIn('employee_id', $employeeIds);
            })->exists();
            if ($outside) {
                throw ValidationException::withMessages([
                    'team_member_employee_ids' => 'A Trip Ticket includes personnel outside the proposed master team.',
                ]);
            }
        }

        foreach ($employeeIds as $employeeId) {
            $member = $trip->personnel()->withTrashed()->where('employee_id', $employeeId)->first();
            if ($member?->trashed()) {
                $member->restore();
            }
            if (! $member) {
                $member = OfficialBusinessTripPersonnel::create([
                    'official_business_trip_id' => $trip->id,
                    'employee_id' => $employeeId,
                ]);
            }
            if (Schema::hasColumn('official_business_trip_personnel', 'role')) {
                $member->role = (int) $employeeId === (int) $trip->employee_id ? 'Primary Traveler' : 'Team Member';
                $member->nature_of_travel = $natures[$employeeId] ?? $trip->nature_of_travel;
                $member->save();
            }
        }

        $trip->personnel()->when($employeeIds !== [], fn ($query) => $query->whereNotIn('employee_id', $employeeIds))->delete();
    }
}
