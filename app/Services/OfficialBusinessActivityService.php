<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OfficialBusinessTrip;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class OfficialBusinessActivityService
{
    /**
     * Return the Official Business records visible to the given Activities user.
     *
     * @return array{records: Collection<int, OfficialBusinessTrip>, current_employee: ?Employee, identity: array, can_manage: bool}
     */
    public function context(User $user): array
    {
        if (! Schema::hasTable('official_business_trips')) {
            return [
                'records' => collect(),
                'current_employee' => null,
                'identity' => $this->identity(null, $user),
                'can_manage' => false,
            ];
        }

        $currentEmployee = $this->currentEmployee($user);
        $identity = $this->identity($currentEmployee, $user);
        $canManage = $this->canManage($user);

        $records = OfficialBusinessTrip::query()
            ->with([
                'preparedBy:id,name,email',
                'company:id,company_name',
                'contactRecord:id,first_name,last_name',
                'deal:id,deal_code,deal_name',
                'project:id,project_code,name',
            ])
            ->latest()
            ->get();

        return [
            'records' => $records,
            'current_employee' => $currentEmployee,
            'identity' => $identity,
            'can_manage' => $canManage,
        ];
    }

    public function isMine(OfficialBusinessTrip $trip, array $identity): bool
    {
        if (! empty($identity['employee_id']) && $trip->employee_id === $identity['employee_id']) {
            return true;
        }

        if (! empty($identity['user_id']) && $trip->created_by === $identity['user_id']) {
            return true;
        }

        if ((int) $trip->workflow_version >= 3) {
            return ! empty($identity['employee_id'])
                && $trip->tripTickets()
                    ->whereHas('personnel', fn (Builder $query) => $query->where('employee_id', $identity['employee_id']))
                    ->exists();
        }

        if (empty($identity['name'])) {
            return false;
        }

        $identityName = strtolower(trim((string) $identity['name']));

        return collect($trip->team_members ?? [])->contains(
            fn ($member) => strtolower(trim((string) $member)) === $identityName
        );
    }

    public function toUnifiedActivity(OfficialBusinessTrip $trip): array
    {
        $attachments = collect($trip->attachment_paths ?? [])
            ->map(function ($attachment): array {
                if (is_array($attachment)) {
                    return $attachment + [
                        'url' => filled($attachment['path'] ?? null)
                            ? Storage::url($attachment['path'])
                            : null,
                    ];
                }

                return [
                    'path' => $attachment,
                    'url' => filled($attachment) ? Storage::url($attachment) : null,
                ];
            })
            ->values()
            ->all();

        return [
            'id' => 'official-business-travel-'.$trip->id,
            'source_id' => $trip->id,
            'type' => 'official_business_travel',
            'title' => $trip->purpose ?: $trip->destination ?: 'Official Business Travel #'.$trip->id,
            'owner' => $trip->employee_name,
            'account_id' => $trip->company_id,
            'account' => $trip->company?->company_name ?: ($trip->is_client_travel ? $trip->client_name : null),
            'contact_id' => $trip->contact_id,
            'contact' => $trip->contactRecord
                ? trim($trip->contactRecord->first_name.' '.$trip->contactRecord->last_name)
                : null,
            'deal_id' => $trip->deal_id,
            'deal' => $trip->deal ? [
                'id' => $trip->deal->id,
                'code' => $trip->deal->deal_code,
                'name' => $trip->deal->deal_name,
            ] : null,
            'project_id' => $trip->project_id,
            'project' => $trip->project ? [
                'id' => $trip->project->id,
                'code' => $trip->project->project_code,
                'name' => $trip->project->name,
            ] : null,
            'related_record' => $trip->ob_reference_no ?: $trip->destination,
            'status' => filled($trip->status) ? (string) $trip->status : null,
            // Canonical O.B. approval and independent movement for All Activities consumers.
            'source_status' => filled($trip->status) ? (string) $trip->status : null,
            'movement_status' => $trip->movement_status,
            'display_status' => filled($trip->status) ? (string) $trip->status : null,
            'occurred_at' => $trip->date_from?->copy()->startOfDay()->toIso8601String(),
            'actual_departure' => $trip->actual_departure?->toIso8601String(),
            'actual_return' => $trip->actual_return?->toIso8601String(),
            'duration_minutes' => $trip->actual_departure && $trip->actual_return
                ? (int) $trip->actual_departure->diffInMinutes($trip->actual_return)
                : null,
            'duration_source' => $trip->actual_departure && $trip->actual_return
                ? 'actual_travel'
                : null,
            'prepared_by_user_id' => $trip->prepared_by_user_id,
            'prepared_by' => $trip->preparedBy,
            'client_report_description' => $trip->client_report_description,
            'include_in_client_report' => (bool) $trip->include_in_client_report,
            'attachments' => $attachments,
            'created_at' => $trip->created_at?->toIso8601String(),
            'updated_at' => $trip->updated_at?->toIso8601String(),
        ];
    }

    private function canManage(User $user): bool
    {
        return app(OfficialBusinessAuthorizationService::class)->canManage($user);
    }

    private function currentEmployee(User $user): ?Employee
    {
        return Employee::with('department')
            ->where(function (Builder $query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('email', $user->email)
                    ->orWhere('work_email', $user->email)
                    ->orWhere('company_email', $user->email);
            })
            ->first();
    }

    private function identity(?Employee $employee, User $user): array
    {
        return [
            'user_id' => $user->id,
            'employee_id' => $employee?->id,
            'employee_code' => $employee?->employee_code,
            'name' => $employee?->full_name ?: $user->name,
            'emails' => collect([
                $user->email,
                $employee?->email,
                $employee?->work_email,
                $employee?->company_email,
                $employee?->personal_email,
            ])->filter()->map(fn ($email) => strtolower(trim((string) $email)))->unique()->values()->all(),
        ];
    }
}
