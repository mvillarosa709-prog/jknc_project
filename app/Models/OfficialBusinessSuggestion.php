<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialBusinessSuggestion extends Model
{
    protected $fillable = [
        'field_type',
        'normalized_value',
        'display_value',
        'context_key',
        'company_id',
        'deal_id',
        'project_id',
        'frequency_count',
        'last_used_at',
    ];

    protected $casts = [
        'frequency_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
