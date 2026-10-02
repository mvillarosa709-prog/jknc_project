<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ActivityAuditLog;
use App\Models\OfficialBusinessTripTicketPersonnel;
use App\Models\OfficialBusinessTripTicketStop;
use App\Models\OfficialBusinessTripTicketCost;
use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OfficialBusinessTripTicketService
{
    /** Explicit internal entry point; never backfills from legacy client strings. */
    public function forClient(User $actor, OfficialBusinessTrip $master, Company $client): OfficialBusinessTripTicket
    {
        return DB::transaction(function () use ($actor, $master, $client): OfficialBusinessTripTicket {
            // Serialize ticket creation for the same master, including concurrent retries.
            $trip = OfficialBusinessTrip::query()->lockForUpdate()->findOrFail($master->getKey());
            Gate::forUser($actor)->authorize('create', [OfficialBusinessTripTicket::class, $trip]);
            $company = Company::query()->findOrFail($client->getKey());

            $ticket = $trip->tripTickets()->withTrashed()->where('company_id', $company->id)->lockForUpdate()->first();
            if ($ticket?->trashed()) {
                return $this->restoreRemovedClientTicket($ticket, $actor, $trip);
            }

            if ($ticket) {
                $this->recordTicketEvent($ticket, $actor, 'client_ticket_synchronized', [
                    'company_id' => $company->id,
                    'created' => false,
                ]);

                return $ticket;
            }

            $ticket = $trip->tripTickets()->create([
                'company_id' => $company->id,
                'created_by_user_id' => $actor->id,
            ]);

            $this->seedNewV3Ticket($trip, $ticket);
            $this->recordTicketEvent($ticket, $actor, 'created', [
                'company_id' => $company->id,
                'client_name' => $ticket->client_name_snapshot,
            ]);
            $this->recordTicketEvent($ticket, $actor, 'client_ticket_synchronized', [
                'company_id' => $company->id,
                'created' => true,
            ]);

            return $ticket;
        });
    }

    /**
     * Synchronize the active client set without destroying ticket history.
     *
     * @param array<int, int|string> $companyIds
     * @return Collection<int, OfficialBusinessTripTicket>
     */
    public function syncClients(User $actor, OfficialBusinessTrip $master, array $companyIds): Collection
    {
        if ((int) $master->workflow_version < 3 || ! $master->is_client_travel) {
            throw ValidationException::withMessages([
                'company_ids' => 'Only V3 Client O.B. records support Trip Ticket synchronization.',
            ]);
        }

        $selectedIds = collect($companyIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        return DB::transaction(function () use ($actor, $master, $selectedIds): Collection {
            $trip = OfficialBusinessTrip::query()->lockForUpdate()->findOrFail($master->getKey());
            Gate::forUser($actor)->authorize('create', [OfficialBusinessTripTicket::class, $trip]);

            $companies = Company::query()
                ->whereIn('id', $selectedIds)
                ->get()
                ->keyBy('id');

            if ($companies->count() !== $selectedIds->count()) {
                throw ValidationException::withMessages([
                    'company_ids' => 'One or more selected clients no longer exist.',
                ]);
            }

            $tickets = $trip->tripTickets()
                ->withTrashed()
                ->lockForUpdate()
                ->get()
                ->keyBy('company_id');

            $removed = $tickets
                ->filter(fn (OfficialBusinessTripTicket $ticket, $companyId): bool =>
                    ! $ticket->trashed() && ! $selectedIds->contains((int) $companyId)
                );

            if ($removed->isNotEmpty() && ! in_array($trip->status, ['Draft', 'Reverted'], true)) {
                throw ValidationException::withMessages([
                    'company_ids' => 'Clients can only be removed while the O.B. is Draft or Reverted.',
                ]);
            }

            foreach ($removed as $ticket) {
                $this->archiveRemovedClientTicket($ticket, $actor);
            }

            return $selectedIds->map(function (int $companyId) use ($actor, $trip, $companies, $tickets): OfficialBusinessTripTicket {
                $ticket = $tickets->get($companyId);

                if ($ticket?->trashed()) {
                    return $this->restoreRemovedClientTicket($ticket, $actor, $trip);
                }

                if ($ticket) {
                    $this->recordTicketEvent($ticket, $actor, 'client_ticket_synchronized', [
                        'company_id' => $companyId,
                        'created' => false,
                    ]);

                    return $ticket;
                }

                $ticket = $trip->tripTickets()->create([
                    'company_id' => $companyId,
                    'created_by_user_id' => $actor->id,
                ]);
                $this->seedNewV3Ticket($trip, $ticket);
                $this->recordTicketEvent($ticket, $actor, 'created', [
                    'company_id' => $companyId,
                    'client_name' => $companies->get($companyId)->company_name,
                ]);
                $this->recordTicketEvent($ticket, $actor, 'client_ticket_synchronized', [
                    'company_id' => $companyId,
                    'created' => true,
                ]);

                return $ticket;
            })->values();
        });
    }

    public function syncCurrentMasterClient(
        User $actor,
        OfficialBusinessTrip $master
    ): ?OfficialBusinessTripTicket {
        if (
            (int) $master->workflow_version < 3
            || ! $master->is_client_travel
            || ! $master->company_id
        ) {
            return null;
        }

        return $this->forClient(
            $actor,
            $master,
            Company::query()->findOrFail($master->company_id)
        );
    }

    /**
     * @param null|array<int, array<string, mixed>> $stops
     * @param null|array<int, array<string, mixed>> $personnel
     */
    public function updateDetails(
        User $actor,
        OfficialBusinessTripTicket $ticket,
        ?array $stops,
        ?array $personnel
    ): OfficialBusinessTripTicket {
        Gate::forUser($actor)->authorize('update', $ticket);
        $this->ensureMutable($ticket);

        return DB::transaction(function () use ($actor, $ticket, $stops, $personnel): OfficialBusinessTripTicket {
            $locked = OfficialBusinessTripTicket::query()->lockForUpdate()->findOrFail($ticket->getKey());

            if ($stops !== null) {
                $this->syncStops($locked, $stops);
                $this->recordTicketEvent($locked, $actor, 'stops_updated', [
                    'stop_count' => count($stops),
                    'sequences' => collect($stops)->pluck('sequence')->values()->all(),
                ]);
            }

            if ($personnel !== null) {
                $this->syncPersonnel($locked, $personnel);
                $this->recordTicketEvent($locked, $actor, 'personnel_updated', [
                    'personnel_count' => count($personnel),
                    'employee_ids' => collect($personnel)->pluck('employee_id')->values()->all(),
                ]);
            }

            return $locked->fresh(['company', 'stops', 'personnel']);
        });
    }

    public function updateFinancialTreatment(
        User $actor,
        OfficialBusinessTripTicket $ticket,
        array $attributes
    ): OfficialBusinessTripTicket {
        Gate::forUser($actor)->authorize('finance', $ticket);
        $this->ensureMutable($ticket);

        if (
            in_array($attributes['billing_treatment'], ['Travel Credit', 'Travel Credit + Additional Cost'], true)
            && (float) ($attributes['travel_credit_units'] ?? 0) <= 0
        ) {
            throw ValidationException::withMessages([
                'travel_credit_units' => 'Travel credit units are required when travel credit is used.',
            ]);
        }

        if (! ($attributes['evidence_required'] ?? true) && blank($attributes['evidence_requirement_notes'] ?? null)) {
            throw ValidationException::withMessages([
                'evidence_requirement_notes' => 'Explain why evidence is not required.',
            ]);
        }

        if ($attributes['billing_treatment'] === 'Other' && blank($attributes['notes'] ?? null)) {
            throw ValidationException::withMessages([
                'notes' => 'Describe the Other billing treatment.',
            ]);
        }

        return DB::transaction(function () use ($actor, $ticket, $attributes): OfficialBusinessTripTicket {
            $locked = OfficialBusinessTripTicket::query()
                ->with(['company', 'financialTreatment.travelCreditEntry'])
                ->lockForUpdate()
                ->findOrFail($ticket->id);
            $usesCredit = in_array($attributes['billing_treatment'], ['Travel Credit', 'Travel Credit + Additional Cost'], true);
            $currentEntry = $locked->financialTreatment?->travelCreditEntry;
            $entry = app(CompanyTravelCreditService::class)->applyTicketUsage(
                $actor,
                $locked,
                $currentEntry,
                $usesCredit ? (float) $attributes['travel_credit_units'] : null,
                $usesCredit && filled($attributes['travel_credit_value'] ?? null)
                    ? (float) $attributes['travel_credit_value']
                    : null
            );

            $before = $locked->financialTreatment?->only([
                'billing_treatment', 'travel_credit_units', 'travel_credit_value',
                'travel_credit_entry_id', 'evidence_required', 'additional_costs_status', 'notes',
            ]) ?? [];
            $attributes['travel_credit_units'] = $usesCredit ? abs((float) $entry->units_delta) : null;
            $attributes['travel_credit_value'] = $usesCredit ? $entry->unit_value : null;
            $attributes['travel_credit_entry_id'] = $entry?->id;
            $treatment = $locked->financialTreatment()->updateOrCreate([], [
                ...$attributes,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
            ]);

            $after = $treatment->only([
                'billing_treatment', 'travel_credit_units', 'travel_credit_value',
                'travel_credit_entry_id', 'evidence_required', 'additional_costs_status', 'notes',
            ]);
            if ($before !== $after) {
                $this->recordTicketEvent(
                    $locked,
                    $actor,
                    'billing_treatment_updated',
                    ['billing_treatment' => $treatment->billing_treatment],
                    $before,
                    $after
                );
            }

            if ($entry && (! $currentEntry || $entry->id !== $currentEntry->id)) {
                $this->recordTicketEvent($locked, $actor, 'travel_credit_used', [
                    'ledger_entry_id' => $entry->id,
                    'units' => abs((float) $entry->units_delta),
                    'unit_value' => $entry->unit_value,
                    'balance_after' => $entry->balance_after,
                ]);
            }
            if ($currentEntry && (! $entry || $entry->id !== $currentEntry->id)) {
                $this->recordTicketEvent($locked, $actor, 'travel_credit_reversed', [
                    'ledger_entry_id' => $currentEntry->id,
                    'units' => abs((float) $currentEntry->units_delta),
                ]);
            }

            return $locked->fresh(['financialTreatment.travelCreditEntry', 'costs']);
        });
    }

    public function addCost(
        User $actor,
        OfficialBusinessTripTicket $ticket,
        array $attributes
    ): OfficialBusinessTripTicketCost {
        Gate::forUser($actor)->authorize('finance', $ticket);
        $this->ensureMutable($ticket);

        $cost = $ticket->costs()->create([
            ...$attributes,
            'recorded_by_user_id' => $actor->id,
        ]);

        $treatment = $ticket->financialTreatment()->first();

        if ($treatment && $treatment->additional_costs_status !== 'Resolved') {
            $treatment->forceFill([
                'additional_costs_status' => 'Resolved',
            ])->save();
        }

        $this->recordTicketEvent($ticket, $actor, 'additional_cost_added', [
            'cost_id' => $cost->id,
            'category' => $cost->category,
            'amount' => $cost->amount,
            'billing_treatment' => $cost->billing_treatment,
        ]);

        return $cost;
    }

    public function deleteCost(
        User $actor,
        OfficialBusinessTripTicket $ticket,
        OfficialBusinessTripTicketCost $cost
    ): void {
        Gate::forUser($actor)->authorize('finance', $ticket);
        $this->ensureMutable($ticket);
        abort_unless((int) $cost->official_business_trip_ticket_id === (int) $ticket->id, 404);

        $costId = $cost->id;
        $cost->delete();
        $this->recordTicketEvent($ticket, $actor, 'additional_cost_removed', ['cost_id' => $costId]);
    }

    public function updateCost(
        User $actor,
        OfficialBusinessTripTicket $ticket,
        OfficialBusinessTripTicketCost $cost,
        array $attributes
    ): OfficialBusinessTripTicketCost {
        Gate::forUser($actor)->authorize('finance', $ticket);
        $this->ensureMutable($ticket);
        abort_unless((int) $cost->official_business_trip_ticket_id === (int) $ticket->id, 404);

        $before = $cost->only(['category', 'description', 'amount', 'incurred_on', 'billing_treatment', 'evidence_reference']);
        $cost->update($attributes);
        $after = $cost->fresh()->only(['category', 'description', 'amount', 'incurred_on', 'billing_treatment', 'evidence_reference']);
        $this->recordTicketEvent(
            $ticket,
            $actor,
            'additional_cost_updated',
            ['cost_id' => $cost->id],
            $before,
            $after
        );

        return $cost->fresh();
    }

    public function restoreArchived(User $actor, int $ticketId): OfficialBusinessTripTicket
    {
        $ticket = OfficialBusinessTripTicket::withTrashed()->findOrFail($ticketId);
        Gate::forUser($actor)->authorize('restore', $ticket);

        $master = $ticket->officialBusinessTrip;

        if (
            $master
            && (int) $master->workflow_version >= 3
            && in_array($master->status, ['Completed', 'Cancelled', 'Rejected'], true)
        ) {
            throw ValidationException::withMessages([
                'official_business_trip_id' => 'Archived V3 Trip Tickets cannot be restored after the master O.B. reaches a terminal status.',
            ]);
        }

        $ticket->restore();
        $ticket->forceFill([
            'status' => 'Draft',
            'archived_reason' => null,
            'archived_by_user_id' => null,
            'archived_at' => null,
        ])->save();
        $this->recordTicketEvent($ticket, $actor, 'restored', ['status' => 'Draft']);

        return $ticket->fresh(['company', 'stops', 'personnel', 'financialTreatment', 'costs', 'evidence.uploadedBy']);
    }

    private function recordTicketEvent(
        OfficialBusinessTripTicket $ticket,
        User $actor,
        string $action,
        array $details = [],
        array $before = [],
        array $after = []
    ): void {
        app(ActivityAuditService::class)->recordSemantic(
            ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET,
            $ticket->id,
            $action,
            $ticket->reference_no,
            $details,
            $before,
            $after,
            $actor->id
        );
    }

    private function ensureMutable(OfficialBusinessTripTicket $ticket): void
    {
        if (in_array(strtolower((string) $ticket->status), ['completed', 'resolved', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Completed, resolved, or cancelled Trip Tickets cannot be changed.',
            ]);
        }
    }

    private function archiveRemovedClientTicket(OfficialBusinessTripTicket $ticket, User $actor): void
    {
        $this->assertSafeToArchiveForClientRemoval($ticket);

        $ticket->forceFill([
            'status' => 'Cancelled',
            'archived_reason' => 'client_removed_from_master',
            'archived_by_user_id' => $actor->id,
            'archived_at' => now(),
        ])->save();
        $ticket->delete();

        $this->recordTicketEvent($ticket, $actor, 'client_removed', [
            'company_id' => $ticket->company_id,
            'archived_reason' => 'client_removed_from_master',
        ]);
        $this->recordTicketEvent($ticket, $actor, 'deleted', [
            'status' => 'Cancelled',
            'archived_reason' => 'client_removed_from_master',
        ]);
    }

    private function restoreRemovedClientTicket(
        OfficialBusinessTripTicket $ticket,
        User $actor,
        OfficialBusinessTrip $trip
    ): OfficialBusinessTripTicket {
        if (
            $ticket->archived_reason !== 'client_removed_from_master'
            || ! in_array($trip->status, ['Draft', 'Reverted'], true)
        ) {
            throw ValidationException::withMessages([
                'company_id' => 'This client already has an archived Trip Ticket. Restore it through the authorized recovery workflow.',
            ]);
        }

        $ticket->restore();
        $ticket->forceFill([
            'status' => 'Draft',
            'archived_reason' => null,
            'archived_by_user_id' => null,
            'archived_at' => null,
        ])->save();
        $this->recordTicketEvent($ticket, $actor, 'restored', [
            'company_id' => $ticket->company_id,
            'reason' => 'client_readded_to_master',
        ]);

        return $ticket;
    }

    private function assertSafeToArchiveForClientRemoval(OfficialBusinessTripTicket $ticket): void
    {
        $hasResolvedStop = $ticket->stops()
            ->withTrashed()
            ->where(function ($query): void {
                $query->whereNotNull('actual_arrival_at')
                    ->orWhereNotNull('resolved_at')
                    ->orWhereNotIn('resolution_status', ['Planned', 'Cancelled']);
            })
            ->exists();
        $hasSemanticWork = Schema::hasTable('activity_audit_logs')
            && ActivityAuditLog::query()
                ->where('activity_type', ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET)
                ->where('activity_id', $ticket->id)
                ->whereIn('action', [
                    'stops_updated',
                    'personnel_updated',
                    'evidence_uploaded',
                    'billing_treatment_updated',
                    'travel_credit_used',
                    'travel_credit_reversed',
                    'additional_cost_added',
                    'completed',
                ])
                ->exists();

        if (
            ! in_array($ticket->status, ['Draft', 'Reverted'], true)
            || $ticket->financialTreatment()->exists()
            || $ticket->costs()->withTrashed()->exists()
            || $ticket->evidence()->exists()
            || $hasResolvedStop
            || $hasSemanticWork
        ) {
            throw ValidationException::withMessages([
                'company_ids' => "{$ticket->client_name_snapshot} cannot be removed because its Trip Ticket has meaningful lifecycle activity. Cancel or resolve it explicitly instead.",
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $stops */
    private function syncStops(OfficialBusinessTripTicket $ticket, array $stops): void
    {
        $sequences = collect($stops)->pluck('sequence')->map(fn ($value): int => (int) $value)->all();

        foreach ($stops as $attributes) {
            $sequence = (int) $attributes['sequence'];
            $stop = $ticket->stops()->withTrashed()->where('sequence', $sequence)->first();

            if ($stop?->trashed()) {
                $stop->restore();
            }

            $stop ??= new OfficialBusinessTripTicketStop([
                'official_business_trip_ticket_id' => $ticket->id,
                'sequence' => $sequence,
            ]);

            $stop->fill([
                'destination' => $attributes['destination'],
                'activity' => $attributes['activity'] ?? null,
                'planned_at' => $attributes['planned_at'] ?? null,
                'actual_arrival_at' => $attributes['actual_arrival_at'] ?? null,
                'resolved_at' => $attributes['resolved_at'] ?? null,
                'resolution_status' => $attributes['resolution_status'] ?? 'Planned',
            ])->save();
        }

        $ticket->stops()->when(
            $sequences !== [],
            fn ($query) => $query->whereNotIn('sequence', $sequences)
        )->delete();
    }

    /** @param array<int, array<string, mixed>> $personnel */
    private function syncPersonnel(OfficialBusinessTripTicket $ticket, array $personnel): void
    {
        $employeeIds = collect($personnel)->pluck('employee_id')->map(fn ($value): int => (int) $value)->all();

        if (Schema::hasTable('official_business_trip_personnel')) {
            $master = $ticket->officialBusinessTrip;
            $masterIds = $master->personnel()->pluck('employee_id')->map(fn ($id): int => (int) $id)->all();
            if ($masterIds !== [] && array_diff($employeeIds, $masterIds) !== []) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'personnel' => 'Trip Ticket personnel must be selected from the master O.B. team.',
                ]);
            }
        }

        foreach ($personnel as $attributes) {
            $employeeId = (int) $attributes['employee_id'];
            $member = $ticket->personnel()->withTrashed()->where('employee_id', $employeeId)->first();

            if ($member?->trashed()) {
                $member->restore();
            }

            $member ??= new OfficialBusinessTripTicketPersonnel([
                'official_business_trip_ticket_id' => $ticket->id,
                'employee_id' => $employeeId,
            ]);

            $member->fill([
                'role' => $attributes['role'] ?? 'Traveler',
                'participation_status' => $attributes['participation_status'] ?? 'Assigned',
            ])->save();
        }

        $ticket->personnel()->when(
            $employeeIds !== [],
            fn ($query) => $query->whereNotIn('employee_id', $employeeIds)
        )->delete();
    }

    private function seedNewV3Ticket(
        OfficialBusinessTrip $trip,
        OfficialBusinessTripTicket $ticket
    ): void {
        if ((int) $trip->workflow_version < 3) {
            return;
        }

        if (Schema::hasTable('official_business_trip_ticket_stops') && filled($trip->destination)) {
            $plannedAt = null;

            if ($trip->date_from) {
                $plannedAt = Carbon::parse(
                    $trip->date_from->format('Y-m-d').' '.($trip->departure_time ?: '00:00:00')
                );
            }

            $ticket->stops()->create([
                'sequence' => 1,
                'destination' => $trip->destination,
                'activity' => $trip->purpose,
                'planned_at' => $plannedAt,
                'resolution_status' => 'Planned',
            ]);
        }

        if (Schema::hasTable('official_business_trip_ticket_personnel') && $trip->employee_id) {
            $ticket->personnel()->create([
                'employee_id' => $trip->employee_id,
                'role' => 'Filer',
                'participation_status' => 'Assigned',
            ]);
        }
    }
}
