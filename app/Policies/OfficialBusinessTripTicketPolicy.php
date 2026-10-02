<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripTicket;
use App\Models\User;
use App\Services\OfficialBusinessAuthorizationService;

class OfficialBusinessTripTicketPolicy
{
    public function viewAny(User $user, OfficialBusinessTrip $trip): bool
    {
        if (! $this->activeUser($user) || $trip->trashed()) {
            return false;
        }

        if ($user->isClient()) {
            return $trip->tripTickets()
                ->where('status', 'Completed')
                ->whereIn('company_id', $this->clientCompanyIds($user))
                ->exists();
        }

        return app(OfficialBusinessAuthorizationService::class)->canView($user, $trip)
            || $this->canOperateMaster($user, $trip)
            || $trip->tripTickets()->whereHas(
                'personnel.employee',
                fn ($query) => $query->where('user_id', $user->id)
            )->exists();
    }

    public function create(User $user, OfficialBusinessTrip $trip): bool
    {
        return $trip->is_client_travel
            && $this->eligible($user, $trip)
            && $this->canOperateMaster($user, $trip);
    }

    public function view(User $user, OfficialBusinessTripTicket $ticket): bool
    {
        if ($ticket->trashed() || $ticket->officialBusinessTrip === null
            || $ticket->officialBusinessTrip->trashed() || ! $this->activeUser($user)) {
            return false;
        }

        if ($user->isClient()) {
            return strtolower((string) $ticket->status) === 'completed'
                && in_array((int) $ticket->company_id, $this->clientCompanyIds($user), true);
        }

        return app(OfficialBusinessAuthorizationService::class)->canView($user, $ticket->officialBusinessTrip)
            || $this->canOperateMaster($user, $ticket->officialBusinessTrip)
            || $ticket->personnel()->whereHas(
                'employee',
                fn ($query) => $query->where('user_id', $user->id)
            )->exists()
            || (
                strtolower((string) $ticket->status) === 'completed'
                && app(OfficialBusinessAuthorizationService::class)->canReadFinance($user)
            );
    }

    public function update(User $user, OfficialBusinessTripTicket $ticket): bool
    {
        return ! $ticket->trashed()
            && $ticket->officialBusinessTrip !== null
            && $this->eligible($user, $ticket->officialBusinessTrip)
            && $this->canOperateMaster($user, $ticket->officialBusinessTrip);
    }

    public function delete(User $user, OfficialBusinessTripTicket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function finance(User $user, OfficialBusinessTripTicket $ticket): bool
    {
        return ! $ticket->trashed()
            && $ticket->officialBusinessTrip !== null
            && $this->eligible($user, $ticket->officialBusinessTrip)
            && app(OfficialBusinessAuthorizationService::class)->canReadFinance($user);
    }

    public function restore(User $user, OfficialBusinessTripTicket $ticket): bool
    {
        return $ticket->trashed()
            && $ticket->officialBusinessTrip !== null
            && ! $ticket->officialBusinessTrip->trashed()
            && app(OfficialBusinessAuthorizationService::class)->canAdminister($user);
    }

    private function eligible(User $user, OfficialBusinessTrip $trip): bool
    {
        if (! $this->activeUser($user) || $user->isClient() || $trip->trashed()) {
            return false;
        }

        return true;
    }

    private function canOperateMaster(User $user, OfficialBusinessTrip $trip): bool
    {
        $authorization = app(OfficialBusinessAuthorizationService::class);

        return $authorization->canManage($user)
            || (int) $trip->created_by === (int) $user->id
            || $trip->employee()->where('user_id', $user->id)->exists();
    }

    private function activeUser(User $user): bool
    {
        return $user->exists && ! $user->isDisabled();
    }

    /** @return array<int, int> */
    private function clientCompanyIds(User $user): array
    {
        return Company::query()
            ->where(function ($query) use ($user): void {
                $query->whereHas('contacts', fn ($contacts) => $contacts->where('user_id', $user->id))
                    ->orWhereHas('primaryContact', fn ($contact) => $contact->where('user_id', $user->id));
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
