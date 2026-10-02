<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialBusinessTripTicketFinancialTreatment extends Model
{
    protected $fillable = [
        'official_business_trip_ticket_id',
        'billing_treatment',
        'travel_credit_units',
        'travel_credit_value',
        'travel_credit_entry_id',
        'evidence_required',
        'evidence_requirement_notes',
        'additional_costs_status',
        'notes',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'travel_credit_units' => 'decimal:2',
            'travel_credit_value' => 'decimal:2',
            'evidence_required' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessTripTicket::class, 'official_business_trip_ticket_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function travelCreditEntry(): BelongsTo
    {
        return $this->belongsTo(CompanyTravelCreditEntry::class, 'travel_credit_entry_id');
    }
}
