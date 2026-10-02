<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficialBusinessTripPersonnel extends Model
{
    use SoftDeletes;

    protected $table = 'official_business_trip_personnel';

    protected $fillable = ['official_business_trip_id', 'employee_id'];

    protected static function booted(): void
    {
        static::creating(function (self $member): void {
            $employee = Employee::with('department')->findOrFail($member->employee_id);
            $member->employee_name_snapshot = $employee->full_name;
            $member->position_snapshot = $employee->position;
            $member->department_snapshot = $employee->department?->department_name;
        });
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessTrip::class, 'official_business_trip_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
