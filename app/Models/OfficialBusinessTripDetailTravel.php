<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialBusinessTripDetailTravel extends Model
{
    protected $table = 'official_business_trip_detail_travel';

    protected $fillable = [
        'official_business_trip_detail_id',
        'departure_date',
        'departure_time',
        'expected_arrival_date',
        'expected_arrival_time',
        'estimated_expense',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'expected_arrival_date' => 'date',
        'estimated_expense' => 'decimal:2',
    ];

    public function setDepartureDateAttribute($value): void
    {
        $this->attributes['departure_date'] = ! filled($value)
            ? null
            : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10));
    }

    public function setExpectedArrivalDateAttribute($value): void
    {
        $this->attributes['expected_arrival_date'] = ! filled($value)
            ? null
            : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : substr((string) $value, 0, 10));
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessTripDetail::class, 'official_business_trip_detail_id');
    }
}
