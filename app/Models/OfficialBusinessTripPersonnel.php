<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class OfficialBusinessTripPersonnel extends Model
{
    use SoftDeletes;

    protected $table = 'official_business_trip_personnel';

    protected $fillable = ['official_business_trip_id', 'employee_id', 'nature_of_travel', 'role'];

    protected static function booted(): void
    {
        static::creating(function (self $member): void {
            $employee = Employee::with('department')->findOrFail($member->employee_id);
            $member->employee_name_snapshot = $employee->full_name;
            $member->position_snapshot = $employee->position;
            $member->department_snapshot = $employee->department?->department_name;
            if (Schema::hasColumn('official_business_trip_personnel', 'immediate_superior_snapshot')) {
                $member->immediate_superior_snapshot = $employee->immediate_supervisor ?: $employee->reporting_to;
            }
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
