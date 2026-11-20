<?php

namespace App\Models;

use App\Enums\ReferenceStatus;
use App\Enums\SourceType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordSource extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'type',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => SourceType::class,
            'status' => ReferenceStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', ReferenceStatus::Active->value);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
