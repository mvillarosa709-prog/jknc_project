<?php

namespace App\Services;

use App\Models\OfficialBusinessTrip;
use App\Models\OfficialBusinessTripDetail;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfficialBusinessDetailService
{
    /**
     * Create one draft-safe detail row with its required one-to-one child records.
     * Completeness is intentionally deferred to the future submission validator.
     */
    public function create(
        OfficialBusinessTrip $trip,
        array $attributes = [],
        array $travel = [],
        array $transportation = []
    ): OfficialBusinessTripDetail {
        return DB::transaction(function () use ($trip, $attributes, $travel, $transportation): OfficialBusinessTripDetail {
            $detail = $trip->details()->create($this->detailAttributes($attributes));
            $detail->travelDetail()->create($this->travelAttributes($travel));
            $detail->transportationDetail()->create($this->transportationAttributes($transportation));

            return $detail->fresh(['travelDetail', 'transportationDetail']);
        });
    }

    /** Update a stable detail identity and upsert its single child records. */
    public function update(
        OfficialBusinessTripDetail $detail,
        array $attributes = [],
        array $travel = [],
        array $transportation = []
    ): OfficialBusinessTripDetail {
        return DB::transaction(function () use ($detail, $attributes, $travel, $transportation): OfficialBusinessTripDetail {
            $detail->fill($this->detailAttributes($attributes))->save();
            $detail->travelDetail()->updateOrCreate([], $this->travelAttributes($travel));
            $detail->transportationDetail()->updateOrCreate([], $this->transportationAttributes($transportation));

            return $detail->fresh(['travelDetail', 'transportationDetail']);
        });
    }

    /** @param array<int, int|string> $detailIds */
    public function reorder(OfficialBusinessTrip $trip, array $detailIds): void
    {
        DB::transaction(function () use ($trip, $detailIds): void {
            $ids = collect($detailIds)
                ->filter(fn ($id): bool => filled($id))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            $details = $trip->details()->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            if ($details->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'detail_ids' => 'Every reordered O.B. detail must belong to this O.B. header.',
                ]);
            }

            foreach ($ids as $order => $id) {
                $details->get($id)->update(['sort_order' => $order + 1]);
            }
        });
    }

    public function delete(OfficialBusinessTripDetail $detail): void
    {
        DB::transaction(function () use ($detail): void {
            if ($detail->attachments()->exists()) {
                throw ValidationException::withMessages([
                    'details_json' => 'Remove or intentionally relink attachments for O.B. Detail '.$detail->sort_order.' before removing the detail.',
                ]);
            }

            $detail->forceDelete();
        });
    }

    private function detailAttributes(array $attributes): array
    {
        return Arr::only($attributes, [
            'classification',
            'sort_order',
            'from_location',
            'destination',
            'purpose',
            'company_id',
            'engagement_type',
            'deal_id',
            'project_id',
            'internal_activity_type',
            'department_id',
            'related_internal_type',
            'related_internal_id',
            'activity_event_name',
            'social_activity_type',
            'organization_host',
            'contact_id',
            'related_contact_text',
        ]);
    }

    private function travelAttributes(array $attributes): array
    {
        return Arr::only($attributes, [
            'departure_date',
            'departure_time',
            'expected_arrival_date',
            'expected_arrival_time',
            'estimated_expense',
        ]);
    }

    private function transportationAttributes(array $attributes): array
    {
        return Arr::only($attributes, [
            'mode',
            'vehicle',
            'plate_number',
            'driver',
            'owner_driver',
            'provider',
            'transport_type',
            'rental_provider',
            'airline',
            'flight_number',
            'shipping_line',
            'vessel_trip',
            'other_details',
        ]);
    }
}
