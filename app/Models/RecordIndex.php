<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordIndex extends Model
{
    use HasUlids;

    protected $table = 'record_indexes';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'record_classification_id',
        'code',
        'name',
        'description',
        'advanced_field_config',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'advanced_field_config' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(RecordClassification::class, 'record_classification_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
