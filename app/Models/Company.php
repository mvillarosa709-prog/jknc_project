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
        'name',
        'code',
        'business_name',
        'tenant_id',
        'email',
        'phone',
        'website',
        'description',
        'address',
        'primary_contact_id',
        'owner_name',
        'record_unit_allocation',
        'storage_limit_bytes',
        'record_sequence_digits',
        'record_next_sequence',
        'record_include_year',
    ];

    protected function casts(): array
    {
        return [
            'record_unit_allocation' => 'integer',
            'storage_limit_bytes' => 'integer',
            'record_sequence_digits' => 'integer',
            'record_next_sequence' => 'integer',
            'record_include_year' => 'boolean',
        ];
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->attributes['name'] ?? $this->attributes['company_name'] ?? 'John Kelly & Company');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function intakeItems(): HasMany
    {
        return $this->hasMany(RecordIntakeItem::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(RecordFile::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(RecordSource::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }

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
