<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialBusinessTripDetailTransportation extends Model
{
    protected $table = 'official_business_trip_detail_transportation';

    protected $fillable = [
        'official_business_trip_detail_id',
        'mode',
        'vehicle',
        'plate_number',
        'driver',
        'owner_driver',
        'provider',
        'transport_type',
        'rental_provider',
        'airline',
        'flight_number',
        'shipping_line',
        'vessel_trip',
        'other_details',
    ];

    public function detail(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessTripDetail::class, 'official_business_trip_detail_id');
    }
}
