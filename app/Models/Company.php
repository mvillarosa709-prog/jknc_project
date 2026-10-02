<?php

namespace App\Models;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'company_name',
        'email',
        'phone',
        'website',
        'description',
        'address',
        'primary_contact_id',
        'owner_name',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function officialBusinessTripTickets(): HasMany
    {
        return $this->hasMany(OfficialBusinessTripTicket::class);
    }

    public function travelCreditAccount(): HasOne
    {
        return $this->hasOne(CompanyTravelCreditAccount::class);
    }

    public function cifs(): HasMany
    {
        return $this->hasMany(CompanyCif::class);
    }

    public function bifs(): HasMany
    {
        return $this->hasMany(CompanyBif::class);
    }

    public function latestBif(): HasOne
    {
        return $this->hasOne(CompanyBif::class)->latestOfMany();
    }

    public function primaryContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'primary_contact_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)->withTimestamps();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
