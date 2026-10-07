<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficialBusinessTripDetail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'official_business_trip_id',
        'classification',
        'sort_order',
        'from_location',
        'destination',
        'purpose',
        'company_id',
        'engagement_type',
        'deal_id',
        'project_id',
        'internal_activity_type',
        'department_id',
        'related_internal_type',
        'related_internal_id',
        'activity_event_name',
        'social_activity_type',
        'organization_host',
        'contact_id',
        'related_contact_text',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'related_internal_id' => 'integer',
    ];

    public function officialBusinessTrip(): BelongsTo
    {
        return $this->belongsTo(OfficialBusinessTrip::class);
    }

    /** Canonical O.B. client identity is Company; this is intentionally not a Contact alias. */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function travelDetail(): HasOne
    {
        return $this->hasOne(OfficialBusinessTripDetailTravel::class);
    }

    public function transportationDetail(): HasOne
    {
        return $this->hasOne(OfficialBusinessTripDetailTransportation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ActivityAttachment::class, 'official_business_trip_detail_id');
    }
}
