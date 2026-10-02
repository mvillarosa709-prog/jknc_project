<?php

namespace App\Services;

use App\Models\OfficialBusinessTrip;
use App\Models\User;

class OfficialBusinessAuthorizationService
{
    public const CREATE = 'create_hc_obf';
    public const APPROVE = 'approve_hc_obf';
    public const TRAVEL_CONTROL = 'control_hc_obf_travel';
    public const MANAGE = 'manage_hc_obf';
    public const FINANCE = 'access_hc_obf_finance';
    public const ADMIN = 'admin_hc_obf';

    public function canCreateOwn(?User $user): bool
    {
        return $this->eligible($user)
            && ($user->isEmployee() || $this->hasOperationalCapability($user, self::CREATE));
    }

    /**
     * Read visibility is separate from every mutation capability.
     */
    public function canView(?User $user, ?OfficialBusinessTrip $trip = null): bool
    {
        if (! $this->eligible($user)) {
            return false;
        }

        return $trip === null || ! $trip->trashed();
    }

    public function canApprove(?User $user): bool
    {
        return $this->eligible($user) && $this->hasOperationalCapability($user, self::APPROVE);
    }

    public function canControlTravel(?User $user, ?OfficialBusinessTrip $trip = null): bool
    {
        if (! $this->eligible($user)) {
            return false;
        }

        if ($this->hasOperationalCapability($user, self::TRAVEL_CONTROL)) {
            return true;
        }

        if ($trip === null) {
            return false;
        }

        if ((int) $trip->workflow_version >= 3) {
            $movementEmployeeId = \App\Models\Employee::query()
                ->where('user_id', $user->id)
                ->value('id');

            $isAssignedTraveler = $movementEmployeeId
                && (int) ($trip->employee_id ?? 0) === (int) $movementEmployeeId;

            $creatorUserId = (int) (
                $trip->created_by_user_id
                ?? $trip->created_by
                ?? 0
            );

            $isCreator = $creatorUserId > 0
                && $creatorUserId === (int) $user->id;

            return $isAssignedTraveler || $isCreator;
        }

        return (int) $trip->created_by === (int) $user->id
            || $trip->employee()->where('user_id', $user->id)->exists();
    }

    public function canManage(?User $user): bool
    {
        return $this->eligible($user) && $this->hasOperationalCapability($user, self::MANAGE);
    }

    public function canEdit(?User $user, OfficialBusinessTrip $trip): bool
    {
        if (! $this->eligible($user)) {
            return false;
        }

        $editable = (int) $trip->workflow_version >= 3
            ? in_array($trip->status, ['Draft', 'Reverted'], true)
            : $trip->status === 'Pending';

        return $editable && (
            $this->canManage($user)
            || (int) $trip->created_by === (int) $user->id
            || $trip->employee()->where('user_id', $user->id)->exists()
        );
    }

    public function canReadFinance(?User $user): bool
    {
        return $this->eligible($user) && (
            $this->hasOperationalCapability($user, self::FINANCE)
            || $user->hasPermission('access_finance')
        );
    }

    public function canAdminister(?User $user): bool
    {
        return $this->eligible($user)
            && ($user->isSuperAdmin() || $user->isAdmin() || $user->hasPermission(self::ADMIN));
    }

    private function hasOperationalCapability(User $user, string $permission): bool
    {
        return $user->isSuperAdmin()
            || $user->isAdmin()
            || $user->hasPermission($permission);
    }

    private function eligible(?User $user): bool
    {
        return $user !== null && $user->exists && ! $user->isDisabled() && ! $user->isClient();
    }
}
