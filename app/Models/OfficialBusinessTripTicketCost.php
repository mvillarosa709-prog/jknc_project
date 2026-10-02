<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficialBusinessTripTicketCost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'official_business_trip_ticket_id',
        'category',
        'description',
        'amount',
        'incurred_on',
        'billing_treatment',
        'evidence_reference',
        'recorded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'incurred_on' => 'date',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessTripTicket::class, 'official_business_trip_ticket_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
