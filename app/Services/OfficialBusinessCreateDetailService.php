<?php

namespace App\Services;

use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripDetail;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OfficialBusinessCreateDetailService
{
    public function decode(?string $json): array
    {
        $rows = json_decode($json ?: '[]', true);
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw ValidationException::withMessages(['details_json' => 'O.B. Details must be a list.']);
        }

        return $rows;
    }

    public function validateRows(array $rows, string $classification, bool $isDraft): array
    {
        $rules = [
            'rows' => ['array', 'max:50'],
            'rows.*.id' => ['nullable', 'integer'],
            'rows.*.client_key' => ['nullable', 'string', 'max:80'],
            'rows.*.from_location' => [$isDraft ? 'nullable' : 'required', 'string', 'max:255'],
            'rows.*.destination' => [$isDraft ? 'nullable' : 'required', 'string', 'max:255'],
            'rows.*.purpose' => [$isDraft ? 'nullable' : 'required', 'string', 'max:2000'],
            'rows.*.travel' => ['nullable', 'array'],
            'rows.*.travel.departure_date' => ['nullable', 'date'],
            'rows.*.travel.departure_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'rows.*.travel.expected_arrival_date' => ['nullable', 'date'],
            'rows.*.travel.expected_arrival_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'rows.*.travel.estimated_expense' => ['nullable', 'numeric', 'min:0'],
            'rows.*.transportation' => ['nullable', 'array'],
            'rows.*.transportation.mode' => ['nullable', Rule::in(config('official_business.transportation_modes'))],
        ];
        foreach ([
            'vehicle', 'plate_number', 'driver', 'owner_driver', 'provider', 'transport_type',
            'rental_provider', 'airline', 'flight_number', 'shipping_line', 'vessel_trip', 'other_details',
        ] as $field) {
            $rules['rows.*.transportation.'.$field] = ['nullable', 'string', 'max:2000'];
        }

        if ($classification === OfficialBusinessTrip::CLASSIFICATION_CLIENT) {
            $rules += [
                'rows.*.company_id' => [$isDraft ? 'nullable' : 'required', 'integer', 'exists:companies,id'],
                'rows.*.engagement_type' => [$isDraft ? 'nullable' : 'required', Rule::in(['Regular', 'Project'])],
                'rows.*.project_id' => [$isDraft ? 'nullable' : 'required', 'integer', 'exists:projects,id'],
            ];
        } elseif ($classification === OfficialBusinessTrip::CLASSIFICATION_INTERNAL) {
            $rules += [
                'rows.*.internal_activity_type' => [$isDraft ? 'nullable' : 'required', Rule::in(config('official_business.internal_activity_types'))],
                'rows.*.department_id' => [$isDraft ? 'nullable' : 'required', 'integer', 'exists:departments,id'],
                'rows.*.related_internal_type' => ['nullable', 'string', 'max:120'],
                'rows.*.related_internal_id' => ['nullable', 'integer', 'min:1'],
            ];
        } else {
            $rules += [
                'rows.*.activity_event_name' => [$isDraft ? 'nullable' : 'required', 'string', 'max:255'],
                'rows.*.social_activity_type' => [$isDraft ? 'nullable' : 'required', Rule::in(config('official_business.social_activity_types'))],
                'rows.*.organization_host' => ['nullable', 'string', 'max:255'],
                'rows.*.contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
                'rows.*.related_contact_text' => ['nullable', 'string', 'max:255'],
            ];
        }

        if (! $isDraft && $rows === []) {
            throw ValidationException::withMessages(['details_json' => 'Add at least one O.B. Detail before submitting.']);
        }
        $validated = Validator::make(['rows' => $rows], $rules)->validate()['rows'];
        $errors = [];

        foreach ($validated as $index => &$row) {
            $row['travel'] = $row['travel'] ?? [];
            $row['transportation'] = $row['transportation'] ?? [];
            if (! $isDraft) {
                foreach ([
                    'departure_date' => 'Departure Date',
                    'departure_time' => 'Departure Time',
                    'expected_arrival_date' => 'Expected Arrival Date',
                    'expected_arrival_time' => 'Expected Arrival Time',
                ] as $field => $label) {
                    if (! filled($row['travel'][$field] ?? null)) {
                        $errors['details_json'][] = 'O.B. Detail '.($index + 1).' '.$label.' is required.';
                    }
                }

                $mode = $row['transportation']['mode'] ?? null;
                if (! filled($mode)) {
                    $errors['details_json'][] = 'O.B. Detail '.($index + 1).' Mode of Transportation is required.';
                } else {
                    foreach ($this->requiredTransportationFields($mode) as $field => $label) {
                        if (! filled($row['transportation'][$field] ?? null)) {
                            $errors['details_json'][] = 'O.B. Detail '.($index + 1).' '.$label.' is required.';
                        }
                    }
                }
            }

            if ($classification !== OfficialBusinessTrip::CLASSIFICATION_CLIENT || ! filled($row['project_id'] ?? null)) {
                continue;
            }
            $project = Project::query()->whereKey($row['project_id'])->first();
            if (! $project || (int) $project->company_id !== (int) ($row['company_id'] ?? 0)
                || $project->isShell()
                || in_array(strtolower((string) $project->status), ['completed', 'cancelled', 'archived', 'rejected'], true)
                || (str_contains(strtolower((string) $project->engagement_type), 'regular') !== (($row['engagement_type'] ?? null) === 'Regular'))) {
                $errors["rows.{$index}.project_id"] = 'Select an active engagement belonging to this client and type.';
                continue;
            }
            $row['deal_id'] = $project->deal_id;
        }
        unset($row);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $validated;
    }

    public function sync(OfficialBusinessTrip $trip, string $classification, array $rows, bool $confirmedChange = false): void
    {
        DB::transaction(function () use ($trip, $classification, $rows, $confirmedChange): void {
            $existing = $trip->details()->get()->keyBy('id');
            $classificationChanged = $existing->contains(fn ($detail) => $detail->classification !== $classification);

            if ($classificationChanged && $existing->isNotEmpty() && ! $confirmedChange) {
                throw ValidationException::withMessages([
                    'classification' => 'Confirm removal of existing O.B. Detail entries before changing classification.',
                ]);
            }

            $detailService = app(OfficialBusinessDetailService::class);
            if ($classificationChanged) {
                foreach ($existing as $detail) {
                    $detailService->delete($detail);
                }
                $existing = collect();
            }

            $keptIds = [];
            foreach ($rows as $index => $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id && ! $existing->has($id)) {
                    throw ValidationException::withMessages(["rows.{$index}.id" => 'This O.B. Detail does not belong to the current draft.']);
                }
                $attributes = $row + ['classification' => $classification, 'sort_order' => $index + 1];
                $attributes['classification'] = $classification;
                $attributes['sort_order'] = $index + 1;
                $travel = $row['travel'] ?? [];
                $transportation = $this->compatibleTransportation($row['transportation'] ?? []);
                unset($attributes['travel'], $attributes['transportation']);
                if ($id) {
                    $detailService->update($existing->get($id), $attributes, $travel, $transportation);
                    $keptIds[] = $id;
                } else {
                    $detailService->create($trip, $attributes, $travel, $transportation);
                }
            }

            foreach ($existing as $detail) {
                if (! in_array($detail->id, $keptIds, true)) {
                    $detailService->delete($detail);
                }
            }
        });
    }

    private function compatibleTransportation(array $data): array
    {
        $mode = $data['mode'] ?? null;
        $fields = match ($mode) {
            'Company Vehicle' => ['vehicle', 'plate_number', 'driver'],
            'Private Vehicle' => ['vehicle', 'plate_number', 'owner_driver'],
            'Ride-Hailing' => ['provider'],
            'Taxi' => ['other_details'],
            'Public Transportation' => ['transport_type'],
            'Rental Vehicle' => ['rental_provider', 'vehicle', 'plate_number'],
            'Air' => ['airline', 'flight_number'],
            'Sea/Ferry' => ['shipping_line', 'vessel_trip'],
            'Other' => ['other_details'],
            default => [],
        };

        return array_intersect_key($data, array_flip(['mode', ...$fields]));
    }

    private function requiredTransportationFields(string $mode): array
    {
        return match ($mode) {
            'Company Vehicle' => ['vehicle' => 'Vehicle', 'plate_number' => 'Plate Number', 'driver' => 'Driver'],
            'Private Vehicle' => ['vehicle' => 'Vehicle', 'plate_number' => 'Plate Number', 'owner_driver' => 'Owner / Driver'],
            'Ride-Hailing' => ['provider' => 'Provider'],
            'Public Transportation' => ['transport_type' => 'Transportation Type'],
            'Rental Vehicle' => ['rental_provider' => 'Rental Provider', 'vehicle' => 'Vehicle', 'plate_number' => 'Plate Number'],
            'Air' => ['airline' => 'Airline', 'flight_number' => 'Flight Number'],
            'Sea/Ferry' => ['shipping_line' => 'Shipping Line', 'vessel_trip' => 'Vessel / Trip'],
            'Other' => ['other_details' => 'Specify'],
            default => [],
        };
    }

    public function rememberSuggestions(array $rows): void
    {
        $suggestions = app(OfficialBusinessSuggestionService::class);
        foreach ($rows as $row) {
            $context = ['company_id' => $row['company_id'] ?? null, 'project_id' => $row['project_id'] ?? null];
            foreach (['from_location' => 'from', 'destination' => 'destination', 'purpose' => 'purpose'] as $key => $type) {
                $suggestions->remember($type, $row[$key] ?? null, $context);
            }
        }
    }
}
