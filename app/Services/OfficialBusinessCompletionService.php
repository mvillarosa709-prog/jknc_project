<?php

namespace App\Services;

use App\Models\OfficialBusinessTrip;
use Illuminate\Support\Facades\Schema;

class OfficialBusinessCompletionService
{
    /**
     * @return array<int, array{key: string, label: string, complete: bool, detail: string}>
     */
    public function checklist(OfficialBusinessTrip $trip): array
    {
        $items = [
            [
                'key' => 'departure',
                'label' => 'Departure recorded',
                'complete' => $trip->actual_departure !== null,
                'detail' => $trip->actual_departure
                    ? 'Actual departure is recorded.'
                    : 'Record the actual departure before completion.',
            ],
            [
                'key' => 'return',
                'label' => 'Return recorded',
                'complete' => $trip->actual_return !== null,
                'detail' => $trip->actual_return
                    ? 'Actual return is recorded.'
                    : 'Record the actual return before completion.',
            ],
        ];

        if ($trip->is_client_travel) {
            $tickets = Schema::hasTable('official_business_trip_tickets')
                ? $trip->tripTickets()->get(['id', 'reference_no', 'status'])
                : collect();
            $resolvedStatuses = ['completed', 'resolved', 'cancelled'];
            $unresolved = $tickets->reject(
                fn ($ticket): bool => in_array(strtolower((string) $ticket->status), $resolvedStatuses, true)
            );
            $complete = $tickets->isNotEmpty() && $unresolved->isEmpty();

            $items[] = [
                'key' => 'client_trip_tickets',
                'label' => 'Client Trip Tickets resolved',
                'complete' => $complete,
                'detail' => match (true) {
                    $tickets->isEmpty() => 'Create a Trip Ticket for every required client stop.',
                    $unresolved->isNotEmpty() => 'Complete or otherwise resolve: '.$unresolved
                        ->map(fn ($ticket): string => $ticket->reference_no ?: 'Trip Ticket #'.$ticket->id)
                        ->implode(', ').'.',
                    default => 'All client Trip Tickets are completed or otherwise resolved.',
                },
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array{key: string, label: string, complete: bool, detail: string}> $checklist
     */
    public function isReady(array $checklist): bool
    {
        return $checklist !== [] && collect($checklist)->every(
            fn (array $item): bool => $item['complete']
        );
    }

    /**
     * @param array<int, array{key: string, label: string, complete: bool, detail: string}> $checklist
     * @return array<int, array{key: string, label: string, detail: string}>
     */
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
}
