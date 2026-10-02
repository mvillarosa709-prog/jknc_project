<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OfficialBusinessTripTicket extends Model
{
    use SoftDeletes;

    protected $fillable = ['official_business_trip_id', 'company_id', 'created_by_user_id'];

    protected $attributes = ['status' => 'Draft'];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            $trip = OfficialBusinessTrip::find($ticket->official_business_trip_id);
            if (! $trip || ! $trip->is_client_travel) {
                throw ValidationException::withMessages([
                    'official_business_trip_id' => 'Trip Tickets require an active Client O.B.',
                ]);
            }

            if (
                (int) $trip->workflow_version >= 3
                && in_array($trip->status, ['Completed', 'Cancelled', 'Rejected'], true)
            ) {
                throw ValidationException::withMessages([
                    'official_business_trip_id' => 'V3 Trip Ticket membership cannot be changed after the master O.B. reaches a terminal status.',
                ]);
            }

            $company = Company::findOrFail($ticket->company_id);
            // Independent of mutable or non-unique legacy OBF reference strings.
            $ticket->reference_no = 'OBTT-'.$trip->id.'-'.Str::ulid();
            $ticket->client_name_snapshot = $company->company_name;
            $ticket->status = 'Draft';
        });

        static::restoring(function (self $ticket): void {
            $trip = OfficialBusinessTrip::find($ticket->official_business_trip_id);

            if (
                $trip
                && (int) $trip->workflow_version >= 3
                && in_array($trip->status, ['Completed', 'Cancelled', 'Rejected'], true)
            ) {
                throw ValidationException::withMessages([
                    'official_business_trip_id' => 'Archived V3 Trip Tickets cannot be restored after the master O.B. reaches a terminal status.',
                ]);
            }
        });
        static::updating(function (self $ticket): void {
            if ($ticket->isDirty(['official_business_trip_id', 'company_id', 'reference_no', 'client_name_snapshot'])) {
                throw ValidationException::withMessages([
                    'reference_no' => 'Trip Ticket identity and original client snapshot cannot be reassigned.',
                ]);
            }
        });
    }

    public function officialBusinessTrip(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessTrip::class)->withTrashed();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    public function stops(): HasMany
    {
        return $this->hasMany(OfficialBusinessTripTicketStop::class)
            ->orderBy('sequence');
    }

    public function personnel(): HasMany
    {
        return $this->hasMany(OfficialBusinessTripTicketPersonnel::class);
    }

    public function financialTreatment(): HasOne
    {
        return $this->hasOne(OfficialBusinessTripTicketFinancialTreatment::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(OfficialBusinessTripTicketCost::class)->orderBy('id');
    }

    public function travelCreditEntries(): HasMany
    {
        return $this->hasMany(CompanyTravelCreditEntry::class, 'official_business_trip_ticket_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ActivityAttachment::class, 'activity_id')
            ->where('activity_type', 'OFFICIAL_BUSINESS_TRIP_TICKET')
            ->orderBy('uploaded_at')
            ->orderBy('id');
    }
}
