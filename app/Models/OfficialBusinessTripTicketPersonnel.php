<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class OfficialBusinessTripTicketPersonnel extends Model
{
    use SoftDeletes;

    protected $table = 'official_business_trip_ticket_personnel';

    protected $fillable = [
        'official_business_trip_ticket_id',
        'employee_id',
        'role',
        'participation_status',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $personnel): void {
            $employee = Employee::with('department')->findOrFail($personnel->employee_id);
            $personnel->employee_name_snapshot = $employee->full_name;
            $personnel->position_snapshot = $employee->position;
            $personnel->department_snapshot = $employee->department?->department_name;
        });

        static::updating(function (self $personnel): void {
            if ($personnel->isDirty([
                'official_business_trip_ticket_id',
                'employee_id',
                'employee_name_snapshot',
                'position_snapshot',
                'department_snapshot',
            ])) {
                throw ValidationException::withMessages([
                    'employee_id' => 'Trip Ticket personnel identity and snapshots cannot be reassigned.',
                ]);
            }
        });
    }

    public function tripTicket(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessTripTicket::class, 'official_business_trip_ticket_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
