<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class OfficialBusinessTripTicketStop extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'official_business_trip_ticket_id',
        'sequence',
        'destination',
        'activity',
        'planned_at',
        'actual_arrival_at',
        'resolved_at',
        'resolution_status',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'planned_at' => 'datetime',
        'actual_arrival_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $stop): void {
            if ($stop->isDirty('official_business_trip_ticket_id')) {
                throw ValidationException::withMessages([
                    'official_business_trip_ticket_id' => 'A Trip Ticket stop cannot be reassigned.',
                ]);
            }
        });
    }

    public function tripTicket(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessTripTicket::class, 'official_business_trip_ticket_id');
    }
}
