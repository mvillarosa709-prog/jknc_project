<?php

namespace App\Models;

use App\Enums\RecordFieldType;
use App\Enums\ReferenceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company's registry of custom Record fields.
 *
 * This is a registry only: it records which extra fields the company intends to
 * capture, so the scheme can be reviewed and agreed. It deliberately does not
 * yet inject inputs into the filing form, which keeps the working filing flow
 * untouched. `is_filterable` records the intent for when that wiring happens,
 * not a behaviour that exists today.
 */
class RecordField extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'type',
        'is_filterable',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => RecordFieldType::class,
            'status' => ReferenceStatus::class,
            'is_filterable' => 'boolean',
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
