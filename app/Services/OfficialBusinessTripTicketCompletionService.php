<?php

namespace App\Services;

use App\Models\ActivityAttachment;
use App\Models\OfficialBusinessTripTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class OfficialBusinessTripTicketCompletionService
{
    public const BILLING_TREATMENTS = [
        'Travel Credit',
        'Travel Credit + Additional Cost',
        'Billable to Client',
        'Included in Retainer',
        'Non-billable',
        'Management Approved',
        'Other',
    ];

    public const EVIDENCE_TYPES = [
        'Photo',
        'Government Acknowledgment Slip',
        'Receiving Copy',
        'Official Receipt',
        'Courier Receipt',
        'Filing Confirmation',
        'Email or Screenshot',
        'Meeting Notes',
        'Other Client-Specific Evidence',
    ];

    /** @return array<int, array{key: string, label: string, complete: bool, detail: string}> */
    public function checklist(OfficialBusinessTripTicket $ticket): array
    {
        $ticket->loadMissing(['officialBusinessTrip', 'stops', 'personnel', 'financialTreatment', 'costs']);
        $master = $ticket->officialBusinessTrip;
        $masterStatus = strtolower((string) $master?->status);
        $masterReadyForTicketCompletion = in_array($masterStatus, ['for completion', 'completed'], true);
        $stops = $ticket->stops;
        $personnel = $ticket->personnel;
        $treatment = $ticket->financialTreatment;
        $costs = $ticket->costs;

        $unresolvedStops = $stops->reject(
            fn ($stop): bool => in_array(strtolower((string) $stop->resolution_status), ['resolved', 'cancelled'], true)
        );
        $unresolvedPersonnel = $personnel->reject(
            fn ($member): bool => in_array(strtolower((string) $member->participation_status), ['confirmed', 'did not go'], true)
        );
        $hasConfirmedPersonnel = $personnel->contains(
            fn ($member): bool => strtolower((string) $member->participation_status) === 'confirmed'
        );
        $evidenceCount = Schema::hasTable('activity_attachments')
            ? ActivityAttachment::query()
                ->where('activity_type', ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET)
                ->where('activity_id', $ticket->id)
                ->count()
            : 0;
        $usesCredit = $treatment && in_array($treatment->billing_treatment, [
            'Travel Credit',
            'Travel Credit + Additional Cost',
        ], true);
        $creditPosted = ! $usesCredit
            || ! Schema::hasColumn('official_business_trip_ticket_financial_treatments', 'travel_credit_entry_id')
            || filled($treatment?->travel_credit_entry_id);
        $financialReady = $treatment !== null
            && in_array($treatment->billing_treatment, self::BILLING_TREATMENTS, true)
            && (! $usesCredit || (
                (float) $treatment->travel_credit_units > 0
                && (float) $treatment->travel_credit_value > 0
                && $creditPosted
            ))
            && ($treatment->billing_treatment !== 'Other' || filled($treatment->notes));
        $costsReady = $treatment !== null
            && in_array($treatment->additional_costs_status, ['Not Applicable', 'Resolved'], true)
            && (! $costs->isNotEmpty() || $treatment->additional_costs_status === 'Resolved')
            && ($treatment->billing_treatment !== 'Travel Credit + Additional Cost' || $costs->isNotEmpty());
        $evidenceRequired = $treatment?->evidence_required ?? true;
        $evidenceReady = $evidenceRequired
            ? $evidenceCount > 0
            : filled($treatment?->evidence_requirement_notes);

        return [
            [
                'key' => 'master_for_completion',
                'label' => $masterStatus === 'completed'
                    ? 'Master O.B. is Completed'
                    : ($masterStatus === 'for completion'
                        ? 'Master O.B. is For Completion'
                        : 'Master O.B. must be For Completion'),
                'complete' => $master !== null
                    && (int) $master->workflow_version >= 3
                    && $master->is_client_travel
                    && $masterReadyForTicketCompletion,
                'detail' => $masterStatus === 'completed'
                    ? 'The parent Official Business is Completed.'
                    : ($masterStatus === 'for completion'
                        ? 'The parent Official Business is For Completion.'
                        : 'The parent Official Business must be returned and moved to For Completion before this Trip Ticket can be completed.'),
            ],
            [
                'key' => 'stops',
                'label' => 'Client stops resolved',
                'complete' => $stops->isNotEmpty() && $unresolvedStops->isEmpty(),
                'detail' => match (true) {
                    $stops->isEmpty() => 'Add at least one client stop.',
                    $unresolvedStops->isNotEmpty() => 'Resolve or cancel every client stop.',
                    default => 'All client stops are resolved.',
                },
            ],
            [
                'key' => 'personnel',
                'label' => 'Client-specific personnel confirmed',
                'complete' => $personnel->isNotEmpty() && $unresolvedPersonnel->isEmpty() && $hasConfirmedPersonnel,
                'detail' => match (true) {
                    $personnel->isEmpty() => 'Add the personnel who went for this client.',
                    $unresolvedPersonnel->isNotEmpty() => 'Confirm or resolve every assigned person.',
                    ! $hasConfirmedPersonnel => 'At least one person must be confirmed as having gone for this client.',
                    default => 'Client-specific personnel are confirmed.',
                },
            ],
            [
                'key' => 'evidence',
                'label' => 'Required evidence recorded',
                'complete' => $evidenceReady,
                'detail' => match (true) {
                    $evidenceRequired && $evidenceCount === 0 => 'Upload or reference at least one item of client-specific evidence.',
                    ! $evidenceRequired && blank($treatment?->evidence_requirement_notes) => 'Explain why evidence is not required.',
                    default => 'The evidence requirement is satisfied.',
                },
            ],
            [
                'key' => 'financial_treatment',
                'label' => 'Billing and travel credit treatment resolved',
                'complete' => $financialReady,
                'detail' => match (true) {
                    $treatment === null => 'Select the ticket billing treatment.',
                    $usesCredit && ! $creditPosted => 'Post the travel-credit deduction to the client ledger.',
                    $usesCredit && ((float) $treatment->travel_credit_units <= 0 || (float) $treatment->travel_credit_value <= 0) => 'Record the travel credit units and value used.',
                    $treatment->billing_treatment === 'Other' && blank($treatment->notes) => 'Describe the Other billing treatment.',
                    default => 'Billing and travel credit treatment is resolved.',
                },
            ],
            [
                'key' => 'additional_costs',
                'label' => 'Additional costs resolved',
                'complete' => $costsReady,
                'detail' => match (true) {
                    $treatment === null => 'Resolve financial treatment before additional costs.',
                    $treatment->billing_treatment === 'Travel Credit + Additional Cost' && $costs->isEmpty() => 'Record the applicable additional cost.',
                    $costs->isNotEmpty() && $treatment->additional_costs_status !== 'Resolved' => 'Mark recorded additional costs as Resolved.',
                    ! in_array($treatment->additional_costs_status, ['Not Applicable', 'Resolved'], true) => 'Resolve additional costs or mark them Not Applicable.',
                    default => 'Additional costs are resolved.',
                },
            ],
        ];
    }

    public function isReady(array $checklist): bool
    {
        return $checklist !== [] && collect($checklist)->every(fn (array $item): bool => $item['complete']);
    }

    public function missingRequirements(array $checklist): array
    {
        return collect($checklist)
            ->reject(fn (array $item): bool => $item['complete'])
            ->map(fn (array $item): array => [
                'key' => $item['key'],
                'label' => $item['label'],
                'detail' => $item['detail'],
            ])
            ->values()
            ->all();
    }

    /** @return array{ticket: OfficialBusinessTripTicket, completion_checklist: array, completion_ready: bool} */
    public function complete(User $actor, OfficialBusinessTripTicket $ticket): array
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        return DB::transaction(function () use ($actor, $ticket): array {
            $locked = OfficialBusinessTripTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if (strtolower((string) $locked->status) === 'completed') {
                return [
                    'ticket' => $locked,
                    'completion_checklist' => [],
                    'completion_ready' => true,
                ];
            }

            $checklist = $this->checklist($locked);
            $ready = $this->isReady($checklist);
            app(ActivityAuditService::class)->recordSemantic(
                ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET,
                $locked->id,
                'completion_checklist_evaluated',
                $locked->reference_no,
                [
                    'completion_ready' => $ready,
                    'missing_requirement_keys' => collect($this->missingRequirements($checklist))->pluck('key')->all(),
                ],
                actorId: $actor->id
            );
            if ($ready) {
                $locked->forceFill(['status' => 'Completed'])->save();
                app(ActivityAuditService::class)->recordSemantic(
                    ActivityTypeRegistry::OFFICIAL_BUSINESS_TRIP_TICKET,
                    $locked->id,
                    'completed',
                    $locked->reference_no,
                    ['status' => 'Completed'],
                    actorId: $actor->id
                );
            }

            return [
                'ticket' => $locked->fresh(),
                'completion_checklist' => $checklist,
                'completion_ready' => $ready,
            ];
        });
    }
}
