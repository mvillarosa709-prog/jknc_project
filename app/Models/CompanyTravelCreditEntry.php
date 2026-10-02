<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyTravelCreditEntry extends Model
{
    protected $fillable = [
        'company_travel_credit_account_id',
        'official_business_trip_ticket_id',
        'entry_type',
        'units_delta',
        'unit_value',
        'balance_after',
        'reason',
        'recorded_by_user_id',
        'reverses_entry_id',
        'reversed_at',
        'reversed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'units_delta' => 'decimal:2',
            'unit_value' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CompanyTravelCreditAccount::class, 'company_travel_credit_account_id');
    }

    public function tripTicket(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessTripTicket::class, 'official_business_trip_ticket_id')->withTrashed();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }
}
