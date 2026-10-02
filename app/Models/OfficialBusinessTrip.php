<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficialBusinessTrip extends Model
{
        use SoftDeletes;

protected $fillable = [
        'ob_reference_no',

        'employee_id',
        'employee_code',
        'employee_name',
        'position',
        'department',
        'immediate_superior',
        'superior_email',

        'destination',
        'additional_stops',
        'purpose',
        'purpose_options',
        'purpose_other',
        'trip_type',
        'date_from',
        'date_to',
        'departure_time',
        'return_time',
        'actual_departure',
        'actual_return',
        'nature_of_travel',
        'team_members',

        'is_client_travel',
        'company_id',
        'contact_id',
        'deal_id',
        'project_id',
        'duration_minutes',
        'client_type',
        'client_id_no',
        'client_name',
        'client_email',
        'client_contract_number',
        'contract_type',
        'travel_billability',

        'client_payment_status',
        'client_payment_items',
        'client_payment_other',
        'amount_client_will_pay',

        'travel_credit_details',

        'transportation_mode',
        'transportation_details',
        'estimated_expenses',

        'attachment_paths',
        'attachment_types',
        'attachment_other',

        'remarks',
        'status',
        'movement_status',
        'created_by',
        'client_report_description',
        'include_in_client_report',
        'workflow_version',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'actual_departure' => 'datetime',
        'actual_return' => 'datetime',
        'estimated_expenses' => 'decimal:2',
        'amount_client_will_pay' => 'decimal:2',

        'purpose_options' => 'array',
        'team_members' => 'array',
        'client_payment_items' => 'array',
        'travel_credit_details' => 'array',
        'transportation_details' => 'array',
        'attachment_paths' => 'array',
        'attachment_types' => 'array',
        'is_client_travel' => 'boolean',
        'include_in_client_report' => 'boolean',
        'duration_minutes' => 'integer',
        'workflow_version' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $trip): void {
            if (
                $trip->isDirty('is_client_travel')
                && ! $trip->is_client_travel
                && \Illuminate\Support\Facades\Schema::hasTable('official_business_trip_tickets')
                && $trip->tripTickets()->exists()
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'is_client_travel' => 'An O.B. with active Trip Tickets cannot be changed to Internal.',
                ]);
            }
        });
    }
    public function tripTickets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OfficialBusinessTripTicket::class);
    }

    public function personnel(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OfficialBusinessTripPersonnel::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function contactRecord()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
