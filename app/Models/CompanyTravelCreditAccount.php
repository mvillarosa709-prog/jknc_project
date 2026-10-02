<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyTravelCreditAccount extends Model
{
    protected $fillable = ['company_id', 'available_units', 'credit_value'];

    protected function casts(): array
    {
        return [
            'available_units' => 'decimal:2',
            'credit_value' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CompanyTravelCreditEntry::class)->latest('id');
    }
}
